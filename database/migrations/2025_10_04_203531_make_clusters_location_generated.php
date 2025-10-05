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

        // Drop the existing location column
        if (Schema::hasColumn('clusters', 'location')) {
            DB::statement('ALTER TABLE clusters DROP COLUMN location');
        }

        // Add location as a generated column
        // This ensures location is always in sync with lat/lon
        DB::statement("
            ALTER TABLE clusters
            ADD COLUMN location POINT
                GENERATED ALWAYS AS (ST_SRID(POINT(lon, lat), 4326)) STORED
                NOT NULL
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

        // Drop generated column
        if (Schema::hasColumn('clusters', 'location')) {
            DB::statement('ALTER TABLE clusters DROP COLUMN location');
        }

        // Recreate as regular column
        DB::statement("
            ALTER TABLE clusters
            ADD COLUMN location POINT NOT NULL
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
