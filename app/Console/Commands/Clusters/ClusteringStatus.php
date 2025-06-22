<?php

namespace App\Console\Commands\Clusters;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClusteringStatus extends Command
{
    protected $signature = 'clustering:status';
    protected $description = 'Show clustering system status';

    public function handle(): int
    {
        $this->info('Clustering System Status');
        $this->info('========================');

        // Check tables exist
        $this->newLine();
        $this->info('Database Tables:');

        $tables = [
            'photos.tile_key column' => Schema::hasColumn('photos', 'tile_key'),
            'clusters table' => Schema::hasTable('clusters'),
            'clustering_runs table' => Schema::hasTable('clustering_runs'),
        ];

        foreach ($tables as $name => $exists) {
            $this->line(sprintf("  %-25s %s", $name, $exists ? '✓' : '✗'));
        }

        if (!$tables['photos.tile_key column']) {
            $this->error("\nMissing tile_key column! Run: php artisan migrate");
            return 1;
        }

        // Photo statistics
        $this->newLine();
        $this->info('Photo Statistics:');

        $stats = DB::selectOne("
            SELECT
                COUNT(*) as total_photos,
                SUM(CASE WHEN verified = 2 THEN 1 ELSE 0 END) as verified_photos,
                SUM(CASE WHEN tile_key IS NOT NULL THEN 1 ELSE 0 END) as with_tile_key,
                COUNT(DISTINCT tile_key) as unique_tiles
            FROM photos
        ");

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Photos', number_format($stats->total_photos)],
                ['Verified Photos', number_format($stats->verified_photos)],
                ['Photos with Tile Key', number_format($stats->with_tile_key)],
                ['Unique Tiles', number_format($stats->unique_tiles)],
            ]
        );

        // Cluster statistics
        $this->newLine();
        $this->info('Cluster Statistics:');

        $clusterCount = DB::table('clusters')->count();

        if ($clusterCount === 0) {
            $this->warn('  No clusters generated yet. Run: php artisan clusters:refresh --all');
        } else {
            $clusterStats = DB::select("
                SELECT
                    zoom,
                    COUNT(*) as clusters,
                    SUM(point_count) as total_points,
                    AVG(point_count) as avg_points_per_cluster
                FROM clusters
                GROUP BY zoom
                ORDER BY zoom
            ");

            $this->table(
                ['Zoom', 'Clusters', 'Total Points', 'Avg Points/Cluster'],
                array_map(function($row) {
                    return [
                        $row->zoom,
                        number_format($row->clusters),
                        number_format($row->total_points),
                        round($row->avg_points_per_cluster, 1)
                    ];
                }, $clusterStats)
            );

            // Total summary
            $totals = DB::selectOne("
                SELECT
                    COUNT(*) as total_clusters,
                    SUM(point_count) as total_points
                FROM clusters
            ");

            $this->info("\n  Total: " . number_format($totals->total_clusters) . " clusters containing " .
                number_format($totals->total_points) . " points");
        }

        // Recent runs
        $this->newLine();
        $this->info('Recent Clustering Runs:');

        $recentRuns = DB::table('clustering_runs')
            ->orderBy('started_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentRuns->isEmpty()) {
            $this->warn('  No runs recorded yet.');
        } else {
            $this->table(
                ['Started', 'Type', 'Status', 'Tiles', 'Duration'],
                $recentRuns->map(function($run) {
                    return [
                        $run->started_at,
                        $run->run_type,
                        $run->status,
                        $run->tiles_processed . '/' . ($run->tiles_processed + $run->tiles_failed),
                        $run->duration_seconds ? $run->duration_seconds . 's' : 'N/A'
                    ];
                })->toArray()
            );
        }

        // Next steps
        $this->newLine();
        $this->info('Next Steps:');

        $needsClustering = DB::selectOne("
            SELECT COUNT(DISTINCT tile_key) as tiles_to_process
            FROM photos
            WHERE tile_key IS NOT NULL
              AND verified = 2
              AND tile_key NOT IN (SELECT DISTINCT tile_key FROM clusters)
        ");

        if ($needsClustering->tiles_to_process > 0) {
            $this->line("  • " . number_format($needsClustering->tiles_to_process) .
                " tiles need clustering. Run:");
            $this->line("    php artisan clusters:refresh --all");
        }

        $recentChanges = DB::selectOne("
            SELECT COUNT(DISTINCT tile_key) as recent_tiles
            FROM photos
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND tile_key IS NOT NULL
              AND verified = 2
        ");

        if ($recentChanges->recent_tiles > 0) {
            $this->line("  • " . number_format($recentChanges->recent_tiles) .
                " tiles changed in last 24 hours. Run:");
            $this->line("    php artisan clusters:refresh");
        }

        // Performance check
        $this->newLine();
        $this->info('Performance Check:');

        // Test query performance
        $start = microtime(true);
        $testCount = DB::table('clusters')
            ->where('zoom', 10)
            ->whereBetween('lat', [51.0, 52.0])
            ->whereBetween('lon', [-1.0, 0.0])
            ->count();
        $queryTime = round((microtime(true) - $start) * 1000, 2);

        $this->line("  Bounding box query (zoom 10): {$queryTime}ms ({$testCount} clusters)");

        // Memory check
        $memoryLimit = ini_get('memory_limit');
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $this->line("  Memory usage: {$memoryUsage}MB / {$memoryLimit}");

        // Tile processing estimate
        if ($stats->unique_tiles > 0) {
            $estimatedTime = round($stats->unique_tiles * 0.5 / 60, 1); // 0.5 sec per tile
            $this->line("  Estimated full refresh time: ~{$estimatedTime} minutes");
        }

        return 0;
    }
}
