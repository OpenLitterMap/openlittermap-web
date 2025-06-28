<?php

namespace Tests\Feature\Clusters;

use Tests\Helpers\CreateTestClusterPhotos;
use Tests\TestCase;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Clustering\ClusteringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClusteringTest extends TestCase
{
    use RefreshDatabase, CreateTestClusterPhotos;

    private ClusteringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClusteringService::class);

        // Ensure consistent config for tests
        // IMPORTANT: grid_size column is DECIMAL(4,3) so max value is 9.999
        config([
            'clustering.tile_size' => 0.25,
            'clustering.zoom_levels' => [
                0  => ['grid' => 8.0,    'min_points' => 10], // Changed from 32.0
                2  => ['grid' => 8.0,    'min_points' =>  8],
                4  => ['grid' => 4.0,    'min_points' =>  6],
                6  => ['grid' => 2.0,    'min_points' =>  5],
                8  => ['grid' => 1.0,    'min_points' =>  3],
                10 => ['grid' => 0.5,    'min_points' =>  3],
                12 => ['grid' => 0.25,   'min_points' =>  2],
                14 => ['grid' => 0.10,   'min_points' =>  1],
                16 => ['grid' => 0.05,   'min_points' =>  1],
            ],
        ]);
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

    /* -----------------------------------------------------------------
     |  Clustering
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_creates_clusters_at_configured_zoom_levels(): void
    {
        $tileKey = 815_759; // London

        // Create photos using the helper
        $this->createPhotosInTile($tileKey, 15);

        $this->service->clusterTile($tileKey);

        // Check clusters were created
        $clusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->select('zoom', 'grid_size', 'point_count')
            ->get();

        $this->assertNotEmpty($clusters);

        $zoomLevels = $clusters->pluck('zoom')->unique()->sort()->values();
        $this->assertContains(8, $zoomLevels);  // Should have zoom 8
        $this->assertContains(12, $zoomLevels); // Should have zoom 12
    }

    /** @test */
    public function it_respects_minimum_points_per_zoom_level(): void
    {
        $tileKey = 815_759;

        // Create only 2 photos
        $this->createPhotosInTile($tileKey, 2);

        $this->service->clusterTile($tileKey);

        $clusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->pluck('zoom');

        // Should NOT have clusters at zoom levels requiring more than 2 points
        $this->assertNotContains(0, $clusters);  // min_points=10
        $this->assertNotContains(8, $clusters);  // min_points=3

        // Should have clusters at zoom 12+ (min_points=2 or 1)
        $this->assertContains(12, $clusters);
    }

    /** @test */
    public function it_handles_verified_status_changes(): void
    {
        // Use helper to create unverified photo
        $photo = $this->createPhoto(['verified' => 0, 'tile_key' => null]);

        $photo->verified = 2;
        $photo->save();

        $this->assertSame(815_759, $photo->fresh()->tile_key);
    }

    /** @test */
    public function it_marks_tiles_dirty(): void
    {
        $this->service->markTileDirty(100);
        $this->assertDatabaseHas('dirty_tiles', ['tile_key' => 100]);
    }

    /** @test */
    public function it_respects_custom_tile_size(): void
    {
        config(['clustering.tile_size' => 0.5]);

        $svc = new ClusteringService();
        $this->assertSame(129_960, $svc->computeTileKey(0, 0));
    }

    /* -----------------------------------------------------------------
     |  Complex clustering scenarios
     * ---------------------------------------------------------------- */

    /** @test */
    public function it_creates_clusters_at_multiple_locations(): void
    {
        // Get tile keys for each location
        $londonTile = $this->service->computeTileKey(51.5074, -0.1278);
        $parisTile = $this->service->computeTileKey(48.8566, 2.3522);
        $nyTile = $this->service->computeTileKey(40.7128, -74.0060);

        // Create photos with correct tile keys
        $this->createPhotosInTile($londonTile, 10);
        $this->createPhotosInTile($parisTile, 10);
        $this->createPhotosInTile($nyTile, 10);

        // Verify photos were created
        $this->assertEquals(10, Photo::where('tile_key', $londonTile)->count());
        $this->assertEquals(10, Photo::where('tile_key', $parisTile)->count());
        $this->assertEquals(10, Photo::where('tile_key', $nyTile)->count());

        // Cluster each tile
        $this->service->clusterTile($londonTile);
        $this->service->clusterTile($parisTile);
        $this->service->clusterTile($nyTile);

        // Debug if no clusters
        $allClusters = DB::table('clusters')->get();
        if ($allClusters->isEmpty()) {
            $this->fail('No clusters were created. Total photos: ' . Photo::count());
        }

        // Verify clusters were created
        $this->assertDatabaseHas('clusters', ['tile_key' => $londonTile]);
        $this->assertDatabaseHas('clusters', ['tile_key' => $parisTile]);
        $this->assertDatabaseHas('clusters', ['tile_key' => $nyTile]);
    }

    /** @test */
    public function it_creates_photos_across_multiple_tiles(): void
    {
        // Create photos in multiple tiles
        $photos = $this->createPhotosAcrossTiles([
            815_759 => 10,  // London tile
            815_760 => 5,   // Adjacent tile
            815_758 => 3,   // Another adjacent tile
        ]);

        $this->assertCount(18, $photos);

        // Verify distribution
        $this->assertEquals(10, Photo::where('tile_key', 815_759)->count());
        $this->assertEquals(5, Photo::where('tile_key', 815_760)->count());
        $this->assertEquals(3, Photo::where('tile_key', 815_758)->count());
    }

    /** @test */
    public function it_creates_a_grid_of_photos(): void
    {
        // Create a 3x3 grid centered on London
        $photos = $this->createPhotoGrid(51.5074, -0.1278, 3, 0.1);

        $this->assertCount(9, $photos);

        // All photos should be within expected bounds
        foreach ($photos as $photo) {
            $this->assertGreaterThanOrEqual(51.4074, $photo->lat);
            $this->assertLessThanOrEqual(51.6074, $photo->lat);
            $this->assertGreaterThanOrEqual(-0.2278, $photo->lon);
            $this->assertLessThanOrEqual(-0.0278, $photo->lon);
        }
    }

    /* -----------------------------------------------------------------
     |  API Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function api_returns_clusters_at_requested_zoom(): void
    {
        // Get tile key for London
        $tileKey = $this->service->computeTileKey(51.5074, -0.1278);

        // Create photos with correct tile key
        $this->createPhotosInTile($tileKey, 10);

        // Verify photos were created
        $this->assertEquals(10, Photo::where('tile_key', $tileKey)->count());

        // Cluster the tile
        $this->service->clusterTile($tileKey);

        // Check what clusters were created
        $clustersCreated = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->get();

        if ($clustersCreated->isEmpty()) {
            $this->fail('No clusters created for tile ' . $tileKey);
        }

        // Update grid_size to match expected values for zoom 8
        DB::table('clusters')
            ->where('zoom', 8)
            ->where('tile_key', $tileKey)
            ->update(['grid_size' => 1.0]);

        $response = $this->getJson('/api/clusters?zoom=8');

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'features',
            ]);

        $features = $response->json('features');

        // Debug if empty
        if (empty($features)) {
            Log::warning('No features returned', [
                'clusters_at_zoom_8' => DB::table('clusters')
                    ->where('zoom', 8)
                    ->where('grid_size', 1.0)
                    ->count(),
            ]);
        }

        $this->assertNotEmpty($features);
    }

    /** @test */
    public function api_filters_by_bounding_box(): void
    {
        // Create clusters in different locations
        $this->createAndClusterPhotos(51.5074, -0.1278, 5);  // London
        $this->createAndClusterPhotos(40.7128, -74.0060, 5); // New York

        // Update grid_size for zoom 8
        DB::table('clusters')
            ->where('zoom', 8)
            ->update(['grid_size' => 1.0]);

        // Request only London area
        $response = $this->getJson('/api/clusters?zoom=8&bbox[left]=-1&bbox[right]=1&bbox[bottom]=50&bbox[top]=52');

        $response->assertOk();
        $features = $response->json('features');

        // Should have some features
        $this->assertNotEmpty($features);

        // Verify they're in the London area
        foreach ($features as $feature) {
            $lon = $feature['geometry']['coordinates'][0];
            $lat = $feature['geometry']['coordinates'][1];

            $this->assertGreaterThanOrEqual(-1, $lon);
            $this->assertLessThanOrEqual(1, $lon);
            $this->assertGreaterThanOrEqual(50, $lat);
            $this->assertLessThanOrEqual(52, $lat);
        }
    }

    /** @test */
    public function api_returns_304_for_matching_etag(): void
    {
        $this->createAndClusterPhotos(51.5074, -0.1278, 5);

        // Update grid_size
        DB::table('clusters')
            ->where('zoom', 8)
            ->update(['grid_size' => 1.0]);

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
        $this->createAndClusterPhotos(51.5074, -0.1278, 5);

        // Update grid_size with valid value for zoom 0
        DB::table('clusters')
            ->where('zoom', 0)
            ->update(['grid_size' => 8.0]); // Use 8.0 instead of 32.0

        $response = $this->getJson('/api/clusters');

        $response->assertOk();
    }

    /* -----------------------------------------------------------------
     |  Diagnostic Tests
     * ---------------------------------------------------------------- */

    /** @test */
    public function diagnose_clustering_service(): void
    {
        // Create a simple test case
        $tileKey = 815_759; // London tile

        // Create photos with explicit tile_key
        $photos = $this->createPhotosInTile($tileKey, 5);

        $this->assertEquals(5, $photos->count(), 'Should have created 5 photos');

        // Verify photos have correct attributes
        foreach ($photos as $photo) {
            $this->assertEquals(2, $photo->verified, 'Photo should be verified');
            $this->assertEquals($tileKey, $photo->tile_key, 'Photo should have correct tile_key');
            $this->assertNotNull($photo->lat, 'Photo should have latitude');
            $this->assertNotNull($photo->lon, 'Photo should have longitude');
        }

        // Try clustering
        $this->service->clusterTile($tileKey);

        // Check what happened
        $debug = $this->debugClustering($tileKey);

        if ($debug['cluster_count'] === 0) {
            $this->fail(
                "No clusters created.\n" .
                "Debug info: " . json_encode($debug, JSON_PRETTY_PRINT)
            );
        }

        $this->assertGreaterThan(0, $debug['cluster_count']);
    }

    /* -----------------------------------------------------------------
     |  Helper Methods
     * ---------------------------------------------------------------- */

    /**
     * Create and cluster photos at a location
     */
    protected function createAndClusterPhotos(float $lat, float $lon, int $count): void
    {
        $tileKey = $this->service->computeTileKey($lat, $lon);

        // Clear any existing data
        DB::table('clusters')->where('tile_key', $tileKey)->delete();
        Photo::where('tile_key', $tileKey)->delete();

        // Create photos
        $this->createPhotosInTile($tileKey, $count);

        // Cluster
        $this->service->clusterTile($tileKey);

        // Verify
        $clusterCount = DB::table('clusters')->where('tile_key', $tileKey)->count();
        if ($clusterCount === 0) {
            throw new \Exception("No clusters created for tile $tileKey");
        }
    }
}
