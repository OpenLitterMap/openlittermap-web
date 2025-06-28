<?php

namespace App\Jobs\Clusters;

use App\Services\Clustering\ClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessClusteringBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->tries = config('clustering.queue.max_tries', 3);
        $this->queue = config('clustering.queue.name', 'clustering');
        $this->connection = config('clustering.queue.connection', 'database');
    }

    /**
     * Execute the job.
     */
    public function handle(ClusteringService $service): void
    {
        $batchSize = config('clustering.batch_size', 100);
        $processed = 0;
        $failed = 0;

        // Process one batch of dirty tiles
        $tiles = $service->popDirtyTiles($batchSize);

        if (empty($tiles)) {
            return; // No work to do
        }

        foreach ($tiles as $tileKey) {
            try {
                $service->clusterTile($tileKey);
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                // Re-add with backoff
                $service->markTileDirty($tileKey, true);

                Log::error('Failed to cluster tile', [
                    'tile_key' => $tileKey,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($processed > 0 || $failed > 0) {
            Log::info('Clustering batch completed', [
                'processed' => $processed,
                'failed' => $failed,
            ]);
        }

        // If we processed a full batch, there might be more work
        if (count($tiles) == $batchSize) {
            self::dispatch()->delay(now()->addSeconds(1));
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        $backoffString = config('clustering.queue.backoff', '60,300,900');
        return array_map('intval', explode(',', $backoffString));
    }
}
