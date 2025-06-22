<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->info("=== Starting Spatial Setup ===\n");

        // Step 1: Add spatial columns to photos
        $this->setupPhotosTable();

        // Step 2: Add spatial columns to clusters
        $this->setupClustersTable();

        // Step 3: Create spatial tile grid
        $this->createSpatialTileGrid();

        // Step 4: Create indexes (regular, not spatial due to NULL constraint)
        $this->createOptimizedIndexes();

        $this->info("\n=== Spatial Setup Complete ===");
        $this->info("Next steps:");
        $this->info("1. Run: php artisan spatial:populate-grid");
        $this->info("2. Test with: php artisan spatial:test");
    }

    private function setupPhotosTable(): void
    {
        $this->info("Setting up photos table...");

        // Add location column if it doesn't exist
        if (!Schema::hasColumn('photos', 'location')) {
            DB::statement("ALTER TABLE photos ADD COLUMN location POINT NULL AFTER lon");
            $this->info("  ✓ Created location column");
        }

        // Populate location data
        $unpopulated = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM photos
            WHERE location IS NULL
                AND lat IS NOT NULL
                AND lon IS NOT NULL
        ")->cnt;

        if ($unpopulated > 0) {
            $this->info("  → Populating location for $unpopulated photos...");

            $batchSize = 10000;
            $processed = 0;

            while ($processed < $unpopulated) {
                $affected = DB::affectingStatement("
                    UPDATE photos
                    SET location = POINT(lon, lat)
                    WHERE location IS NULL
                        AND lat IS NOT NULL
                        AND lon IS NOT NULL
                    LIMIT $batchSize
                ");

                if ($affected == 0) break;

                $processed += $affected;
                if ($processed % 50000 == 0 || $processed >= $unpopulated) {
                    $this->info("    Processed $processed / $unpopulated photos");
                }
            }

            $this->info("  ✓ Location data populated");
        } else {
            $this->info("  ✓ Location data already populated");
        }

        // Add generated column for spatial indexing (workaround for NULL issue)
        if (!Schema::hasColumn('photos', 'location_indexed')) {
            $this->info("  → Creating indexed location column...");

            DB::statement("
                ALTER TABLE photos
                ADD COLUMN location_indexed POINT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN location IS NOT NULL THEN location
                        WHEN lat IS NOT NULL AND lon IS NOT NULL THEN POINT(lon, lat)
                        ELSE NULL
                    END
                ) STORED
            ");

            $this->info("  ✓ Created location_indexed column");
        }
    }

    private function setupClustersTable(): void
    {
        $this->info("\nSetting up clusters table...");

        // Add center_point column
        if (!Schema::hasColumn('clusters', 'center_point')) {
            DB::statement("ALTER TABLE clusters ADD COLUMN center_point POINT NULL AFTER lon");
            $this->info("  ✓ Created center_point column");
        }

        // Populate center_point
        $unpopulated = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM clusters
            WHERE center_point IS NULL
                AND lat IS NOT NULL
                AND lon IS NOT NULL
        ")->cnt;

        if ($unpopulated > 0) {
            $this->info("  → Populating center_point for $unpopulated clusters...");

            DB::statement("
                UPDATE clusters
                SET center_point = POINT(lon, lat)
                WHERE center_point IS NULL
                    AND lat IS NOT NULL
                    AND lon IS NOT NULL
            ");

            $this->info("  ✓ Center points populated");
        } else {
            $this->info("  ✓ Center points already populated");
        }

        // Add cluster_bounds column
        if (!Schema::hasColumn('clusters', 'cluster_bounds')) {
            DB::statement("ALTER TABLE clusters ADD COLUMN cluster_bounds GEOMETRY NULL");
            $this->info("  ✓ Created cluster_bounds column");
        }

        // Add indexed column for clusters too
        if (!Schema::hasColumn('clusters', 'center_indexed')) {
            DB::statement("
                ALTER TABLE clusters
                ADD COLUMN center_indexed POINT
                GENERATED ALWAYS AS (
                    CASE
                        WHEN center_point IS NOT NULL THEN center_point
                        WHEN lat IS NOT NULL AND lon IS NOT NULL THEN POINT(lon, lat)
                        ELSE NULL
                    END
                ) STORED
            ");

            $this->info("  ✓ Created center_indexed column");
        }
    }

    private function createSpatialTileGrid(): void
    {
        $this->info("\nSetting up spatial tile grid...");

        // Create table
        DB::statement("
            CREATE TABLE IF NOT EXISTS spatial_tile_grid (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tile_key INT UNSIGNED NOT NULL UNIQUE,
                bounds POLYGON NOT NULL,
                center POINT NOT NULL,

                INDEX idx_tile_key (tile_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $count = DB::selectOne("SELECT COUNT(*) as cnt FROM spatial_tile_grid")->cnt;

        if ($count == 0) {
            $this->info("  ✓ Created empty spatial_tile_grid table");
            $this->info("  → Run 'php artisan spatial:populate-grid' to populate it");
        } else {
            $this->info("  ✓ Spatial tile grid already has $count tiles");
        }
    }

    private function createOptimizedIndexes(): void
    {
        $this->info("\nCreating optimized indexes...");

        // Photos indexes
        if (!$this->indexExists('photos', 'idx_photos_geo')) {
            DB::statement("
                CREATE INDEX idx_photos_geo
                ON photos(verified, lat, lon)
            ");
            $this->info("  ✓ Created geographic index on photos");
        }

        if (!$this->indexExists('photos', 'idx_photos_tile_verified')) {
            DB::statement("
                CREATE INDEX idx_photos_tile_verified
                ON photos(tile_key, verified)
            ");
            $this->info("  ✓ Created tile index on photos");
        }

        // Try to create spatial indexes on the indexed columns
        $this->tryCreateSpatialIndex('photos', 'location_indexed', 'idx_photos_location_spatial');
        $this->tryCreateSpatialIndex('clusters', 'center_indexed', 'idx_clusters_center_spatial');

        // Clusters indexes
        if (!$this->indexExists('clusters', 'idx_clusters_tile_zoom')) {
            DB::statement("
                CREATE INDEX idx_clusters_tile_zoom
                ON clusters(tile_key, zoom, year)
            ");
            $this->info("  ✓ Created tile/zoom index on clusters");
        }

        // Spatial tile grid indexes
        $this->tryCreateSpatialIndex('spatial_tile_grid', 'bounds', 'idx_tile_bounds');
        $this->tryCreateSpatialIndex('spatial_tile_grid', 'center', 'idx_tile_center');
    }

    private function tryCreateSpatialIndex(string $table, string $column, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        try {
            // Check if all values are NOT NULL
            $nullCount = DB::selectOne("SELECT COUNT(*) as cnt FROM $table WHERE $column IS NULL")->cnt;

            if ($nullCount == 0) {
                // Try to make column NOT NULL first
                DB::statement("ALTER TABLE $table MODIFY $column POINT NOT NULL");
                DB::statement("CREATE SPATIAL INDEX $indexName ON $table($column)");
                $this->info("  ✓ Created spatial index $indexName");
            } else {
                // Create regular index instead
                $colLength = ($column === 'bounds') ? 32 : 25;
                DB::statement("CREATE INDEX {$indexName}_regular ON $table($column($colLength))");
                $this->info("  → Created regular index {$indexName}_regular (spatial not possible due to NULLs)");
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠ Could not create spatial index $indexName: " . substr($e->getMessage(), 0, 100));
        }
    }

    public function down(): void
    {
        // Drop spatial_tile_grid table
        Schema::dropIfExists('spatial_tile_grid');

        // Drop indexes
        $indexes = [
            'photos' => [
                'idx_photos_geo',
                'idx_photos_tile_verified',
                'idx_photos_location_spatial',
                'idx_photos_location_spatial_regular',
                'idx_location_indexed_regular'
            ],
            'clusters' => [
                'idx_clusters_tile_zoom',
                'idx_clusters_center_spatial',
                'idx_clusters_center_spatial_regular',
                'idx_center_indexed_regular'
            ]
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }

        // Drop generated columns
        if (Schema::hasColumn('photos', 'location_indexed')) {
            DB::statement("ALTER TABLE photos DROP COLUMN location_indexed");
        }

        if (Schema::hasColumn('clusters', 'center_indexed')) {
            DB::statement("ALTER TABLE clusters DROP COLUMN center_indexed");
        }

        // Drop spatial columns
        if (Schema::hasColumn('clusters', 'cluster_bounds')) {
            DB::statement("ALTER TABLE clusters DROP COLUMN cluster_bounds");
        }

        if (Schema::hasColumn('clusters', 'center_point')) {
            DB::statement("ALTER TABLE clusters DROP COLUMN center_point");
        }

        if (Schema::hasColumn('photos', 'location')) {
            DB::statement("ALTER TABLE photos DROP COLUMN location");
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

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            try {
                DB::statement("DROP INDEX $index ON $table");
            } catch (\Exception $e) {
                // Ignore errors
            }
        }
    }

    private function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    private function warn(string $message): void
    {
        echo "\033[33m" . $message . "\033[0m" . PHP_EOL;
    }
};
