<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDirtyTiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clustering:process-dirty
        {--limit=100 : Maximum number of tiles to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process tiles marked as dirty';

    /**
     * Execute the console command.
     */
    public function handle(ClusteringService $service): int
    {
        $limit = (int) $this->option('limit');

        // Get dirty tiles
        $tiles = DB::table('dirty_tiles')
            ->orderBy('changed_at')
            ->limit($limit)
            ->pluck('tile_key');

        if ($tiles->isEmpty()) {
            $this->info('No dirty tiles to process.');
            return 0;
        }

        $this->info("Processing {$tiles->count()} dirty tiles...");
        $bar = $this->output->createProgressBar($tiles->count());

        $processed = 0;
        $failed = 0;

        foreach ($tiles as $tileKey) {
            try {
                $service->clusterTile($tileKey);

                // Remove from dirty tiles
                DB::table('dirty_tiles')
                    ->where('tile_key', $tileKey)
                    ->delete();

                $processed++;
            } catch (\Exception $e) {
                $failed++;
                $this->error("\nFailed to process tile $tileKey: " . $e->getMessage());

                // Mark with backoff
                $service->markTileDirty($tileKey, true);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Processed: $processed tiles");
        if ($failed > 0) {
            $this->warn("✗ Failed: $failed tiles (will retry with backoff)");
        }

        // Show remaining
        $remaining = DB::table('dirty_tiles')->count();
        if ($remaining > 0) {
            $this->info("Remaining dirty tiles: $remaining");
        }

        $ttl = config('clustering.dirty_tile_ttl', 24);
        DB::table('dirty_tiles')
            ->where('attempts', '>=', 3)
            ->where('changed_at', '<', now()->subHours($ttl))
            ->delete();

        return $failed > 0 ? 1 : 0;
    }
}
