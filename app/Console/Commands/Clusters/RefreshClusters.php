<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshClusters extends Command
{
    protected $signature = 'clusters:refresh
                            {--hours=24 : Look back N hours for changes}
                            {--all : Rebuild *all* tiles (ignores --hours)}
                            {--limit=0 : Max tiles to process (0 = unlimited)}
                            {--year= : Limit to specific year}
                            {--batch=10 : Process tiles in batches of N}
                            {--affected-only : Only process tiles that contain photos}';

    protected $description = 'Scans tiles that changed recently and rebuilds clusters';

    private ClusteringService $svc;

    public function __construct(ClusteringService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $startedAt = microtime(true);
        $year = $this->option('year') ? (int)$this->option('year') : null;
        $batchSize = (int)$this->option('batch') ?: 10;
        $runId = $this->createRun();

        $totals = [
            'tiles'     => 0,
            'failed'    => 0,
            'photos'    => 0,
            'clusters'  => 0,
        ];

        $clustersByZoom = [];

        try {
            // Set session lock timeout for this process
            DB::statement('SET SESSION innodb_lock_wait_timeout = 30');

            $tileQuery = $this->buildTileQuery();
            $tileCount = (clone $tileQuery)->count('tile_key');

            if ($tileCount === 0) {
                $this->info('Nothing to do – no tiles qualify.');
                $this->finaliseRun($runId, 'completed', $totals, microtime(true) - $startedAt);
                return 0;
            }

            $this->info("Processing {$tileCount} tiles" . ($year ? " for year {$year}" : '') . " in batches of {$batchSize}…\n");

            $bar = null;
            if ($this->output->isVerbose() || !$this->option('no-interaction')) {
                $bar = $this->output->createProgressBar($tileCount);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% mem:%memory:6s%');
                $bar->start();
            }

            // Process tiles in batches for better efficiency
            $tileQuery->orderBy('tile_key')
                ->distinct()
                ->chunk($batchSize, function ($tiles) use (&$totals, &$clustersByZoom, $bar, $year) {
                    $batchStartTime = microtime(true);
                    $batchTiles = 0;
                    $batchPhotos = 0;
                    $batchClusters = 0;

                    foreach ($tiles as $row) {
                        try {
                            // Use optimized method if requested
                            if ($this->option('affected-only')) {
                                $result = $this->svc->rebuildAffectedTiles((int)$row->tile_key, $year);
                                $totals['tiles'] += $result['tiles_processed'];
                                $batchTiles += $result['tiles_processed'];
                                $totals['photos'] += $result['total_photos'];
                                $batchPhotos += $result['total_photos'];
                                $totals['clusters'] += $result['total_clusters'];
                                $batchClusters += $result['total_clusters'];
                            } else {
                                $result = $this->svc->rebuildTile((int)$row->tile_key, $year);
                                $totals['tiles']++;
                                $batchTiles++;
                                $totals['photos'] += $result['photos'];
                                $batchPhotos += $result['photos'];
                                $totals['clusters'] += $result['clusters'];
                                $batchClusters += $result['clusters'];
                            }

                            // Track clusters by zoom for debugging
                            if (config('clustering.debug') && isset($result['clusters_by_zoom'])) {
                                foreach ($result['clusters_by_zoom'] as $zoom => $count) {
                                    $clustersByZoom[$zoom] = ($clustersByZoom[$zoom] ?? 0) + $count;
                                }
                            }

                            if ($result['clusters'] === 0 && $result['photos'] > 0) {
                                \Log::warning('No clusters created despite photos', [
                                    'tile_key' => $row->tile_key,
                                    'photos' => $result['photos'],
                                    'year' => $year
                                ]);
                            }
                        } catch (\Throwable $e) {
                            $totals['tiles']++;
                            $totals['failed']++;
                            $batchTiles++;

                            \Log::error('Tile rebuild failed', [
                                'tile_key' => $row->tile_key,
                                'year' => $year,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);

                            // If too many failures in this batch, abort
                            if ($totals['failed'] > 10 && $totals['failed'] > $totals['tiles'] * 0.1) {
                                throw new \RuntimeException("Too many failures ({$totals['failed']}), aborting");
                            }
                        }

                        if ($bar) {
                            $bar->advance();
                        }
                    }

                    // Log batch performance
                    $batchDuration = microtime(true) - $batchStartTime;
                    if ($this->output->isVerbose()) {
                        $this->comment(sprintf(
                            "\nBatch complete: %d tiles, %d photos, %d clusters in %.2fs (%.2f tiles/s)",
                            $batchTiles,
                            $batchPhotos,
                            $batchClusters,
                            $batchDuration,
                            $batchTiles / $batchDuration
                        ));
                    }

                    // Update run progress periodically
                    if ($totals['tiles'] % 100 === 0) {
                        $this->updateRunProgress($runId, $totals);
                    }
                });

            if ($bar) {
                $bar->finish();
            }
            $this->newLine(2);

            $duration = microtime(true) - $startedAt;
            $tableData = [
                ['Tiles processed', number_format($totals['tiles'])],
                ['Tiles failed',    number_format($totals['failed'])],
                ['Photos processed',number_format($totals['photos'])],
                ['Clusters created',number_format($totals['clusters'])],
                ['Duration (s)',    number_format($duration, 2)],
                ['Throughput',      $duration ? number_format($totals['tiles'] / $duration, 2).' tiles/s' : 'n/a'],
                ['Avg photos/tile', $totals['tiles'] ? number_format($totals['photos'] / $totals['tiles'], 1) : 'n/a'],
                ['Avg clusters/tile', $totals['tiles'] ? number_format($totals['clusters'] / $totals['tiles'], 1) : 'n/a'],
            ];

            if (config('clustering.debug') && !empty($clustersByZoom)) {
                $this->newLine();
                $this->info('Clusters by zoom level:');
                ksort($clustersByZoom);
                foreach ($clustersByZoom as $zoom => $count) {
                    $tableData[] = ["  Zoom {$zoom}", number_format($count)];
                }
            }

            $this->output->table(['Metric', 'Count'], $tableData);

            $status = $totals['failed'] > 0 ? 'completed' : 'completed';
            $this->finaliseRun($runId, $status, $totals, $duration, null, $clustersByZoom);

            return $totals['failed'] > 0 ? 1 : 0;
        } catch (\Throwable $e) {
            $this->finaliseRun($runId, 'failed', $totals, microtime(true) - $startedAt, $e->getMessage());
            $this->error('Fatal: '.$e->getMessage());
            return 1;
        }
    }

    private function buildTileQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('photos')
            ->select('tile_key')
            ->whereNotNull('tile_key')
            ->where('verified', 2);

        if (!$this->option('all')) {
            $hours = (int)$this->option('hours');
            $q->where('updated_at', '>=', now()->subHours($hours));
        }

        if ($limit = (int)$this->option('limit')) {
            $q->limit($limit);
        }

        return $q;
    }

    private function createRun(): int
    {
        return DB::table('clustering_runs')->insertGetId([
            'run_type' => $this->option('all') ? 'full_rebuild' : 'manual',
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    private function updateRunProgress(int $id, array $totals): void
    {
        DB::table('clustering_runs')->where('id', $id)->update([
            'tiles_processed' => $totals['tiles'],
            'tiles_failed' => $totals['failed'],
            'photos_processed' => $totals['photos'],
            'clusters_created' => $totals['clusters'],
        ]);
    }

    private function finaliseRun(
        int $id,
        string $status,
        array $t,
        float $secs,
        string $err = null,
        array $clustersByZoom = []
    ): void {
        $payload = [
            'tiles_processed'   => $t['tiles'],
            'tiles_failed'      => $t['failed'],
            'photos_processed'  => $t['photos'],
            'clusters_created'  => $t['clusters'],
            'peak_memory_mb'    => round(memory_get_peak_usage() / 1048576, 2),
            'status'            => $status,
            'completed_at'      => now(),
        ];

        if ($err) {
            $payload['error_message'] = $err;
        }

        // Store quality metrics if available
        if (!empty($clustersByZoom)) {
            $metrics = [
                'clusters_by_zoom' => $clustersByZoom,
                'avg_photos_per_tile' => $t['tiles'] > 0 ? round($t['photos'] / $t['tiles'], 2) : 0,
                'avg_clusters_per_tile' => $t['tiles'] > 0 ? round($t['clusters'] / $t['tiles'], 2) : 0,
                'failure_rate' => $t['tiles'] > 0 ? round($t['failed'] / $t['tiles'] * 100, 2) : 0,
            ];
            $payload['quality_metrics'] = json_encode($metrics);
        }

        DB::table('clustering_runs')->where('id', $id)->update($payload);
    }
}
