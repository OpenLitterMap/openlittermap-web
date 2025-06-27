<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestClustering extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clusters:test
        {--lat= : Test latitude}
        {--lon= : Test longitude}
        {--sample : Use a sample photo from database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the clustering system with a specific location';

    /**
     * Execute the console command.
     */
    public function handle(ClusteringService $service): int
    {
        $this->info('🧪 Testing Clustering System');
        $this->newLine();

        // Get test coordinates
        if ($this->option('sample')) {
            $photo = DB::table('photos')
                ->whereNotNull('lat')
                ->whereNotNull('lon')
                ->where('verified', 2)
                ->first();

            if (!$photo) {
                $this->error('No verified photos with coordinates found!');
                return 1;
            }

            $lat = $photo->lat;
            $lon = $photo->lon;
            $this->info("Using sample photo: ID {$photo->id} at ($lat, $lon)");
        } else {
            $lat = $this->option('lat') ?? $this->ask('Enter latitude', '51.5074');
            $lon = $this->option('lon') ?? $this->ask('Enter longitude', '-0.1278');
            $lat = (float) $lat;
            $lon = (float) $lon;
        }

        // Test 1: Calculate tile key
        $this->info("\n1️⃣ Testing tile key calculation:");
        $latIdx = floor(($lat + 90) / 0.25);
        $lonIdx = floor(($lon + 180) / 0.25);
        $tileKey = $latIdx * 1440 + $lonIdx;

        $this->table(
            ['Calculation', 'Value'],
            [
                ['Latitude', $lat],
                ['Longitude', $lon],
                ['Lat Index', $latIdx],
                ['Lon Index', $lonIdx],
                ['Tile Key', $tileKey],
            ]
        );

        // Test 2: Get tile bounds
        $this->info("\n2️⃣ Testing tile bounds:");
        $bounds = $service->getTileBounds($tileKey);
        $this->table(
            ['Bound', 'Value'],
            [
                ['Min Latitude', $bounds['min_lat']],
                ['Max Latitude', $bounds['max_lat']],
                ['Min Longitude', $bounds['min_lon']],
                ['Max Longitude', $bounds['max_lon']],
                ['Width (degrees)', 0.25],
                ['Height (degrees)', 0.25],
            ]
        );

        // Test 3: Check if tile exists
        $this->info("\n3️⃣ Checking tile status:");
        $tileExists = DB::table('active_tiles')->where('tile_key', $tileKey)->first();

        if ($tileExists) {
            $this->info("✅ Tile exists with {$tileExists->photo_count} photos");
            $this->info("   Last updated: {$tileExists->last_updated}");
        } else {
            $this->warn("❌ Tile does not exist in active_tiles");
        }

        // Test 4: Count photos in tile
        $this->info("\n4️⃣ Counting photos in tile:");
        $photoCount = DB::table('photos')
            ->where('tile_key', $tileKey)
            ->where('verified', 2)
            ->count();

        $this->info("Found $photoCount verified photos in this tile");

        // Test 5: Show sample clusters
        $this->info("\n5️⃣ Sample clusters in this tile:");
        $clusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('year', 0)
            ->orderBy('zoom')
            ->limit(10)
            ->get();

        if ($clusters->isEmpty()) {
            $this->warn("No clusters found in this tile");
        } else {
            $data = $clusters->map(function ($cluster) {
                return [
                    $cluster->id,
                    $cluster->zoom,
                    $cluster->lat,
                    $cluster->lon,
                    $cluster->point_count,
                    "({$cluster->cell_x}, {$cluster->cell_y})"
                ];
            })->toArray();

            $this->table(
                ['ID', 'Zoom', 'Latitude', 'Longitude', 'Photos', 'Cell'],
                $data
            );
        }

        // Test 6: Test clustering for this tile
        if ($photoCount > 0) {
            $this->info("\n6️⃣ Testing cluster generation:");

            if ($this->confirm("Run clustering for tile $tileKey?")) {
                try {
                    $result = $service->clusterTile($tileKey);

                    $this->info("✅ Clustering successful!");
                    $this->table(
                        ['Metric', 'Value'],
                        [
                            ['Photos processed', $result['photos']],
                            ['Clusters created', $result['clusters']],
                        ]
                    );

                    if (!empty($result['zoom_clusters'])) {
                        $this->info("\nClusters by zoom level:");
                        foreach ($result['zoom_clusters'] as $zoom => $count) {
                            $this->info("  Zoom $zoom: $count clusters");
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Clustering failed: " . $e->getMessage());
                }
            }
        }

        // Test 7: Database integrity
        $this->info("\n7️⃣ Testing database integrity:");

        // Check for photos without tile_key
        $missingTileKey = DB::table('photos')
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->whereNull('tile_key')
            ->count();

        if ($missingTileKey > 0) {
            $this->warn("⚠️  Found $missingTileKey photos without tile_key");
        } else {
            $this->info("✅ All photos with coordinates have tile_key");
        }

        // Check for orphaned clusters
        $orphanedClusters = DB::selectOne("
            SELECT COUNT(*) as count
            FROM clusters c
            LEFT JOIN active_tiles t ON c.tile_key = t.tile_key
            WHERE t.tile_key IS NULL
        ")->count;

        if ($orphanedClusters > 0) {
            $this->warn("⚠️  Found $orphanedClusters orphaned clusters");
        } else {
            $this->info("✅ No orphaned clusters found");
        }

        $this->newLine();
        $this->info('✨ Testing complete!');

        return 0;
    }
}
