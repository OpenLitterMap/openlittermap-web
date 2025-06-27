<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateClusters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clusters:update
        {--tile= : Specific tile key to process}
        {--hours=24 : Number of hours to look back for changes}
        {--all : Process all active tiles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update photo clusters for tiles with recent changes';

    /**
     * Execute the console command.
     */
    public function handle(ClusteringService $service): int
    {
        $startTime = microtime(true);

        // Determine which tiles to process
        $tiles = $this->getTilesToProcess();

        if (empty($tiles)) {
            $this->info('No tiles to process.');
            return 0;
        }

        $this->info("Processing " . count($tiles) . " tiles...");

        $bar = $this->output->createProgressBar(count($tiles));
        $bar->start();

        $totalPhotos = 0;
        $totalClusters = 0;
        $failed = 0;

        foreach ($tiles as $tileKey) {
            try {
                $result = $service->clusterTile($tileKey);
                $totalPhotos += $result['photos'];
                $totalClusters += $result['clusters'];
            } catch (\Exception $e) {
                $failed++;
                $this->error("\nFailed to process tile $tileKey: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show results
        $duration = round(microtime(true) - $startTime, 2);

        $this->info("Completed:");
        $this->info("  Tiles processed: " . number_format(count($tiles) - $failed));
        $this->info("  Photos clustered: " . number_format($totalPhotos));
        $this->info("  Clusters created: " . number_format($totalClusters));
        $this->info("  Duration: {$duration} seconds");

        if ($failed > 0) {
            $this->warn("  Failed tiles: $failed");
        }

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Determine which tiles to process based on command options
     */
    private function getTilesToProcess(): array
    {
        // Single tile specified
        if ($tile = $this->option('tile')) {
            return [(int) $tile];
        }

        // All tiles
        if ($this->option('all')) {
            return DB::table('active_tiles')
                ->pluck('tile_key')
                ->toArray();
        }

        // Default: tiles with recently changed photos
        $hours = (int) $this->option('hours');

        return DB::table('photos')
            ->select('tile_key')
            ->where('verified', 2)
            ->where('updated_at', '>=', now()->subHours($hours))
            ->whereNotNull('tile_key')
            ->distinct()
            ->pluck('tile_key')
            ->toArray();
    }
}
