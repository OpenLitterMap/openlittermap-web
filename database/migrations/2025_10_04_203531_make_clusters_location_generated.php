<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop spatial index first (depends on the column)
        if ($this->indexExists('clusters', 'idx_clusters_spatial')) {
            DB::statement('DROP INDEX idx_clusters_spatial ON clusters');
        }

        // Convert location to a generated column
        // This ensures location is always in sync with lat/lon
        DB::statement("
            ALTER TABLE clusters
            MODIFY COLUMN location POINT NOT NULL
                AS (ST_SRID(POINT(lon, lat), 4326)) STORED
        ");

        // Recreate spatial index
        DB::statement('CREATE SPATIAL INDEX idx_clusters_spatial ON clusters(location)');
    }

    public function down(): void
    {
        // Drop spatial index
        if ($this->indexExists('clusters', 'idx_clusters_spatial')) {
            DB::statement('DROP INDEX idx_clusters_spatial ON clusters');
        }

        // Convert back to regular column
        DB::statement("
            ALTER TABLE clusters
            MODIFY COLUMN location POINT NOT NULL
        ");

        // Recreate spatial index
        DB::statement('CREATE SPATIAL INDEX idx_clusters_spatial ON clusters(location)');
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
};
