<?php

namespace Tests\Feature\Clusters;

use App\Models\Cluster;
use App\Models\Users\User;
use App\Services\Clustering\ClusteringService;
use App\Services\Clustering\TileMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ClusteringConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected ClusteringService $clusterService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cluster::query()->truncate();
        $this->user = User::factory()->create();
        $this->clusterService = resolve(ClusteringService::class);
    }

    private function insertPhoto(float $lat, float $lon, $createdAt = null, int $verified = 2): int
    {
        return DB::table('photos')->insertGetId([
            'user_id'    => $this->user->id,
            'filename'   => 'test.jpg',
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
    public function it_handles_true_concurrent_access(): void
    {
        // Insert photos for testing
        for ($i = 0; $i < 20; $i++) {
            $this->insertPhoto(51.5 + rand(-100, 100) / 10000, -0.1 + rand(-100, 100) / 10000);
        }

        $tileKey = DB::table('photos')->value('tile_key');

        // Use process forking for true concurrency
        $results = [];
        $processes = [];

        // Start 3 concurrent processes
        for ($i = 0; $i < 3; $i++) {
            $processes[] = Process::start(sprintf(
                'php artisan tinker --execute="
                    \$service = app(\App\Services\Clustering\ClusteringService::class);
                    \$result = \$service->rebuildTile(%d);
                    echo json_encode(\$result);
                "',
                $tileKey
            ));
        }

        // Wait for all processes and collect results
        foreach ($processes as $i => $process) {
            $output = $process->wait()->output();
            $results[$i] = json_decode(trim($output), true);
        }

        // All results should be identical (idempotent)
        $this->assertEquals($results[0]['clusters'], $results[1]['clusters']);
        $this->assertEquals($results[1]['clusters'], $results[2]['clusters']);
        $this->assertEquals($results[0]['photos'], $results[1]['photos']);

        // Verify no duplicate clusters exist
        $duplicates = DB::select("
            SELECT tile_key, zoom, cell_x, cell_y, year, COUNT(*) as cnt
            FROM clusters
            WHERE tile_key = ?
            GROUP BY tile_key, zoom, cell_x, cell_y, year
            HAVING cnt > 1
        ", [$tileKey]);

        $this->assertEmpty($duplicates, 'No duplicate clusters should exist after concurrent runs');
    }

    /** @test */
    public function it_handles_lock_contention_gracefully(): void
    {
        $this->insertPhoto(52.5200, 13.4050); // Berlin
        $tileKey = DB::table('photos')->value('tile_key');

        // Acquire lock in main process
        $lockName = "tile_{$tileKey}_year_all";
        $lockResult = DB::selectOne('SELECT GET_LOCK(?, 60) AS l', [$lockName]);
        $this->assertEquals(1, $lockResult->l);

        try {
            // Try to rebuild in another connection (should timeout)
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Could not obtain lock for tile $tileKey");

            // Use a new database connection to simulate another process
            DB::connection('mysql')->transaction(function() use ($tileKey) {
                $service = new ClusteringService();
                $service->rebuildTile($tileKey);
            });
        } finally {
            // Clean up: release lock
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /** @test */
    public function it_recovers_from_failure_mid_clustering(): void
    {
        // Insert photos
        for ($i = 0; $i < 50; $i++) {
            $this->insertPhoto(48.8566 + rand(-100, 100) / 10000, 2.3522 + rand(-100, 100) / 10000);
        }

        $tileKey = DB::table('photos')->value('tile_key');

        // Create initial clusters
        $this->clusterService->rebuildTile($tileKey);
        $initialClusters = DB::table('clusters')->where('tile_key', $tileKey)->get();
        $initialCount = $initialClusters->count();

        // Mock a service that fails partway through
        $failingService = new class extends ClusteringService {
            private int $callCount = 0;

            public function insertClusters(int $tileKey, int $zoom, ?int $year, bool $allowSingletons, array $tiles): int
            {
                $this->callCount++;
                // Fail on the 5th zoom level
                if ($this->callCount === 5) {
                    throw new \RuntimeException('Simulated database error');
                }
                return parent::insertClusters($tileKey, $zoom, $year, $allowSingletons, $tiles);
            }
        };

        // Attempt rebuild with failing service
        try {
            $failingService->rebuildTile($tileKey);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated database error', $e->getMessage());
        }

        // Verify clusters are unchanged (transaction rolled back)
        $afterFailure = DB::table('clusters')->where('tile_key', $tileKey)->get();
        $this->assertEquals($initialCount, $afterFailure->count());

        // Verify lock was released by checking we can acquire it
        $lockName = "tile_{$tileKey}_year_all";
        $lockResult = DB::selectOne('SELECT GET_LOCK(?, 1) AS l', [$lockName]);
        $this->assertEquals(1, $lockResult->l, 'Lock should be released after failure');
        DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }

    /** @test */
    public function it_handles_photo_deletion_and_cluster_removal(): void
    {
        // Create a cluster with multiple photos
        $photoIds = [];
        for ($i = 0; $i < 5; $i++) {
            $photoIds[] = $this->insertPhoto(51.5074, -0.1278); // London
        }

        $tileKey = DB::table('photos')->value('tile_key');

        // Build initial clusters
        $result1 = $this->clusterService->rebuildTile($tileKey);
        $this->assertEquals(5, $result1['photos']);
        $this->assertGreaterThan(0, $result1['clusters']);

        // Delete 3 photos
        DB::table('photos')->whereIn('id', array_slice($photoIds, 0, 3))->delete();

        // Rebuild and verify cluster shrinks
        $result2 = $this->clusterService->rebuildTile($tileKey);
        $this->assertEquals(2, $result2['photos']);
        $this->assertGreaterThan(0, $result2['clusters']);

        // Delete all remaining photos
        DB::table('photos')->whereIn('id', array_slice($photoIds, 3))->delete();

        // Rebuild and verify no clusters remain
        $result3 = $this->clusterService->rebuildTile($tileKey);
        $this->assertEquals(0, $result3['photos']);
        $this->assertEquals(0, $result3['clusters']);

        // Verify database is clean
        $remainingClusters = DB::table('clusters')->where('tile_key', $tileKey)->count();
        $this->assertEquals(0, $remainingClusters);
    }

    /** @test */
    public function it_handles_photos_becoming_unverified(): void
    {
        // Create verified photos
        $photoIds = [];
        for ($i = 0; $i < 10; $i++) {
            $photoIds[] = $this->insertPhoto(40.7128, -74.0060); // New York
        }

        $tileKey = DB::table('photos')->value('tile_key');

        // Build initial clusters
        $result1 = $this->clusterService->rebuildTile($tileKey);
        $this->assertGreaterThanOrEqual(10, $result1['photos']);

        // Un-verify half the photos
        DB::table('photos')
            ->whereIn('id', array_slice($photoIds, 0, 5))
            ->update(['verified' => 1]);

        // Rebuild and verify clusters only include verified photos
        $result2 = $this->clusterService->rebuildTile($tileKey);
        $this->assertGreaterThanOrEqual(5, $result2['photos']);
        $this->assertLessThan($result1['photos'], $result2['photos']);

        // Verify cluster point counts decreased
        $totalPoints = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->sum('point_count');
        $this->assertGreaterThanOrEqual(5, $totalPoints);
        $this->assertLessThan(10, $totalPoints);
    }

    /** @test */
    public function it_maintains_data_integrity_under_rapid_updates(): void
    {
        $lat = 35.6762;
        $lon = 139.6503; // Tokyo
        $tileKey = TileMath::getTileKey($lat, $lon);

        // Simulate rapid photo uploads and clustering
        for ($round = 0; $round < 5; $round++) {
            // Add photos
            for ($i = 0; $i < 10; $i++) {
                $this->insertPhoto($lat + rand(-100, 100) / 10000, $lon + rand(-100, 100) / 10000);
            }

            // Rebuild clusters
            $result = $this->clusterService->rebuildTile($tileKey);

            // Verify integrity
            $actualPhotos = DB::table('photos')
                ->where('tile_key', $tileKey)
                ->where('verified', 2)
                ->count();

            $clusterPointSum = DB::table('clusters')
                ->where('tile_key', $tileKey)
                ->where('year', 0)
                ->groupBy('zoom')
                ->selectRaw('zoom, SUM(point_count) as total')
                ->get();

            // Each zoom level should have same total points
            foreach ($clusterPointSum as $zoomTotal) {
                $this->assertLessThanOrEqual(
                    $result['photos'],
                    $zoomTotal->total,
                    "Zoom {$zoomTotal->zoom} has more points than photos!"
                );
            }
        }
    }

    /** @test */
    public function it_handles_lock_timeout_correctly(): void
    {
        $this->insertPhoto(51.5, -0.1);
        $tileKey = DB::table('photos')->value('tile_key');

        // Set a very short lock timeout
        config(['clustering.lock_timeout' => 1]);

        // Hold lock in one connection
        $lockName = "tile_{$tileKey}_year_all";
        DB::connection()->getPdo()->exec("SELECT GET_LOCK('{$lockName}', 60)");

        // Try to acquire in another connection (should fail quickly)
        $startTime = microtime(true);

        try {
            // Create new service instance to pick up config change
            $service = new ClusteringService();
            $service->rebuildTile($tileKey);
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            $duration = microtime(true) - $startTime;

            // Should fail within ~3 seconds (3 attempts with 1s timeout each)
            $this->assertLessThan(5, $duration);
            $this->assertStringContainsString('Could not obtain lock', $e->getMessage());
        } finally {
            DB::connection()->getPdo()->exec("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }
}
