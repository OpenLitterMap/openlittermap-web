<?php

namespace Tests\Feature\Clusters;

use Tests\Helpers\CreateTestClusterPhotosTrait;
use Tests\TestCase;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Clustering\ClusteringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClusteringTest extends TestCase
{
    use RefreshDatabase, CreateTestClusterPhotosTrait;

    private ClusteringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the static test user to avoid foreign key issues
        $this->setUpCreateTestClusterPhotos();

        $this->service = app(ClusteringService::class);

        // Ensure generated columns exist for tests
        if (!Schema::hasColumn('photos', 'cell_x')) {
            DB::statement('
                ALTER TABLE photos
                ADD COLUMN cell_x INT GENERATED ALWAYS AS (FLOOR((lon + 180) / 0.05)) STORED AFTER lon,
                ADD COLUMN cell_y INT GENERATED ALWAYS AS (FLOOR((lat + 90) / 0.05)) STORED AFTER cell_x
            ');
        }
    }

    protected function tearDown(): void
    {
        self::$testUser = null;
        parent::tearDown();
    }

    /* -----------------------------------------------------------------
     |  Tile key calculations
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_computes_tile_keys_correctly(): void
    {
        // [lat, lon, expected tile_key] – 0.25° grid
        $cases = [
            [-90,      -180,             0],  // origin
            [  0,         0,       519_120],  // equator/prime meridian
            [ 89.9999, 179.9999, 1_036_799],  // near max
            [ 51.5074,  -0.1278,   815_759],  // London
            [ 40.7128, -74.0060,   752_103],  // New York
            [-33.8688, 151.2093,   323_884],  // Sydney
        ];

        foreach ($cases as [$lat, $lon, $expected]) {
            $this->assertSame(
                $expected,
                $this->service->computeTileKey($lat, $lon),
                "Failed for ($lat, $lon)"
            );
        }
    }

    /** @test */
    public function it_returns_null_for_out_of_range_coordinates(): void
    {
        $this->assertNull($this->service->computeTileKey( 91,   0));
        $this->assertNull($this->service->computeTileKey(-91,   0));
        $this->assertNull($this->service->computeTileKey(  0, 181));
        $this->assertNull($this->service->computeTileKey(  0,-181));
    }

    /** @test */
    public function it_handles_negative_zero_coordinates(): void
    {
        // Negative zero should be treated same as positive zero
        $positiveZero = $this->service->computeTileKey(0.0, 0.0);
        $negativeZero = $this->service->computeTileKey(-0.0, -0.0);

        $this->assertSame($positiveZero, $negativeZero);
        $this->assertSame(519_120, $negativeZero);
    }

    /* -----------------------------------------------------------------
     |  Tile key backfilling
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_backfills_photo_tile_keys(): void
    {
        // Use helper to create photos without tile keys
        $photos = $this->createPhotosAt(51.5074, -0.1278, 5, ['tile_key' => null]);

        // Verify we have photos without tile keys
        $this->assertEquals(5, Photo::whereNull('tile_key')->count());

        // Backfill
        $updated = $this->service->backfillPhotoTileKeys();

        $this->assertSame(5, $updated);
        $this->assertPhotosHaveTileKeys($photos, 815_759);
    }

    /** @test */
    public function it_only_backfills_photos_needing_tile_keys(): void
    {
        // Photos with tile keys
        $this->createPhotosInTile(815_759, 3);

        // Photos without tile keys
        $photosNeedingKeys = $this->createPhotos(2, ['tile_key' => null]);

        $updated = $this->service->backfillPhotoTileKeys();

        $this->assertSame(2, $updated);
        $this->assertSame(5, Photo::whereNotNull('tile_key')->count());
    }

    /** @test */
    public function it_respects_custom_tile_size(): void
    {
        config(['clustering.tile_size' => 0.5]);

        $svc = new ClusteringService();
        $this->assertSame(129_960, $svc->computeTileKey(0, 0));
    }

    /* -----------------------------------------------------------------
     |  Global Clustering (Batch Processing)
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_creates_global_clusters_at_low_zoom_levels(): void
    {
        // Create photos across multiple locations
        $this->createPhotosAt(51.5074, -0.1278, 50);   // London
        $this->createPhotosAt(40.7128, -74.0060, 50);  // New York
        $this->createPhotosAt(48.8566, 2.3522, 50);    // Paris
        $this->createPhotosAt(35.6762, 139.6503, 50);  // Tokyo
        $this->createPhotosAt(-33.8688, 151.2093, 50); // Sydney

        // Run global clustering for zoom 0 and 2
        $zoom0Count = $this->service->clusterGlobal(0);
        $zoom2Count = $this->service->clusterGlobal(2);

        // At zoom 0 (90° grid), should have very few clusters
        $this->assertGreaterThan(0, $zoom0Count);
        $this->assertLessThan(10, $zoom0Count, "Zoom 0 should have < 10 clusters globally");

        // At zoom 2 (45° grid), should have more but still < 50
        $this->assertGreaterThan($zoom0Count, $zoom2Count);
        $this->assertLessThan(50, $zoom2Count, "Zoom 2 should have < 50 clusters globally");

        // Verify global tile key is used
        $globalTileKey = config('clustering.global_tile_key');
        $this->assertDatabaseHas('clusters', [
            'tile_key' => $globalTileKey,
            'zoom' => 0
        ]);
    }

    /** @test */
    public function it_uses_minimum_points_threshold(): void
    {
        // Create sparse photos that won't meet threshold
        $this->createPhotosAt(0, 0, 1);      // Only 1 photo
        $this->createPhotosAt(10, 10, 2);    // Only 2 photos
        $this->createPhotosAt(20, 20, 40);   // 40 photos - should cluster

        // At zoom 0, min_points should be 32
        $count = $this->service->clusterGlobal(0);

        // Only the location with 40 photos should create a cluster
        $this->assertEquals(1, $count);

        $cluster = DB::table('clusters')->where('zoom', 0)->first();
        $this->assertEquals(40, $cluster->point_count);
    }

    /* -----------------------------------------------------------------
     |  Tile-Based Batch Clustering
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_creates_tile_clusters_for_deep_zoom_levels(): void
    {
        // Create photos in specific tiles
        $londonTile = 815_759;
        $parisTile = 815_495;

        $this->createPhotosInTile($londonTile, 100);
        $this->createPhotosInTile($parisTile, 100);

        // Run batch clustering for zoom 8
        $count = $this->service->clusterAllTilesForZoom(8);

        $this->assertGreaterThan(0, $count);

        // Verify clusters were created for both tiles
        $londonClusters = DB::table('clusters')
            ->where('tile_key', $londonTile)
            ->where('zoom', 8)
            ->count();

        $parisClusters = DB::table('clusters')
            ->where('tile_key', $parisTile)
            ->where('zoom', 8)
            ->count();

        $this->assertGreaterThan(0, $londonClusters);
        $this->assertGreaterThan(0, $parisClusters);
    }

    /** @test */
    public function it_respects_grid_sizes_at_different_zoom_levels(): void
    {
        // Create a dense grid of photos
        $centerLat = 51.5074;
        $centerLon = -0.1278;

        // Create 100 photos in a small area
        for ($i = 0; $i < 100; $i++) {
            $this->createPhotosAt(
                $centerLat + ($i % 10) * 0.01,
                $centerLon + floor($i / 10) * 0.01,
                1
            );
        }

        // Cluster at different zoom levels
        $zoom8Count = $this->service->clusterAllTilesForZoom(8);   // 5.625° grid
        $zoom12Count = $this->service->clusterAllTilesForZoom(12); // 1.40625° grid
        $zoom16Count = $this->service->clusterAllTilesForZoom(16); // 0.05° grid

        // At least some clusters should be created
        $this->assertGreaterThan(0, $zoom8Count, 'Zoom 8 should have clusters');
        $this->assertGreaterThan(0, $zoom12Count, 'Zoom 12 should have clusters');
        $this->assertGreaterThan(0, $zoom16Count, 'Zoom 16 should have clusters');

        // Higher zoom should have more clusters (or equal if all in one cluster)
        $this->assertGreaterThanOrEqual($zoom8Count, $zoom12Count);
        $this->assertGreaterThanOrEqual($zoom12Count, $zoom16Count);

        // Verify grid sizes are set correctly
        $zoom8Cluster = DB::table('clusters')->where('zoom', 8)->first();
        $zoom16Cluster = DB::table('clusters')->where('zoom', 16)->first();

        if ($zoom8Cluster) {
            $this->assertEqualsWithDelta(5.625, (float) $zoom8Cluster->grid_size, 0.001);
        }
        if ($zoom16Cluster) {
            $this->assertEqualsWithDelta(0.05, (float) $zoom16Cluster->grid_size, 0.001);
        }
    }

    /* -----------------------------------------------------------------
     |  Performance & Optimization Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_uses_generated_columns_for_performance(): void
    {
        // Create a photo
        $photo = $this->createPhotosAt(51.5074, -0.1278, 1)->first();

        // Verify generated columns exist
        $rawPhoto = DB::table('photos')->where('id', $photo->id)->first();

        $this->assertNotNull($rawPhoto->cell_x);
        $this->assertNotNull($rawPhoto->cell_y);

        // Cell values should match expected calculation
        $expectedCellX = floor((-0.1278 + 180) / 0.05);
        $expectedCellY = floor((51.5074 + 90) / 0.05);

        $this->assertEquals($expectedCellX, $rawPhoto->cell_x);
        $this->assertEquals($expectedCellY, $rawPhoto->cell_y);
    }

    /** @test */
    public function it_handles_dirty_tiles_with_backoff(): void
    {
        $tileKey = 815_759;

        // Mark tile dirty without backoff
        $this->service->markTileDirty($tileKey);

        $dirty = DB::table('dirty_tiles')->where('tile_key', $tileKey)->first();
        $this->assertEquals(0, $dirty->attempts);

        // Mark again with backoff
        $this->service->markTileDirty($tileKey, true);

        $dirty = DB::table('dirty_tiles')->where('tile_key', $tileKey)->first();
        $this->assertGreaterThan(0, $dirty->attempts);
    }

    /* -----------------------------------------------------------------
     |  API Tests (Updated for Batch Processing)
     * ---------------------------------------------------------------- */

    /** @test */
    public function api_returns_clusters_at_requested_zoom(): void
    {
        // Create photos globally
        $this->createPhotosAt(51.5074, -0.1278, 50);
        $this->createPhotosAt(40.7128, -74.0060, 50);

        // Run batch clustering
        $this->service->clusterGlobal(8);
        $this->service->clusterAllTilesForZoom(8);

        $response = $this->getJson('/api/clusters?zoom=8');

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'properties' => ['count'],
                        'geometry' => ['type', 'coordinates']
                    ]
                ]
            ]);

        $features = $response->json('features');
        $this->assertNotEmpty($features);
    }

    /** @test */
    public function api_filters_by_bounding_box(): void
    {
        // Create clusters in different locations
        $this->createPhotosAt(51.5074, -0.1278, 50);  // London
        $this->createPhotosAt(40.7128, -74.0060, 50); // New York

        // Run global clustering
        $this->service->clusterGlobal(2);

        // Request only Europe area
        $response = $this->getJson('/api/clusters?zoom=2&bbox[left]=-10&bbox[right]=10&bbox[bottom]=45&bbox[top]=60');

        $response->assertOk();
        $features = $response->json('features');

        // Should have features only in Europe
        $this->assertNotEmpty($features);

        foreach ($features as $feature) {
            $lon = $feature['geometry']['coordinates'][0];
            $lat = $feature['geometry']['coordinates'][1];

            $this->assertGreaterThanOrEqual(-10, $lon);
            $this->assertLessThanOrEqual(10, $lon);
            $this->assertGreaterThanOrEqual(45, $lat);
            $this->assertLessThanOrEqual(60, $lat);
        }
    }

    /** @test */
    public function api_returns_304_for_matching_etag(): void
    {
        $this->createPhotosAt(51.5074, -0.1278, 50);
        $this->service->clusterGlobal(8);

        // First request
        $response1 = $this->getJson('/api/clusters?zoom=8');
        $etag = $response1->headers->get('ETag');

        // Second request with If-None-Match
        $response2 = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/clusters?zoom=8');

        $response2->assertStatus(304);
    }

    /** @test */
    public function api_handles_missing_zoom_gracefully(): void
    {
        $this->createPhotosAt(51.5074, -0.1278, 50);
        $this->service->clusterGlobal(0);

        $response = $this->getJson('/api/clusters');
        $response->assertOk();

        // Should default to zoom 0
        $this->assertEquals('0', $response->headers->get('X-Cluster-Zoom'));
    }

    /** @test */
    public function api_handles_bbox_crossing_dateline(): void
    {
        // Create photos on both sides of dateline
        $this->createPhotosAt(0, 179.6, 50);
        $this->createPhotosAt(0, -179.6, 50);

        $this->service->clusterGlobal(2);

        // Query across dateline
        $response = $this->getJson('/api/clusters?zoom=2&bbox[left]=170&bbox[right]=-170&bbox[bottom]=-10&bbox[top]=10');

        $response->assertOk();
        $features = $response->json('features');

        $this->assertNotEmpty($features, 'Should return features across dateline');
    }

    /** @test */
    public function api_returns_422_for_invalid_inputs(): void
    {
        $invalidInputs = [
            'zoom=abc' => 'non-numeric zoom',
            'zoom=-1' => 'negative zoom',
            'zoom=25' => 'zoom too high',
            'lat=91' => 'latitude out of range',
            'lon=181' => 'longitude out of range',
        ];

        foreach ($invalidInputs as $query => $description) {
            $response = $this->getJson("/api/clusters?$query");
            $response->assertStatus(422, "Failed for: $description");
        }
    }

    /** @test */
    public function etag_changes_after_cluster_update(): void
    {
        $this->createPhotosAt(51.5074, -0.1278, 50);
        $this->service->clusterGlobal(8);

        $etag1 = $this->getJson('/api/clusters?zoom=8')->headers->get('ETag');

        // Add more photos and re-cluster
        $this->createPhotosAt(51.5074, -0.1278, 50);
        $this->service->clusterGlobal(8);

        // Clear caches
        \Illuminate\Support\Facades\Cache::flush();

        $etag2 = $this->getJson('/api/clusters?zoom=8')->headers->get('ETag');

        $this->assertNotEquals($etag1, $etag2, 'ETag should change after cluster update');
    }

    /* -----------------------------------------------------------------
     |  Statistics & Verification Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_provides_accurate_statistics(): void
    {
        // Create known quantities
        $this->createPhotosAt(51.5074, -0.1278, 100, ['verified' => 2]);
        $this->createPhotosAt(40.7128, -74.0060, 50, ['verified' => 2]);
        $this->createPhotosAt(48.8566, 2.3522, 25, ['verified' => 0]); // Unverified

        // Run clustering
        foreach ([0, 2, 4, 6] as $zoom) {
            $this->service->clusterGlobal($zoom);
        }

        $stats = $this->service->getStats();

        $this->assertEquals(175, $stats['photos_total']);
        $this->assertEquals(175, $stats['photos_with_tiles']);
        $this->assertEquals(150, $stats['photos_verified']); // Only verified photos
        $this->assertArrayHasKey('clusters_by_zoom', $stats);
    }

    /** @test */
    public function it_ensures_all_verified_photos_are_clustered_at_zoom_16(): void
    {
        // Create photos
        $verifiedCount = 100;
        $this->createPhotosAt(51.5074, -0.1278, $verifiedCount, ['verified' => 2]);

        // Cluster at zoom 16
        $this->service->clusterAllTilesForZoom(16);

        // Sum of all point_counts at zoom 16 should equal verified photos
        $totalPoints = DB::table('clusters')
            ->where('zoom', 16)
            ->sum('point_count');

        $this->assertEquals($verifiedCount, $totalPoints);
    }

    /* -----------------------------------------------------------------
     |  Configuration Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_uses_configured_zoom_levels(): void
    {
        $configuredLevels = config('clustering.zoom_levels.all');

        $this->assertIsArray($configuredLevels);
        $this->assertContains(0, $configuredLevels);
        $this->assertContains(16, $configuredLevels);

        // Verify global vs tile zoom separation
        $globalZooms = config('clustering.zoom_levels.global');
        $tileZooms = config('clustering.zoom_levels.tile');

        $this->assertContains(0, $globalZooms);
        $this->assertContains(2, $globalZooms);
        $this->assertNotContains(16, $globalZooms);

        $this->assertContains(16, $tileZooms);
        $this->assertNotContains(0, $tileZooms);
    }

    /** @test */
    public function api_provides_zoom_levels_endpoint(): void
    {
        $response = $this->getJson('/api/clusters/zoom-levels');

        $response->assertOk()
            ->assertJsonStructure([
                'zoom_levels',
                'global_zooms',
                'tile_zooms'
            ]);

        $data = $response->json();
        $this->assertIsArray($data['zoom_levels']);
        $this->assertNotEmpty($data['zoom_levels']);
    }

    /* -----------------------------------------------------------------
     |  Edge Case Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_handles_polar_regions(): void
    {
        // Create photos near poles
        $this->createPhotosAt(85.0, 0, 50);   // Near North Pole
        $this->createPhotosAt(-85.0, 0, 50);  // Near South Pole

        $count = $this->service->clusterGlobal(0);

        // Should create clusters even at extreme latitudes
        $this->assertGreaterThan(0, $count);

        $polarClusters = DB::table('clusters')
            ->where('zoom', 0)
            ->where(function ($q) {
                $q->where('lat', '>', 80)
                    ->orWhere('lat', '<', -80);
            })
            ->count();

        $this->assertGreaterThan(0, $polarClusters);
    }

    /** @test */
    public function it_handles_empty_tiles_gracefully(): void
    {
        // Create photos in only one tile
        $this->createPhotosInTile(815_759, 50);

        // Run clustering for a zoom level
        $count = $this->service->clusterAllTilesForZoom(12);

        // Should only create clusters where photos exist
        $this->assertGreaterThan(0, $count);

        // No clusters should exist for empty tiles
        $emptyTileClusters = DB::table('clusters')
            ->where('zoom', 12)
            ->where('tile_key', '!=', 815_759)
            ->where('tile_key', '!=', config('clustering.global_tile_key'))
            ->count();

        $this->assertEquals(0, $emptyTileClusters);
    }
}
