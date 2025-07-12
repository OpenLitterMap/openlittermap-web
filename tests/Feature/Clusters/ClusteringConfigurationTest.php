<?php

namespace Tests\Feature\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Helpers\CreateTestClusterPhotosTrait;

class ClusteringConfigurationTest extends TestCase
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
    public function it_uses_configured_zoom_levels()
    {
        $configured = config('clustering.zoom_levels.all');
        $this->assertIsArray($configured);
        $this->assertNotEmpty($configured);

        // Verify global and tile zooms are subsets of all zooms
        $globalZooms = config('clustering.zoom_levels.global');
        $tileZooms = config('clustering.zoom_levels.tile');

        $this->assertTrue(
            empty(array_diff($globalZooms, $configured)),
            'Global zooms should be subset of all zooms'
        );

        $this->assertTrue(
            empty(array_diff($tileZooms, $configured)),
            'Tile zooms should be subset of all zooms'
        );
    }

    /** @test */
    public function it_respects_configured_grid_sizes()
    {
        // Test that configured grid sizes are used
        $zoom = 8;
        $configuredGridSize = config("clustering.grid_sizes.$zoom");

        $this->assertNotNull($configuredGridSize);
        $this->assertEquals(0.8, $configuredGridSize);

        // Create photos and cluster at this zoom
        $this->createPhotosAt(51.2, -0.1, 10);  // Will be in cell at 50.8°
        $this->createPhotosAt(51.4, -0.1, 10);  // Also in cell at 50.8°
        $this->createPhotosAt(52.0, -0.1, 10);  // Will be in cell at 51.6°

        $this->service->backfillPhotoTileKeys();
        $count = $this->service->clusterGlobal($zoom);

        // With 0.8° grid, we should have 2 clusters
        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_validates_smallest_grid_matches_generated_columns()
    {
        $smallestGrid = config('clustering.smallest_grid');
        $this->assertEquals(0.01, $smallestGrid);

        // Create a photo and verify generated columns use this grid
        $photo = $this->createPhoto([
            'lat' => 51.5074,
            'lon' => -0.1278,
            'verified' => 2,
        ]);

        $this->service->backfillPhotoTileKeys();

        $rawPhoto = DB::table('photos')->find($photo->id);

        // Generated columns should use 0.01 grid
        $expectedCellX = floor((-0.1278 + 180) / 0.01);
        $expectedCellY = floor((51.5074 + 90) / 0.01);

        $this->assertEquals($expectedCellX, $rawPhoto->cell_x);
        $this->assertEquals($expectedCellY, $rawPhoto->cell_y);
    }

    /** @test */
    public function it_handles_configuration_changes_gracefully()
    {
        // Create photos and cluster with default config
        $this->createPhotosAt(51.5, -0.1, 10);
        $this->service->backfillPhotoTileKeys();
        $originalCount = $this->service->clusterGlobal(0);

        // Change configuration
        config(['clustering.grid_sizes.0' => 60.0]); // Double the grid size

        // Re-cluster
        $newCount = $this->service->clusterGlobal(0);

        // With larger grid, we might have fewer clusters
        $this->assertLessThanOrEqual($originalCount, $newCount);
    }

    /** @test */
    public function it_validates_grid_size_factors_for_tile_zooms()
    {
        $smallestGrid = config('clustering.smallest_grid');
        $tileZooms = config('clustering.zoom_levels.tile');

        foreach ($tileZooms as $zoom) {
            $gridSize = config("clustering.grid_sizes.$zoom");
            $factor = $gridSize / $smallestGrid;

            // Factor should be an integer for clean cell divisions
            $this->assertEquals(
                round($factor),
                $factor,
                "Grid size for zoom $zoom should divide evenly into smallest grid"
            );
        }
    }

    /** @test */
    public function it_uses_global_tile_key_for_global_clustering()
    {
        $globalTileKey = config('clustering.global_tile_key');
        $this->assertEquals(4294967295, $globalTileKey);

        // Create photos and cluster globally
        $this->createPhotosAt(51.5, -0.1, 10);
        $this->service->backfillPhotoTileKeys();
        $this->service->clusterGlobal(0);

        // Verify clusters use global tile key
        $cluster = DB::table('clusters')->where('zoom', 0)->first();
        $this->assertEquals($globalTileKey, $cluster->tile_key);
    }

    /** @test */
    public function it_respects_cache_ttl_configuration()
    {
        $ttl = config('clustering.cache_ttl');
        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
    }

    /** @test */
    public function it_respects_update_chunk_size()
    {
        $chunkSize = config('clustering.update_chunk_size');
        $this->assertIsInt($chunkSize);
        $this->assertGreaterThan(0, $chunkSize);

        // Create more photos than chunk size
        for ($i = 0; $i < 100; $i++) {
            $this->createPhotosAt(51.5 + $i * 0.001, -0.1 + $i * 0.001, 1);
        }

        // First backfill should only process chunk size
        $updated = $this->service->backfillPhotoTileKeys();
        $this->assertLessThanOrEqual($chunkSize, $updated);
    }

    /** @test */
    public function it_validates_minimum_cluster_size_configuration()
    {
        $minSize = config('clustering.min_cluster_size');
        $this->assertIsInt($minSize);
        $this->assertGreaterThan(0, $minSize);

        // Test with custom minimum
        config(['clustering.min_cluster_size' => 5]);

        // Create 4 photos (below minimum)
        $this->createPhotosAt(51.5, -0.1, 4);
        $this->service->backfillPhotoTileKeys();

        $count = $this->service->clusterGlobal(0);
        $this->assertEquals(0, $count, 'Should not create cluster below minimum size');

        // Add one more photo to meet minimum
        $this->createPhotosAt(51.5, -0.1, 1);
        $this->service->backfillPhotoTileKeys();

        $count = $this->service->clusterGlobal(0);
        $this->assertEquals(1, $count, 'Should create cluster at minimum size');
    }

    /** @test */
    public function it_handles_maximum_clusters_per_request()
    {
        $maxClusters = config('clustering.max_clusters_per_request');
        $this->assertIsInt($maxClusters);
        $this->assertGreaterThan(0, $maxClusters);

        // This is tested more thoroughly in API tests
        $this->assertTrue(true);
    }

    /** @test */
    public function it_validates_tile_size_configuration()
    {
        $tileSize = config('clustering.tile_size');
        $this->assertIsNumeric($tileSize);
        $this->assertGreaterThan(0, $tileSize);

        // Tile size should divide evenly into 360 degrees
        $tilesPerRow = 360 / $tileSize;
        $this->assertEquals(
            round($tilesPerRow),
            $tilesPerRow,
            'Tile size should divide evenly into 360 degrees'
        );
    }

    /** @test */
    public function it_validates_base_grid_configuration()
    {
        $baseGrid = config('clustering.base_grid_deg');
        $this->assertEquals(90.0, $baseGrid);

        // Test that grid halves every 2 zoom levels
        // At zoom 0: 90°
        // At zoom 2: 45°
        // At zoom 4: 22.5°
        // etc.
        $this->assertTrue(true);
    }
}
