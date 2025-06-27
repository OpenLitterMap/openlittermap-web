<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('active_tiles', function (Blueprint $table) {
            $table->unsignedInteger('tile_key')->primary();
            $table->unsignedInteger('photo_count')->default(0);
            $table->timestamp('last_updated')->useCurrent()->useCurrentOnUpdate();

            $table->index('last_updated', 'idx_last_updated');
        });

        // Populate from existing photos
        $this->info('Populating active tiles from existing photos...');

        DB::insert('
            INSERT INTO active_tiles (tile_key, photo_count)
            SELECT tile_key, COUNT(*)
            FROM photos
            WHERE tile_key IS NOT NULL AND verified = 2
            GROUP BY tile_key
        ');

        $tileCount = DB::table('active_tiles')->count();
        $this->info("✓ Created $tileCount active tiles");

        // Create triggers to maintain active_tiles automatically
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_insert_tiles');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_update_tiles');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_delete_tiles');

        // After INSERT trigger
        DB::unprepared('
            CREATE TRIGGER photos_after_insert_tiles AFTER INSERT ON photos
            FOR EACH ROW
            BEGIN
                IF NEW.tile_key IS NOT NULL AND NEW.verified = 2 THEN
                    INSERT INTO active_tiles (tile_key, photo_count)
                    VALUES (NEW.tile_key, 1)
                    ON DUPLICATE KEY UPDATE
                        photo_count = photo_count + 1,
                        last_updated = CURRENT_TIMESTAMP;
                END IF;
            END
        ');

        // After UPDATE trigger - Fixed with atomic operations
        DB::unprepared('
            CREATE TRIGGER photos_after_update_tiles AFTER UPDATE ON photos
            FOR EACH ROW
            BEGIN
                -- Only process if tile_key or verified actually changed
                IF (OLD.tile_key <=> NEW.tile_key) = 0 OR OLD.verified != NEW.verified THEN

                    -- Decrement old tile if it was verified
                    IF OLD.tile_key IS NOT NULL AND OLD.verified = 2 THEN
                        UPDATE active_tiles
                        SET photo_count = photo_count - 1,
                            last_updated = CURRENT_TIMESTAMP
                        WHERE tile_key = OLD.tile_key AND photo_count > 0;

                        -- Delete if now empty
                        DELETE FROM active_tiles
                        WHERE tile_key = OLD.tile_key AND photo_count = 0;
                    END IF;

                    -- Increment new tile if verified
                    IF NEW.tile_key IS NOT NULL AND NEW.verified = 2 THEN
                        INSERT INTO active_tiles (tile_key, photo_count)
                        VALUES (NEW.tile_key, 1)
                        ON DUPLICATE KEY UPDATE
                            photo_count = photo_count + VALUES(photo_count),
                            last_updated = CURRENT_TIMESTAMP;
                    END IF;
                END IF;
            END
        ');

        // After DELETE trigger - Fixed with atomic delete
        DB::unprepared('
            CREATE TRIGGER photos_after_delete_tiles AFTER DELETE ON photos
            FOR EACH ROW
            BEGIN
                IF OLD.tile_key IS NOT NULL AND OLD.verified = 2 THEN
                    -- Atomic decrement
                    UPDATE active_tiles
                    SET photo_count = photo_count - 1,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE tile_key = OLD.tile_key AND photo_count > 0;

                    -- Delete if now empty (row is already locked from UPDATE)
                    DELETE FROM active_tiles
                    WHERE tile_key = OLD.tile_key AND photo_count = 0;
                END IF;
            END
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_insert_tiles');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_update_tiles');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_after_delete_tiles');

        Schema::dropIfExists('active_tiles');
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }
};
