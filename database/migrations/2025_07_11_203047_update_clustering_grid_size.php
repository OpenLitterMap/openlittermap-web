<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop the covering index that depends on the generated columns
        if ($this->indexExists('photos', 'idx_photos_fast_cluster')) {
            DB::statement('DROP INDEX idx_photos_fast_cluster ON photos');
        }

        // Drop the existing generated columns
        if (Schema::hasColumn('photos', 'cell_x')) {
            DB::statement('ALTER TABLE photos DROP COLUMN cell_x, DROP COLUMN cell_y');
        }

        // Add new generated columns with the new smallest_grid value (0.01)
        DB::statement("
            ALTER TABLE photos
            ADD COLUMN cell_x INT UNSIGNED GENERATED ALWAYS AS (
                FLOOR((lon + 180) / 0.01)
            ) STORED,
            ADD COLUMN cell_y INT UNSIGNED GENERATED ALWAYS AS (
                FLOOR((lat + 90) / 0.01)
            ) STORED
        ");

        // Recreate the covering index WITH lat/lon
        DB::statement('
            CREATE INDEX idx_photos_fast_cluster
            ON photos(verified, tile_key, cell_x, cell_y, lat, lon)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the covering index
        if ($this->indexExists('photos', 'idx_photos_fast_cluster')) {
            DB::statement('DROP INDEX idx_photos_fast_cluster ON photos');
        }

        // Drop the new columns
        if (Schema::hasColumn('photos', 'cell_x')) {
            DB::statement('ALTER TABLE photos DROP COLUMN cell_x, DROP COLUMN cell_y');
        }

        // Restore original generated columns with 0.05 grid
        DB::statement("
            ALTER TABLE photos
            ADD COLUMN cell_x INT GENERATED ALWAYS AS (
                FLOOR((lon + 180) / 0.05)
            ) STORED,
            ADD COLUMN cell_y INT GENERATED ALWAYS AS (
                FLOOR((lat + 90) / 0.05)
            ) STORED
        ");

        // Restore the covering index WITH lat/lon
        DB::statement('
            CREATE INDEX idx_photos_fast_cluster
            ON photos(verified, tile_key, cell_x, cell_y, lat, lon)
        ');
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
