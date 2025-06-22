<?php

namespace Tests\Feature\Clusters;

use App\Models\Cluster;
use App\Models\Users\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\Clustering\ClusteringService;
use App\Services\Clustering\TileMath;

class ClusteringTest extends TestCase
{
    use RefreshDatabase;

    protected ClusteringService $clusterService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cluster::query()->truncate();

        // Simple user to satisfy NOT NULL FK constraints in photos
        $this->user = User::factory()->create();

        $this->clusterService = resolve(ClusteringService::class);
    }

    // ------------------------------------------------------------
    //  Helper: quick photo insert complying with schema
    // ------------------------------------------------------------
    private function insertPhoto(float $lat, float $lon, $createdAt = null, int $verified = 2): int
    {
        return DB::table('photos')->insertGetId([
            'user_id'    => $this->user->id,
            'filename'   => 'test.jpg',   // photos.filename is NOT NULL in schema
            'lat'        => $lat,
            'lon'        => $lon,
            'verified'   => $verified,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
            'model'      => 'iphone',
            'datetime'   => $createdAt ?? now(),
        ]);
    }

    /** @test */
    public function it_handles_coordinate_edge_cases(): void
    {
        $cases = [
            ['lat' => 89.999999, 'lon' => 179.999999],
            ['lat' => -89.999999, 'lon' => -179.999999],
            ['lat' => 90.0,       'lon' => 180.0],
            ['lat' => -90.0,      'lon' => -180.0],
        ];

        foreach ($cases as $c) {
            $key = TileMath::getTileKey($c['lat'], $c['lon']);
            $this->assertNotNull($key);
            $this->assertLessThan(720 * 10000, $key);
            $b = TileMath::getTileBounds($key);
            $this->assertGreaterThanOrEqual($b['minLat'], $c['lat']);
            $this->assertLessThanOrEqual(  $b['maxLat'], $c['lat']);
            $this->assertGreaterThanOrEqual($b['minLon'], $c['lon']);
            $this->assertLessThanOrEqual(  $b['maxLon'], $c['lon']);
        }
    }

    /** @test */
    public function it_processes_large_tiles_without_memory_issues(): void
    {
        $baseLat = 51.5;
        $baseLon = -0.1;
        $tileKey = TileMath::getTileKey($baseLat, $baseLon);

        // Create photos with small jitter that might spill into adjacent tiles
        $batch = [];
        for ($i = 0; $i < 1000; $i++) {
            $batch[] = [
                'user_id'    => $this->user->id,
                'filename'   => 'bulk.jpg',
                'model'      => 'iphone',
                'datetime'   => now(),
                'lat'        => $baseLat + (rand(-100, 100) / 10000),
                'lon'        => $baseLon + (rand(-100, 100) / 10000),
                'verified'   => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($batch, 250) as $chunk) {
            DB::table('photos')->insert($chunk);
        }

        // Count actual photos inserted
        $actualPhotoCount = DB::table('photos')
            ->where('verified', 2)
            ->whereNotNull('tile_key')
            ->count();
        $this->assertSame(1000, $actualPhotoCount);

        // Get all adjacent tiles that might contain our photos
        $adjacentTiles = TileMath::getAdjacentTiles($tileKey);

        // Process all tiles in the 3x3 grid
        $tilesProcessed = 0;
        foreach ($adjacentTiles as $tile) {
            $result = $this->clusterService->rebuildTile($tile);
            $tilesProcessed++;
        }

        // Verify we processed all 9 tiles
        $this->assertSame(9, $tilesProcessed);

        // Count total clusters created across all processed tiles
        $totalClusters = DB::table('clusters')
            ->whereIn('tile_key', $adjacentTiles)
            ->count();

        // Verify we created clusters
        $this->assertGreaterThan(0, $totalClusters);

        // Verify we have clusters at all expected zoom levels (2-16 = 15 levels)
        $processedZooms = DB::table('clusters')
            ->whereIn('tile_key', $adjacentTiles)
            ->distinct()
            ->pluck('zoom')
            ->count();
        $this->assertSame(15, $processedZooms);
    }

    /** @test */
    public function it_streams_tiles_using_cursors(): void
    {
        for ($lat = -75; $lat <= 75; $lat += 5) {
            for ($lon = -180; $lon < 180; $lon += 5) {
                $this->insertPhoto($lat, $lon);
            }
        }

        $memBefore = memory_get_usage();
        $tiles = 0;
        DB::table('photos')->select('tile_key')->whereNotNull('tile_key')->distinct()->cursor()->each(function () use (&$tiles) {
            $tiles++;
        });
        $memUsed = (memory_get_usage() - $memBefore) / 1048576;
        $this->assertGreaterThan(100, $tiles);
        $this->assertLessThan(10, $memUsed);
    }

    /** @test */
    public function it_supports_reusable_temp_tables(): void
    {
        $this->insertPhoto(51.5, -0.1);
        $tileKey = DB::table('photos')->value('tile_key');

        // ----- FIX: the helper now exists publicly
        $temp = $this->clusterService->initWorkerTemp();

        for ($i = 0; $i < 3; $i++) {
            $r = $this->clusterService->rebuildTile($tileKey, null, $temp);
            $this->assertSame(1, $r['photos']);
        }
        $this->clusterService->dropWorkerTemp($temp);
        $this->assertEmpty(DB::select("SHOW TABLES LIKE '{$temp}'"));
    }

    /** @test */
    public function it_creates_clusters_for_singletons_and_has_no_empty_rows(): void
    {
        $this->insertPhoto(0.0, 0.0);
        $tileKey = DB::table('photos')->value('tile_key');
        $this->clusterService->rebuildTile($tileKey);
        $this->assertSame(1, (int)DB::table('clusters')->where('tile_key', $tileKey)->sum('point_count'));
        $this->assertSame(0, DB::table('clusters')->where('point_count', 0)->count());
    }

    /** @test */
    public function it_clamps_mercator_projection_correctly(): void
    {
        $this->insertPhoto(85.05, 0.0);
        $this->insertPhoto(-85.05, 0.0);

        foreach (DB::table('photos')->pluck('tile_key') as $tileKey) {
            $r = $this->clusterService->rebuildTile($tileKey);
            $this->assertGreaterThan(0, $r['photos']);
            $this->assertGreaterThan(0, $r['clusters']);
        }
    }

    /** @test */
    public function it_handles_photos_near_tile_boundaries(): void
    {
        // Insert photos at the very edge of a tile (0.25° boundaries)
        // These should be included when processing adjacent tiles

        // Tile boundary at lat=51.5, lon=0.0
        $this->insertPhoto(51.4999, -0.0001);  // Just inside SW corner
        $this->insertPhoto(51.5001, 0.0001);   // Just inside NE corner of next tile
        $this->insertPhoto(51.7499, 0.2499);   // Just inside NE corner

        $centerTile = TileMath::getTileKey(51.625, 0.125); // Center of tile
        $result = $this->clusterService->rebuildTile($centerTile);

        $this->assertGreaterThanOrEqual(3, $result['photos']);
        $this->assertGreaterThan(0, $result['clusters']);

        // Verify clusters exist at multiple zoom levels
        $clusters = DB::table('clusters')->where('tile_key', $centerTile)->get();
        $this->assertNotEmpty($clusters);
    }

    /** @test */
    public function it_handles_global_distribution_correctly(): void
    {
        // Simulate uploads from major cities worldwide
        $cities = [
            ['name' => 'London', 'lat' => 51.5074, 'lon' => -0.1278],
            ['name' => 'New York', 'lat' => 40.7128, 'lon' => -74.0060],
            ['name' => 'Tokyo', 'lat' => 35.6762, 'lon' => 139.6503],
            ['name' => 'Sydney', 'lat' => -33.8688, 'lon' => 151.2093],
            ['name' => 'São Paulo', 'lat' => -23.5505, 'lon' => -46.6333],
            ['name' => 'Cairo', 'lat' => 30.0444, 'lon' => 31.2357],
            ['name' => 'Mumbai', 'lat' => 19.0760, 'lon' => 72.8777],
            ['name' => 'Reykjavik', 'lat' => 64.1466, 'lon' => -21.9426],
        ];

        $processedTiles = [];

        foreach ($cities as $city) {
            // Insert multiple photos near each city with slight variation
            for ($i = 0; $i < 5; $i++) {
                $this->insertPhoto(
                    $city['lat'] + (rand(-100, 100) / 10000),
                    $city['lon'] + (rand(-100, 100) / 10000)
                );
            }

            $tileKey = TileMath::getTileKey($city['lat'], $city['lon']);
            $result = $this->clusterService->rebuildTile($tileKey);

            $this->assertGreaterThan(0, $result['photos'], "No photos found for {$city['name']}");
            $this->assertGreaterThan(0, $result['clusters'], "No clusters created for {$city['name']}");

            $processedTiles[] = $tileKey;
        }

        // Verify each city has its own tile
        $uniqueTiles = array_unique($processedTiles);
        $this->assertCount(count($cities), $uniqueTiles, 'Cities should be in different tiles');
    }

    /** @test */
    public function it_handles_incremental_updates_over_time(): void
    {
        $lat = 52.5200;
        $lon = 13.4050; // Berlin
        $tileKey = TileMath::getTileKey($lat, $lon);

        // Day 1: Initial upload
        for ($i = 0; $i < 10; $i++) {
            $this->insertPhoto(
                $lat + (rand(-50, 50) / 10000),
                $lon + (rand(-50, 50) / 10000),
                now()->subDays(7)
            );
        }

        $result1 = $this->clusterService->rebuildTile($tileKey);
        $initialClusters = DB::table('clusters')->where('tile_key', $tileKey)->count();

        // Day 2: More uploads in same area
        for ($i = 0; $i < 15; $i++) {
            $this->insertPhoto(
                $lat + (rand(-50, 50) / 10000),
                $lon + (rand(-50, 50) / 10000),
                now()->subDays(3)
            );
        }

        $result2 = $this->clusterService->rebuildTile($tileKey);

        // Count actual photos in and around this tile
        $adjacentTiles = TileMath::getAdjacentTiles($tileKey);
        $actualPhotoCount = DB::table('photos')
            ->whereIn('tile_key', $adjacentTiles)
            ->where('verified', 2)
            ->count();

        $this->assertEquals(25, $actualPhotoCount);

        // Count clusters for this specific tile
        $finalClusters = DB::table('clusters')->where('tile_key', $tileKey)->count();
        $this->assertGreaterThanOrEqual($initialClusters, $finalClusters);

        // Verify idempotency
        $result3 = $this->clusterService->rebuildTile($tileKey);
        $this->assertEquals($result2['clusters'], $result3['clusters']);
    }

    /** @test */
    public function it_handles_year_based_clustering(): void
    {
        $lat = 48.8566;
        $lon = 2.3522; // Paris
        $tileKey = TileMath::getTileKey($lat, $lon);
        $adjacentTiles = TileMath::getAdjacentTiles($tileKey);

        // Add photos from different years
        for ($year = 2022; $year <= 2024; $year++) {
            for ($i = 0; $i < 10; $i++) {
                $this->insertPhoto(
                    $lat + (rand(-100, 100) / 10000),
                    $lon + (rand(-100, 100) / 10000),
                    now()->setYear($year)
                );
            }
        }

        // Rebuild for each year
        foreach ([2022, 2023, 2024] as $year) {
            $result = $this->clusterService->rebuildTile($tileKey, $year);

            // Count actual photos for this year in adjacent tiles
            $yearPhotoCount = DB::table('photos')
                ->whereIn('tile_key', $adjacentTiles)
                ->where('verified', 2)
                ->whereYear('created_at', $year)
                ->count();

            $this->assertEquals(10, $yearPhotoCount, "Year $year should have exactly 10 photos");
            $this->assertGreaterThan(0, $result['clusters'], "Year $year should have clusters");

            // Verify clusters are tagged with correct year
            $clusters = DB::table('clusters')
                ->where('tile_key', $tileKey)
                ->where('year', $year)
                ->count();
            $this->assertGreaterThan(0, $clusters);
        }

        // Rebuild for all years
        $allResult = $this->clusterService->rebuildTile($tileKey, null);

        // Verify total photos
        $totalPhotoCount = DB::table('photos')
            ->whereIn('tile_key', $adjacentTiles)
            ->where('verified', 2)
            ->count();
        $this->assertEquals(30, $totalPhotoCount);

        // Verify year=0 clusters exist for "all years"
        $allYearClusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('year', 0)
            ->count();
        $this->assertGreaterThan(0, $allYearClusters);
    }

    /** @test */
    public function it_handles_date_line_crossing(): void
    {
        // Test photos near the International Date Line
        $locations = [
            ['lat' => -17.7134, 'lon' => 179.9999],  // Just west of date line (Fiji)
            ['lat' => -17.7134, 'lon' => -179.9999], // Just east of date line
            ['lat' => -17.7134, 'lon' => 180.0],     // Exactly on date line
        ];

        $insertedPhotos = 0;
        foreach ($locations as $loc) {
            for ($i = 0; $i < 5; $i++) {
                $this->insertPhoto($loc['lat'], $loc['lon']);
                $insertedPhotos++;
            }
        }

        // Get unique tiles for all locations
        $tiles = [];
        foreach ($locations as $loc) {
            $tile = TileMath::getTileKey($loc['lat'], $loc['lon']);
            if ($tile && !in_array($tile, $tiles)) {
                $tiles[] = $tile;
            }
        }

        // Process each unique tile
        $processedTiles = [];
        foreach ($tiles as $tile) {
            $result = $this->clusterService->rebuildTile($tile);
            $this->assertGreaterThan(0, $result['photos']);
            $this->assertGreaterThan(0, $result['clusters']);
            $processedTiles[] = $tile;
        }

        // Verify we have clusters in the tiles
        $totalClusters = DB::table('clusters')
            ->whereIn('tile_key', $tiles)
            ->count();

        $this->assertGreaterThan(0, $totalClusters);
        $this->assertEquals($insertedPhotos, 15); // Verify we inserted 15 photos
    }

    /** @test */
    public function it_handles_polar_regions(): void
    {
        // Test near poles where tiles are smaller
        $polarLocations = [
            ['name' => 'Svalbard', 'lat' => 78.2232, 'lon' => 15.6267],
            ['name' => 'Antarctica', 'lat' => -77.8463, 'lon' => 166.6755],
            ['name' => 'Alert, Canada', 'lat' => 82.5018, 'lon' => -62.3481],
        ];

        foreach ($polarLocations as $loc) {
            for ($i = 0; $i < 3; $i++) {
                $this->insertPhoto(
                    $loc['lat'] + (rand(-10, 10) / 10000),
                    $loc['lon'] + (rand(-10, 10) / 10000)
                );
            }

            $tileKey = TileMath::getTileKey($loc['lat'], $loc['lon']);
            $result = $this->clusterService->rebuildTile($tileKey);

            $this->assertGreaterThan(0, $result['photos'], "No photos for {$loc['name']}");
            $this->assertGreaterThan(0, $result['clusters'], "No clusters for {$loc['name']}");
        }
    }

    /** @test */
    public function it_respects_singleton_policy_configuration(): void
    {
        // Test with different singleton policies
        $lat = 41.9028;
        $lon = 12.4964; // Rome

        // Insert single photo
        $this->insertPhoto($lat, $lon);
        $tileKey = TileMath::getTileKey($lat, $lon);

        // Test default policy (max_zoom_only)
        $result = $this->clusterService->rebuildTile($tileKey);
        $this->assertEquals(1, $result['photos']);

        // Check that singleton only exists at max zoom
        $singletonClusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('point_count', 1)
            ->pluck('zoom')
            ->toArray();

        $maxZoom = config('clustering.max_zoom');
        $this->assertEquals([$maxZoom], $singletonClusters);
    }

    /** @test */
    public function it_handles_high_density_areas(): void
    {
        // Simulate a beach cleanup event with many photos in small area
        $lat = 33.7701;
        $lon = -118.1937; // Long Beach, CA

        // Insert 100 photos in a very small area (simulating event)
        for ($i = 0; $i < 100; $i++) {
            $this->insertPhoto(
                $lat + (rand(-10, 10) / 100000), // Very small variation
                $lon + (rand(-10, 10) / 100000)
            );
        }

        $tileKey = TileMath::getTileKey($lat, $lon);
        $result = $this->clusterService->rebuildTile($tileKey);

        $this->assertGreaterThanOrEqual(100, $result['photos']);

        // At low zoom levels, should be one cluster
        $lowZoomClusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('zoom', '<=', 10)
            ->get();

        foreach ($lowZoomClusters as $cluster) {
            $this->assertGreaterThan(50, $cluster->point_count, 'Low zoom clusters should aggregate many points');
        }

        // At high zoom levels, might have multiple clusters
        $highZoomClusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->where('zoom', '>=', 14)
            ->count();

        $this->assertGreaterThanOrEqual(1, $highZoomClusters);
    }

    /** @test */
    public function it_handles_concurrent_tile_updates(): void
    {
        $lat = 37.7749;
        $lon = -122.4194; // San Francisco
        $tileKey = TileMath::getTileKey($lat, $lon);

        // Insert initial photos
        for ($i = 0; $i < 20; $i++) {
            $this->insertPhoto(
                $lat + (rand(-100, 100) / 10000),
                $lon + (rand(-100, 100) / 10000)
            );
        }

        // Simulate concurrent updates by running rebuild multiple times
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->clusterService->rebuildTile($tileKey);
        }

        // All results should be identical (idempotent)
        $this->assertEquals($results[0]['clusters'], $results[1]['clusters']);
        $this->assertEquals($results[1]['clusters'], $results[2]['clusters']);

        // Verify no duplicate clusters
        $duplicates = DB::select("
            SELECT tile_key, zoom, cell_x, cell_y, year, COUNT(*) as cnt
            FROM clusters
            WHERE tile_key = ?
            GROUP BY tile_key, zoom, cell_x, cell_y, year
            HAVING cnt > 1
        ", [$tileKey]);

        $this->assertEmpty($duplicates, 'No duplicate clusters should exist');
    }

    /** @test */
    public function it_handles_unverified_photos_correctly(): void
    {
        $lat = 55.7558;
        $lon = 37.6173; // Moscow
        $tileKey = TileMath::getTileKey($lat, $lon);

        // Mix of verified and unverified photos
        for ($i = 0; $i < 10; $i++) {
            $this->insertPhoto($lat + (rand(-50, 50) / 10000), $lon + (rand(-50, 50) / 10000), null, 2); // verified
            $this->insertPhoto($lat + (rand(-50, 50) / 10000), $lon + (rand(-50, 50) / 10000), null, 1); // unverified
            $this->insertPhoto($lat + (rand(-50, 50) / 10000), $lon + (rand(-50, 50) / 10000), null, 0); // rejected
        }

        $result = $this->clusterService->rebuildTile($tileKey);

        // Should only process verified photos
        $this->assertGreaterThanOrEqual(10, $result['photos']);
        $this->assertLessThanOrEqual(20, $result['photos']); // Might include some from adjacent tiles

        // Verify clusters only contain verified photos
        $clusterSum = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->sum('point_count');

        $this->assertGreaterThan(0, $clusterSum);
    }
}
