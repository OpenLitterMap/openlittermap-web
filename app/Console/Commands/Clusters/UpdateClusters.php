<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateClusters extends Command
{
    protected $signature = 'clustering:update
        {--populate : Populate missing tile keys}
        {--all : Recluster all tiles}
        {--stats : Show statistics only}';

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
            $this->clusterAllTiles();
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

        $this->info("Populating $missing photos – chunking …");
        $bar = $this->output->createProgressBar($missing);

        while (true) {
            $done = $this->service->backfillPhotoTileKeys(); // default 50 k
            if ($done === 0) break;

            $bar->advance($done);
        }

        $bar->finish();
        $this->newLine();
        $this->info(' ✓  tile_key back-fill complete');
    }

    private function clusterAllTiles(): void
    {
        $this->info('Clustering all tiles...');

        $tiles = DB::table('photos')
            ->select('tile_key', DB::raw('COUNT(*) as photo_count'))
            ->where('verified', 2)
            ->whereNotNull('tile_key')
            ->groupBy('tile_key')
            ->having('photo_count', '>=', 2)
            ->pluck('tile_key');

        $this->info("Found {$tiles->count()} tiles to cluster");

        $bar = $this->output->createProgressBar($tiles->count());
        $bar->start();

        $failed = 0;

        foreach ($tiles as $tileKey) {
            try {
                $this->service->clusterTile($tileKey);
            } catch (\Exception $e) {
                $failed++;
                Log::error("Error clustering tile $tileKey", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('✓ Clustering complete');
        if ($failed > 0) {
            $this->warn("Failed to cluster $failed tiles - check logs");
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
                $this->line("  Zoom $zoom: " . number_format($count));
            }
        }
    }
}
