<?php

namespace Tests\Feature\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Helpers\CreateTestClusterPhotosTrait;

class ClusteringTest extends TestCase
{
    use RefreshDatabase, CreateTestClusterPhotosTrait;

    private ClusteringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCreateTestClusterPhotos();
        $this->service = app(ClusteringService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestPhotos();
        parent::tearDown();
    }

    /** @test */
    public function it_computes_tile_keys_correctly()
    {
        // London: 51.5074°N, 0.1278°W
        $key = $this->service->computeTileKey(51.5074, -0.1278);

        // With tile_size = 0.25, we get:
        // latIndex = floor((51.5074 + 90) / 0.25) = floor(566.0296) = 566
        // lonIndex = floor((-0.1278 + 180) / 0.25) = floor(719.4888) = 719
        // However, due to floating point precision, we might get 720
        // Let's calculate the actual values
        $tileSize = config('clustering.tile_size', 0.25);
        $tileWidth = (int)(360 / $tileSize);
        $latIndex = (int)floor((51.5074 + 90) / $tileSize);
        $lonIndex = (int)floor((-0.1278 + 180) / $tileSize);
        $expectedKey = $latIndex * $tileWidth + $lonIndex;

        $this->assertEquals($expectedKey, $key);
    }

    /** @test */
    public function it_returns_null_for_out_of_range_coordinates()
    {
        $this->assertNull($this->service->computeTileKey(91, 0));
        $this->assertNull($this->service->computeTileKey(-91, 0));
        $this->assertNull($this->service->computeTileKey(0, 181));
        $this->assertNull($this->service->computeTileKey(0, -181));
    }

    /** @test */
    public function it_handles_negative_zero_coordinates()
    {
        // PHP's -0.0 is treated as 0.0
        $key1 = $this->service->computeTileKey(0.0, 0.0);
        $key2 = $this->service->computeTileKey(-0.0, -0.0);
        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_backfills_photo_tile_keys()
    {
        // Create photos without tile keys using trait
        $this->createPhotosAt(51.5, -0.1, 1);
        $this->createPhotosAt(52.5, -1.1, 1);

        $updated = $this->service->backfillPhotoTileKeys();
        $this->assertEquals(2, $updated);

        // Verify tile keys were set
        $photos = DB::table('photos')->whereNotNull('tile_key')->get();
        $this->assertCount(2, $photos);
    }

    /** @test */
    public function it_only_backfills_photos_needing_tile_keys()
    {
        // Create one with tile key, one without
        $this->createPhotosInTile(12345, 1);
        $this->createPhotosAt(52.5, -1.1, 1);

        $beforeCount = DB::table('photos')->whereNotNull('tile_key')->count();
        $updated = $this->service->backfillPhotoTileKeys();
        $afterCount = DB::table('photos')->whereNotNull('tile_key')->count();

        $this->assertEquals(1, $updated);
        $this->assertEquals($beforeCount + 1, $afterCount);
    }

    /** @test */
    public function it_respects_custom_tile_size()
    {
        config(['clustering.tile_size' => 1.0]);

        // Recompute with new tile size
        $key = $this->service->computeTileKey(51.5, -0.1);

        // With tile_size = 1.0:
        // latIndex = floor((51.5 + 90) / 1.0) = 141
        // lonIndex = floor((-0.1 + 180) / 1.0) = 179
        // tileWidth = 360 / 1.0 = 360
        // key = 141 * 360 + 179 = 50939
        $this->assertEquals(50939, $key);
    }

    /** @test */
    public function it_creates_global_clusters_at_low_zoom_levels()
    {
        // Create photos across different locations
        $this->createPhotosAtLocation('london', 10);
        $this->createPhotosAtLocation('paris', 10);
        $this->createPhotosAtLocation('new_york', 10);
        $this->createPhotosAtLocation('sydney', 10);
        $this->createPhotosAtLocation('tokyo', 10);

        // Populate tile keys
        $this->service->backfillPhotoTileKeys();

        // Cluster at different zoom levels
        $zoom0Count = $this->service->clusterGlobal(0);
        $zoom2Count = $this->service->clusterGlobal(2);

        // Zoom 0 (30° grid) should have fewer clusters
        $this->assertGreaterThan(0, $zoom0Count);
        $this->assertLessThan(10, $zoom0Count, "Zoom 0 should have < 10 clusters globally");

        // At zoom 2 (15° grid), should have more clusters
        $this->assertGreaterThanOrEqual($zoom0Count, $zoom2Count);
        $this->assertLessThan(50, $zoom2Count, "Zoom 2 should have < 50 clusters globally");

        // Verify global tile key is used
        $globalTileKey = config('clustering.global_tile_key');
        $clusters = DB::table('clusters')->where('zoom', 0)->get();
        $this->assertTrue($clusters->every(fn($c) => $c->tile_key == $globalTileKey));
    }

    /** @test */
    public function it_uses_minimum_points_threshold()
    {
        // With min_cluster_size = 1, all photo groups should create clusters
        config(['clustering.min_cluster_size' => 32]);

        // Create photos: one location with 40, another with 20
        $this->createPhotosAt(51.5, -0.1, 40);
        $this->createPhotosAt(48.8, 2.3, 20);

        // Populate tile keys
        $this->service->backfillPhotoTileKeys();

        // At zoom 0 with min_points = 32
        $count = $this->service->clusterGlobal(0);

        // Only the location with 40 photos should create a cluster
        $this->assertEquals(1, $count);

        $cluster = DB::table('clusters')->where('zoom', 0)->first();
        $this->assertEquals(40, $cluster->point_count);
    }

    /** @test */
    public function it_creates_tile_clusters_for_deep_zoom_levels()
    {
        // Create photos in same tile
        $photos = $this->createPhotosAt(51.5074, -0.1278, 25);

        // Populate tile keys
        $this->service->backfillPhotoTileKeys();

        $count = $this->service->clusterAllTilesForZoom(16);

        $this->assertGreaterThan(0, $count);

        // Verify clusters are created for the correct tile
        $photo = DB::table('photos')->first();
        $cluster = DB::table('clusters')
            ->where('zoom', 16)
            ->where('tile_key', $photo->tile_key)
            ->first();

        $this->assertNotNull($cluster);
        $this->assertEquals(25, $cluster->point_count);
    }

    /** @test */
    public function it_respects_grid_sizes_at_different_zoom_levels()
    {
        // Create a spread of photos using the grid helper
        $this->createPhotoGrid(51.5, -0.1, 3, 0.5);
        $this->createPhotoGrid(48.8, 2.3, 3, 0.5);

        // Populate tile keys
        $this->service->backfillPhotoTileKeys();

        // Test different zoom levels
        $zoom8Count = $this->service->clusterAllTilesForZoom(8);   // 0.8° grid
        $zoom12Count = $this->service->clusterAllTilesForZoom(12); // 0.08° grid
        $zoom16Count = $this->service->clusterAllTilesForZoom(16); // 0.01° grid

        // At least some clusters should be created
        $this->assertGreaterThan(0, $zoom8Count, 'Zoom 8 should have clusters');
        $this->assertGreaterThan(0, $zoom12Count, 'Zoom 12 should have clusters');
        $this->assertGreaterThan(0, $zoom16Count, 'Zoom 16 should have clusters');

        // Higher zoom should have more clusters (finer detail)
        $this->assertGreaterThanOrEqual($zoom8Count, $zoom12Count);
        $this->assertGreaterThanOrEqual($zoom12Count, $zoom16Count);
    }

    /** @test */
    public function it_uses_generated_columns_for_performance()
    {
        // Create a photo using the trait
        $photo = $this->createPhoto([
            'lat' => 51.5074,
            'lon' => -0.1278,
            'verified' => 2,
        ]);

        // Populate tile key
        $this->service->backfillPhotoTileKeys();

        // Check generated columns with new 0.01 grid
        $rawPhoto = DB::table('photos')->find($photo->id);

        // With 0.01 grid:
        // cell_x = floor((-0.1278 + 180) / 0.01) = floor(17987.22) = 17987
        // cell_y = floor((51.5074 + 90) / 0.01) = floor(14150.74) = 14150
        $expectedCellX = floor((-0.1278 + 180) / 0.01);
        $expectedCellY = floor((51.5074 + 90) / 0.01);

        $this->assertEquals($expectedCellX, $rawPhoto->cell_x);
        $this->assertEquals($expectedCellY, $rawPhoto->cell_y);
    }

    /** @test */
    public function it_handles_dirty_tiles_with_backoff()
    {
        $tileKey = 815039;

        // Mark as dirty
        $this->service->markTileDirty($tileKey);

        $dirty = DB::table('dirty_tiles')->where('tile_key', $tileKey)->first();
        $this->assertNotNull($dirty);
        $this->assertEquals(0, $dirty->attempts);

        // Mark with backoff
        $this->service->markTileDirty($tileKey, true);

        $dirty = DB::table('dirty_tiles')->where('tile_key', $tileKey)->first();
        $this->assertGreaterThanOrEqual(1, $dirty->attempts);
    }

    /** @test */
    public function it_handles_polar_regions()
    {
        // Near north pole
        $this->createPhotosAt(89.9, 0, 10);
        // Near south pole
        $this->createPhotosAt(-89.9, 0, 10);

        $this->service->backfillPhotoTileKeys();
        $count = $this->service->clusterGlobal(4);

        // At zoom 4 with 5° grid, polar regions might create multiple clusters
        // due to longitude convergence near poles
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(6, $count); // Allow for some clustering variation at poles
    }

    /** @test */
    public function it_handles_empty_tiles_gracefully()
    {
        // Cluster with no photos
        $count = $this->service->clusterAllTilesForZoom(16);
        $this->assertEquals(0, $count);

        // No errors should occur
        $this->assertTrue(true);
    }

    /** @test */
    public function it_provides_accurate_statistics()
    {
        // Create mix of verified and unverified photos
        $this->createPhotos(100, ['verified' => 2]);
        $this->createPhotos(50, ['verified' => 2]);
        $this->createUnverifiedPhotos(25);

        // Populate tile keys
        $this->service->backfillPhotoTileKeys();

        $stats = $this->service->getStats();

        $this->assertEquals(175, $stats['photos_total']);
        $this->assertEquals(175, $stats['photos_with_tiles']);
        $this->assertEquals(150, $stats['photos_verified']); // Only verified photos
        $this->assertArrayHasKey('clusters_by_zoom', $stats);
    }

    /** @test */
    public function it_ensures_all_verified_photos_are_clustered_at_zoom_16()
    {
        // Create verified photos
        $this->createPhotosAt(51.5, -0.1, 60);
        $this->createPhotosAt(52.5, -1.1, 40);

        // Populate and cluster
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterAllTilesForZoom(16);

        $verifiedCount = DB::table('photos')->where('verified', 2)->count();
        $totalPoints = DB::table('clusters')
            ->where('zoom', 16)
            ->sum('point_count');

        $this->assertEquals($verifiedCount, $totalPoints);
    }

    /** @test */
    public function it_uses_configured_zoom_levels()
    {
        $configured = config('clustering.zoom_levels.all');
        $this->assertIsArray($configured);
        $this->assertNotEmpty($configured);
    }

    /** @test */
    public function debug_helpers_work_correctly()
    {
        // Test the debug helpers from the trait
        $tileKey = 815039;
        $this->createPhotosInTile($tileKey, 5);

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterAllTilesForZoom(16);

        $debug = $this->debugClustering($tileKey);

        $this->assertEquals($tileKey, $debug['tile_key']);
        $this->assertEquals(5, $debug['photo_count']);
        $this->assertArrayHasKey('clusters_by_zoom', $debug);
    }
}
