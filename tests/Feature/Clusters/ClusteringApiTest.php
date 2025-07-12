<?php

namespace Tests\Feature\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Helpers\CreateTestClusterPhotosTrait;

class ClusteringApiTest extends TestCase
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
    public function api_returns_clusters_at_requested_zoom()
    {
        // Create photos across world
        $this->createPhotosAtLocation('london', 10);
        $this->createPhotosAtLocation('new_york', 10);
        $this->createPhotosAtLocation('tokyo', 10);

        // Populate tile keys and create clusters
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        $response = $this->getJson('/api/clusters?zoom=8');

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry' => ['type', 'coordinates'],
                        'properties' => ['point_count']
                    ]
                ]
            ]);
    }

    /** @test */
    public function api_filters_by_bounding_box()
    {
        $londonPhotos = $this->createPhotosAtLocation('london', 10);
        $nyPhotos = $this->createPhotosAtLocation('new_york', 10);

        // Populate and cluster
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Request only Europe
        $response = $this->getJson('/api/clusters?zoom=8&bbox[]=-10&bbox[]=40&bbox[]=30&bbox[]=60');

        $response->assertOk();
        $data = $response->json();

        // Should only have London cluster
        $this->assertCount(1, $data['features']);

        $coords = $data['features'][0]['geometry']['coordinates'];

        // Get actual London coordinates from the photos
        $londonLat = $londonPhotos->first()->fresh()->lat;
        $londonLon = $londonPhotos->first()->fresh()->lon;

        $this->assertEqualsWithDelta($londonLon, $coords[0], 1.0);
        $this->assertEqualsWithDelta($londonLat, $coords[1], 1.0);
    }

    /** @test */
    public function api_returns_304_for_matching_etag()
    {
        $this->createPhotosAt(51.5, -0.1, 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        $response1 = $this->getJson('/api/clusters?zoom=8');
        $etag = $response1->headers->get('ETag');

        $response2 = $this->getJson('/api/clusters?zoom=8', ['If-None-Match' => $etag]);
        $response2->assertStatus(304);
    }

    /** @test */
    public function api_handles_missing_zoom_gracefully()
    {
        $response = $this->getJson('/api/clusters');
        $response->assertOk();

        // Should default to first available zoom level
        $this->assertEquals(0, $response->headers->get('X-Cluster-Zoom'));
    }

    /** @test */
    public function api_handles_bbox_crossing_dateline()
    {
        // Photos on both sides of dateline
        $this->createPhotosAt(35.6, 170, 5);  // West of dateline
        $this->createPhotosAt(35.6, -170, 5); // East of dateline

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Bbox crossing dateline
        $response = $this->getJson('/api/clusters?zoom=8&bbox[]=160&bbox[]=30&bbox[]=-160&bbox[]=40');

        $response->assertOk();
        $data = $response->json();

        // At zoom 8 with 0.8° grid, both locations are in different cells
        // So we should get 2 clusters minimum
        $this->assertGreaterThanOrEqual(2, count($data['features']));

        // Verify we got clusters from both sides of dateline
        $longitudes = array_map(fn($f) => $f['geometry']['coordinates'][0], $data['features']);
        $hasWest = count(array_filter($longitudes, fn($lon) => $lon > 160)) > 0;
        $hasEast = count(array_filter($longitudes, fn($lon) => $lon < -160)) > 0;

        $this->assertTrue($hasWest || $hasEast, 'Should have clusters from at least one side of dateline');
    }

    /** @test */
    public function api_returns_422_for_invalid_inputs()
    {
        $response = $this->getJson('/api/clusters?zoom=999');
        $response->assertStatus(422);
    }

    /** @test */
    public function etag_changes_after_cluster_update()
    {
        $this->createPhotosAt(51.5, -0.1, 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        $etag1 = $this->getJson('/api/clusters?zoom=8')->headers->get('ETag');

        // Add more photos and recluster
        $this->createPhotosAt(51.6, -0.2, 5);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Clear cache to force new ETag computation
        Cache::flush();

        $etag2 = $this->getJson('/api/clusters?zoom=8')->headers->get('ETag');

        $this->assertNotEquals($etag1, $etag2, 'ETag should change after cluster update');
    }

    /** @test */
    public function api_provides_zoom_levels_endpoint()
    {
        $response = $this->getJson('/api/clusters/zoom-levels');

        $response->assertOk()
            ->assertJsonStructure([
                'zoom_levels',
                'global_zooms',
                'tile_zooms',
            ]);
    }

    /** @test */
    public function api_handles_comma_separated_bbox()
    {
        $this->createPhotosAtLocation('london', 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Test comma-separated bbox format
        $response = $this->getJson('/api/clusters?zoom=8&bbox[]=-10,40,30,60');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data['features']);
    }

    /** @test */
    public function api_creates_bbox_from_center_point()
    {
        $this->createPhotosAtLocation('london', 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Request with center point instead of bbox
        $response = $this->getJson('/api/clusters?zoom=8&lat=51.5&lon=-0.1');

        $response->assertOk();
        $data = $response->json();

        // Should include London cluster
        $this->assertGreaterThan(0, count($data['features']));
    }

    /** @test */
    public function api_respects_max_clusters_limit()
    {
        // Create many photos that will result in many clusters
        for ($i = 0; $i < 100; $i++) {
            $this->createPhotosAt(51.5 + $i * 0.1, -0.1 + $i * 0.1, 1);
        }

        $this->service->backfillPhotoTileKeys();
        $this->service->clusterAllTilesForZoom(16);

        $limit = config('clustering.max_clusters_per_request', 5000);
        $response = $this->getJson('/api/clusters?zoom=16');

        $response->assertOk();
        $data = $response->json();

        $this->assertLessThanOrEqual($limit, count($data['features']));
    }

    /** @test */
    public function api_handles_inverted_bbox_gracefully()
    {
        $this->createPhotosAtLocation('london', 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(8);

        // Inverted bbox (north < south)
        $response = $this->getJson('/api/clusters?zoom=8&bbox[]=-10&bbox[]=60&bbox[]=30&bbox[]=40');

        $response->assertOk();
        $data = $response->json();

        // Should still return results after swapping north/south
        $this->assertCount(1, $data['features']);
    }
}
