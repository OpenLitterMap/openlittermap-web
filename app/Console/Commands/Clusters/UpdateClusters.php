<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UpdateClusters extends Command
{
    protected $signature = 'clustering:update
        {--populate : Populate missing tile keys}
        {--all : Recluster all tiles}
        {--team= : Cluster a specific team by ID}
        {--all-teams : Cluster all teams with photos}
        {--stats : Show statistics only}
        {--explain : Show query execution plans}';

    protected $description = 'Update photo clustering';

    private ClusteringService $service;

    public function __construct(ClusteringService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        // Show stats
        if ($this->option('stats')) {
            $this->showStats();
            return 0;
        }

        // Populate tile keys
        if ($this->option('populate')) {
            $this->populateTileKeys();
        }

        // Process clusters
        if ($this->option('all')) {
            $this->clusterAll();
        }

        // Team clustering
        if ($teamId = $this->option('team')) {
            $this->clusterTeam((int) $teamId);
        }

        if ($this->option('all-teams')) {
            $this->clusterAllTeams();
        }

        return 0;
    }

    private function populateTileKeys(): void
    {
        $this->info('Populating missing tile keys...');

        $missing = DB::table('photos')
            ->whereNull('tile_key')
            ->count();

        if ($missing === 0) {
            $this->info('All photos already have tile keys!');
            return;
        }

        $this->info("Populating $missing photos - chunking...");
        $bar = $this->output->createProgressBar($missing);

        $totalTime = 0;
        while (true) {
            $start = microtime(true);
            $done = $this->service->backfillPhotoTileKeys();
            if ($done === 0) break;

            $totalTime += microtime(true) - $start;
            $bar->advance($done);
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('✓ tile_key back-fill complete in %.2fs', $totalTime));
    }

    private function clusterAll(): void
    {
        $startTime = microtime(true);
        $this->info('Starting clustering process...');

        // Get zoom levels from config
        $globalZooms = config('clustering.zoom_levels.global', [0, 2, 4, 6]);
        $tileZooms = config('clustering.zoom_levels.tile', [8, 10, 12, 14, 16]);

        // Show query plan if requested
        if ($this->option('explain')) {
            $this->showQueryPlan();
        }

        // Global clustering for low zoom levels
        $this->info('Processing global zoom levels...');
        $globalStats = [];
        foreach ($globalZooms as $zoom) {
            $start = microtime(true);
            $count = $this->service->clusterGlobal($zoom);
            $time = microtime(true) - $start;
            $globalStats[] = compact('zoom', 'count', 'time');
            $this->line(sprintf("✓ Zoom %2d: %6d clusters (%5.2fs)", $zoom, $count, $time));
        }

        // Batch clustering for high zoom levels
        $this->info('Processing per-tile zoom levels (optimized batch mode)...');
        $tileStats = [];
        foreach ($tileZooms as $zoom) {
            $start = microtime(true);
            $memBefore = memory_get_usage(true);

            $count = $this->service->clusterAllTilesForZoom($zoom);

            $time = microtime(true) - $start;
            $memUsed = (memory_get_usage(true) - $memBefore) / 1024 / 1024;
            $tileStats[] = compact('zoom', 'count', 'time', 'memUsed');

            $this->line(sprintf(
                "✓ Zoom %2d: %6d clusters (%5.2fs, %.1fMB)",
                $zoom, $count, $time, $memUsed
            ));
        }

        $totalTime = microtime(true) - $startTime;

        // Flush stale cluster API cache
        $this->flushClusterCache();

        $this->newLine();
        $this->info(sprintf("✓ Clustering complete in %.2fs", $totalTime));

        // Show performance summary
        $this->showPerformanceSummary($globalStats, $tileStats, $totalTime);
    }

    private function showQueryPlan(): void
    {
        $this->info('Query execution plan for deep zoom:');

        $plan = DB::select("
            EXPLAIN FORMAT=JSON
            SELECT
              tile_key,
              FLOOR(cell_x / 20) AS cluster_x,
              FLOOR(cell_y / 20) AS cluster_y,
              COUNT(*)
            FROM photos USE INDEX (idx_photos_fast_cluster)
            WHERE verified >= 2
              AND tile_key IS NOT NULL
            GROUP BY tile_key, cluster_x, cluster_y
            LIMIT 1
        ");

        $json = json_decode($plan[0]->{'EXPLAIN'}, true);
        $this->line(json_encode($json, JSON_PRETTY_PRINT));
        $this->newLine();
    }

    private function showPerformanceSummary(array $globalStats, array $tileStats, float $totalTime): void
    {
        $this->newLine();
        $this->info('Performance Summary:');
        $this->info('───────────────────');

        $globalTime = array_sum(array_column($globalStats, 'time'));
        $tileTime = array_sum(array_column($tileStats, 'time'));
        $globalClusters = array_sum(array_column($globalStats, 'count'));
        $tileClusters = array_sum(array_column($tileStats, 'count'));

        $this->line(sprintf("Global zooms: %.2fs for %d clusters", $globalTime, $globalClusters));
        $this->line(sprintf("Tile zooms:   %.2fs for %d clusters", $tileTime, $tileClusters));
        $this->line(sprintf("Total:        %.2fs for %d clusters", $totalTime, $globalClusters + $tileClusters));

        if ($tileTime > 0) {
            $this->line(sprintf("Throughput:   %.0f clusters/sec", $tileClusters / $tileTime));
        }
    }

    private function clusterTeam(int $teamId): void
    {
        $this->info("Clustering team $teamId...");

        $start = microtime(true);
        $count = $this->service->clusterTeam($teamId);
        $time = microtime(true) - $start;

        $this->line(sprintf("✓ Team %d: %d clusters (%.2fs)", $teamId, $count, $time));
    }

    private function clusterAllTeams(): void
    {
        $teams = DB::table('teams')
            ->where('total_images', '>', 0)
            ->pluck('id', 'name');

        if ($teams->isEmpty()) {
            $this->info('No teams with photos found.');
            return;
        }

        $this->info(sprintf('Clustering %d teams...', $teams->count()));
        $startTime = microtime(true);
        $totalClusters = 0;

        foreach ($teams as $name => $id) {
            $start = microtime(true);
            $count = $this->service->clusterTeam($id);
            $time = microtime(true) - $start;
            $totalClusters += $count;

            $this->line(sprintf("✓ %s (id=%d): %d clusters (%.2fs)", $name, $id, $count, $time));
        }

        $totalTime = microtime(true) - $startTime;
        $this->flushClusterCache();
        $this->newLine();
        $this->info(sprintf("✓ All teams complete: %d clusters in %.2fs", $totalClusters, $totalTime));
    }

    private function flushClusterCache(): void
    {
        $connection = config('cache.stores.redis.connection', 'cache');
        $prefix = config('cache.prefix', 'laravel_cache');
        $pattern = $prefix . 'clusters:v5:*';
        $redis = Redis::connection($connection);

        $keys = $redis->keys($pattern);

        if (!empty($keys)) {
            $redis->del(...$keys);
            $this->line(sprintf('✓ Flushed %d cached cluster responses', count($keys)));
        }
    }

    private function showStats(): void
    {
        $stats = $this->service->getStats();

        $this->info('Clustering Statistics:');
        $this->info('─────────────────────');

        $this->line("Total photos: " . number_format($stats['photos_total']));
        $this->line("Photos with tile keys: " . number_format($stats['photos_with_tiles']));
        $this->line("Verified photos: " . number_format($stats['photos_verified']));
        $this->line("Unique tiles: " . number_format($stats['unique_tiles']));
        $this->line("Total clusters: " . number_format($stats['clusters_total']));

        if (!empty($stats['clusters_by_zoom'])) {
            $this->newLine();
            $this->line("Clusters by zoom:");
            foreach ($stats['clusters_by_zoom'] as $zoom => $count) {
                $this->line(sprintf("  Zoom %2d: %s", $zoom, number_format($count)));
            }
        }

        // Verify data integrity
        $this->newLine();
        $this->info('Data Integrity Check:');
        $verifiedPhotos = DB::table('photos')->where('verified', '>=', 2)->count();
        $z16Points = DB::table('clusters')->where('zoom', 16)->sum('point_count');

        if ($verifiedPhotos == $z16Points) {
            $this->line("✓ All verified photos accounted for in zoom 16 clusters");
        } else {
            $this->warn(sprintf(
                "⚠ Mismatch: %d verified photos vs %d in zoom 16 clusters",
                $verifiedPhotos, $z16Points
            ));
        }
    }
}
