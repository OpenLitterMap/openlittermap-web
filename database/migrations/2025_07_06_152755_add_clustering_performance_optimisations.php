<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add generated columns for cell coordinates
        if (!Schema::hasColumn('photos', 'cell_x')) {
            DB::statement('
                ALTER TABLE photos
                ADD COLUMN cell_x INT GENERATED ALWAYS AS (FLOOR((lon + 180) / 0.05)) STORED,
                ADD COLUMN cell_y INT GENERATED ALWAYS AS (FLOOR((lat + 90) / 0.05)) STORED
            ');
        }

        // Add covering index for fast clustering
        if (!$this->indexExists('photos', 'idx_photos_fast_cluster')) {
            DB::statement('
                CREATE INDEX idx_photos_fast_cluster
                ON photos(verified, tile_key, cell_x, cell_y, lat, lon)
            ');
        }

        // Optional: Add index for tile_key only queries
        if (!$this->indexExists('photos', 'idx_photos_tile_key')) {
            DB::statement('
                CREATE INDEX idx_photos_tile_key
                ON photos(tile_key)
            ');
        }
    }

    public function down(): void
    {
        // Drop indexes
        if ($this->indexExists('photos', 'idx_photos_fast_cluster')) {
            DB::statement('DROP INDEX idx_photos_fast_cluster ON photos');
        }

        if ($this->indexExists('photos', 'idx_photos_tile_key')) {
            DB::statement('DROP INDEX idx_photos_tile_key ON photos');
        }

        // Drop generated columns
        if (Schema::hasColumn('photos', 'cell_x')) {
            DB::statement('ALTER TABLE photos DROP COLUMN cell_x, DROP COLUMN cell_y');
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
