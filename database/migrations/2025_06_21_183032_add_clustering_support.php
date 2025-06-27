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
        // Add tile_key to photos
        if (!Schema::hasColumn('photos', 'tile_key')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->unsignedInteger('tile_key')->nullable()->after('lon');
                $table->index(['verified', 'tile_key', 'lat', 'lon'], 'idx_photos_clustering');
            });
        }

        // Create triggers for automatic tile_key maintenance
        DB::unprepared('DROP TRIGGER IF EXISTS photos_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_before_update');

        DB::unprepared('
            CREATE TRIGGER photos_before_insert BEFORE INSERT ON photos
            FOR EACH ROW
            BEGIN
                DECLARE norm_lon DOUBLE;

                IF NEW.lat IS NOT NULL AND NEW.lon IS NOT NULL THEN
                    -- Normalize longitude to -180 to 180 range
                    SET norm_lon = MOD(NEW.lon + 540, 360) - 180;
                    SET NEW.tile_key = FLOOR((NEW.lat + 90) / 0.25) * 1440 +
                                      FLOOR((norm_lon + 180) / 0.25);
                END IF;
            END
        ');

        DB::unprepared('
            CREATE TRIGGER photos_before_update BEFORE UPDATE ON photos
            FOR EACH ROW
            proc: BEGIN
                DECLARE norm_lon DOUBLE;

                -- Check if coordinates unchanged (null-safe comparison)
                IF (NEW.lat <=> OLD.lat) = 1 AND (NEW.lon <=> OLD.lon) = 1 THEN
                    -- Coordinates unchanged, keep existing tile_key and exit
                    SET NEW.tile_key = OLD.tile_key;
                    LEAVE proc;
                END IF;

                IF NEW.lat IS NOT NULL AND NEW.lon IS NOT NULL THEN
                    -- Normalize longitude to -180 to 180 range
                    SET norm_lon = MOD(NEW.lon + 540, 360) - 180;
                    SET NEW.tile_key = FLOOR((NEW.lat + 90) / 0.25) * 1440 +
                                      FLOOR((norm_lon + 180) / 0.25);
                ELSE
                    SET NEW.tile_key = NULL;
                END IF;
            END
        ');

        // Cleanse invalid coordinates before populating tile_key
        $this->info('Cleaning invalid coordinates...');
        $invalid = DB::update('
            UPDATE photos
            SET lat = NULL, lon = NULL, tile_key = NULL
            WHERE lat NOT BETWEEN -90 AND 90
               OR lon NOT BETWEEN -180 AND 180
        ');
        if ($invalid > 0) {
            $this->info("  ✓ Cleaned $invalid photos with invalid coordinates");
        }

        // Populate tile_key for existing photos
        $this->info('Populating tile_key for existing photos...');

        // Single atomic update - exact same formula as trigger
        // norm_lon = MOD(lon + 540, 360) - 180, then (norm_lon + 180) / 0.25
        // This simplifies to: FLOOR((MOD(lon + 540, 360)) / 0.25)
        $affected = DB::update('
            UPDATE photos
            SET tile_key = FLOOR((lat + 90) / 0.25) * 1440 +
                          FLOOR((MOD(lon + 540, 360)) / 0.25)
            WHERE lat IS NOT NULL
                AND lon IS NOT NULL
                AND tile_key IS NULL
        ');

        if ($affected > 0) {
            $this->info("  ✓ Updated $affected photos with tile_key");
        }

        // Add missing columns to clusters table first
        if (!Schema::hasColumn('clusters', 'tile_key')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->unsignedInteger('tile_key')->nullable()->after('id');
                $table->index('tile_key');
            });
        }

        // Add cell_x and cell_y columns if they don't exist (SIGNED for negative values)
        if (!Schema::hasColumn('clusters', 'cell_x')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->integer('cell_x')->after('zoom');  // SIGNED by default
                $table->integer('cell_y')->after('cell_x'); // SIGNED by default
            });
        }

        // Fix year column to be NOT NULL DEFAULT 0 (outside of any table closure)
        DB::statement('UPDATE clusters SET year = 0 WHERE year IS NULL');
        DB::statement('ALTER TABLE clusters MODIFY year YEAR NOT NULL DEFAULT 0');

        // Add unique constraint to clusters if it doesn't exist
        $hasUniqueKey = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
                AND table_name = 'clusters'
                AND index_name = 'uk_cluster'
        ")[0]->count > 0;

        if (!$hasUniqueKey) {
            // Check for existing duplicates before adding unique constraint
            $duplicates = DB::select("
                SELECT tile_key, zoom, year, cell_x, cell_y, COUNT(*) as cnt
                FROM clusters
                WHERE tile_key IS NOT NULL
                GROUP BY tile_key, zoom, year, cell_x, cell_y
                HAVING COUNT(*) > 1
            ");

            if (count($duplicates) > 0) {
                $this->info('Found ' . count($duplicates) . ' duplicate cluster combinations, removing duplicates...');

                // Single set-based delete for all duplicates
                DB::statement("
                    DELETE c1 FROM clusters c1
                    INNER JOIN (
                        SELECT MIN(id) as keep_id, tile_key, zoom, year, cell_x, cell_y
                        FROM clusters
                        WHERE tile_key IS NOT NULL
                        GROUP BY tile_key, zoom, year, cell_x, cell_y
                        HAVING COUNT(*) > 1
                    ) c2
                    WHERE c1.tile_key = c2.tile_key
                        AND c1.zoom = c2.zoom
                        AND c1.year = c2.year
                        AND c1.cell_x = c2.cell_x
                        AND c1.cell_y = c2.cell_y
                        AND c1.id != c2.keep_id
                ");
            }

            Schema::table('clusters', function (Blueprint $table) {
                $table->unique(['tile_key', 'zoom', 'year', 'cell_x', 'cell_y'], 'uk_cluster');
            });
        }

        // Add index for efficient time-based queries
        if (!$this->indexExists('photos', 'idx_photos_tile_verified_time')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->index(['tile_key', 'verified', 'created_at'], 'idx_photos_tile_verified_time');
            });
        }

        // Handle legacy columns in clusters table
        $legacyColumns = [];
        if (Schema::hasColumn('clusters', 'geohash')) {
            $legacyColumns[] = 'geohash';
        }
        if (Schema::hasColumn('clusters', 'point_count_abbreviated')) {
            $legacyColumns[] = 'point_count_abbreviated';
        }

        if (!empty($legacyColumns)) {
            $this->info('Dropping legacy columns: ' . implode(', ', $legacyColumns));
            Schema::table('clusters', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
            $this->info('✓ Dropped legacy columns');
        }

        $this->info('✓ Clustering support added successfully');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS photos_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS photos_before_update');

        // Drop CHECK constraint if it exists (might have been added separately)
        try {
            DB::statement('ALTER TABLE photos DROP CONSTRAINT chk_photos_coordinates');
        } catch (\Exception $e) {
            // Constraint might not exist, ignore
        }

        // Drop indexes - check existence outside closure
        if ($this->indexExists('photos', 'idx_photos_clustering')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('idx_photos_clustering');
            });
        }

        if ($this->indexExists('photos', 'idx_photos_tile_verified_time')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('idx_photos_tile_verified_time');
            });
        }

        if ($this->indexExists('clusters', 'uk_cluster')) {
            Schema::table('clusters', function (Blueprint $table) {
                $table->dropUnique('uk_cluster');
            });
        }

        // Drop columns
        if (Schema::hasColumn('photos', 'tile_key')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropColumn('tile_key');
            });
        }

        $columnsToRemove = [];
        if (Schema::hasColumn('clusters', 'cell_x')) {
            $columnsToRemove[] = 'cell_x';
        }
        if (Schema::hasColumn('clusters', 'cell_y')) {
            $columnsToRemove[] = 'cell_y';
        }
        if (Schema::hasColumn('clusters', 'tile_key')) {
            $columnsToRemove[] = 'tile_key';
        }

        if (!empty($columnsToRemove)) {
            Schema::table('clusters', function (Blueprint $table) use ($columnsToRemove) {
                $table->dropColumn($columnsToRemove);
            });
        }

        // Revert year column to nullable
        if (Schema::hasColumn('clusters', 'year')) {
            DB::statement('ALTER TABLE clusters MODIFY year YEAR NULL');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
                AND table_name = ?
                AND index_name = ?
        ", [$table, $index]);

        return $result && $result->count > 0;
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }
};
