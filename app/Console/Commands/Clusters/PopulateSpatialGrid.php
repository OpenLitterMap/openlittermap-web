<?php

namespace App\Console\Commands\Clusters;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateSpatialGrid extends Command
{
    protected $signature = 'spatial:populate-grid';
    protected $description = 'Populate the spatial tile grid table';

    public function handle(): int
    {
        $existing = DB::selectOne("SELECT COUNT(*) as cnt FROM spatial_tile_grid")->cnt;

        if ($existing > 0) {
            if (!$this->confirm("Spatial grid already has $existing tiles. Repopulate?", false)) {
                return 0;
            }

            DB::statement("TRUNCATE TABLE spatial_tile_grid");
        }

        $this->info("Populating spatial tile grid (1,036,800 tiles)...");

        $bar = $this->output->createProgressBar(720);
        $bar->start();

        $batchSize = 10000;
        $values = [];
        $totalInserted = 0;

        for ($latIdx = 0; $latIdx < 720; $latIdx++) {
            for ($lonIdx = 0; $lonIdx < 1440; $lonIdx++) {
                $latMin = -90 + $latIdx * 0.25;
                $latMax = $latMin + 0.25;
                $lonMin = -180 + $lonIdx * 0.25;
                $lonMax = $lonMin + 0.25;
                $tileKey = $latIdx * 10000 + $lonIdx;

                // Create WKT strings for the geometry
                $bounds = sprintf(
                    "POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))",
                    $lonMin, $latMin,
                    $lonMax, $latMin,
                    $lonMax, $latMax,
                    $lonMin, $latMax,
                    $lonMin, $latMin
                );

                $center = sprintf(
                    "POINT(%f %f)",
                    ($lonMin + $lonMax) / 2,
                    ($latMin + $latMax) / 2
                );

                $values[] = sprintf(
                    "(%d, ST_GeomFromText('%s'), ST_GeomFromText('%s'))",
                    $tileKey,
                    $bounds,
                    $center
                );

                if (count($values) >= $batchSize) {
                    $this->insertBatch($values);
                    $totalInserted += count($values);
                    $values = [];
                }
            }

            $bar->advance();
        }

        // Insert remaining values
        if (!empty($values)) {
            $this->insertBatch($values);
            $totalInserted += count($values);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Inserted $totalInserted tiles");

        // Try to create spatial indexes
        $this->info("Creating spatial indexes...");

        try {
            DB::statement("ALTER TABLE spatial_tile_grid MODIFY bounds POLYGON NOT NULL");
            DB::statement("ALTER TABLE spatial_tile_grid MODIFY center POINT NOT NULL");
            DB::statement("CREATE SPATIAL INDEX idx_tile_bounds ON spatial_tile_grid(bounds)");
            DB::statement("CREATE SPATIAL INDEX idx_tile_center ON spatial_tile_grid(center)");
            $this->info("✓ Spatial indexes created");
        } catch (\Exception $e) {
            $this->warn("Could not create spatial indexes: " . $e->getMessage());
            $this->info("Creating regular indexes as fallback...");

            try {
                DB::statement("CREATE INDEX idx_tile_bounds_regular ON spatial_tile_grid(bounds(32))");
                DB::statement("CREATE INDEX idx_tile_center_regular ON spatial_tile_grid(center(25))");
                $this->info("✓ Regular indexes created");
            } catch (\Exception $e2) {
                $this->error("Failed to create indexes: " . $e2->getMessage());
            }
        }

        return 0;
    }

    private function insertBatch(array $values): void
    {
        $sql = "INSERT INTO spatial_tile_grid (tile_key, bounds, center) VALUES " .
            implode(',', $values);
        DB::statement($sql);
    }
}
