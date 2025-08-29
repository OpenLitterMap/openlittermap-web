<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Redis\LuaOperations;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class TrimRankingZsetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LuaOperations;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [60, 120, 300];

    /**
     * @var array Keys to trim
     */
    private array $keys;

    /**
     * @var int Maximum size for ZSETs
     */
    private int $maxSize;

    /**
     * Create a new job instance
     */
    public function __construct(array $keys, int $maxSize = null)
    {
        $this->keys = $keys;
        $this->maxSize = $maxSize ?? config('redis_metrics.limits.max_ranking_items', 500);
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $trimmedCount = 0;

        try {
            // Process keys in chunks to avoid memory issues
            foreach (array_chunk($this->keys, 50) as $chunk) {
                $trimmedCount += $this->trimChunk($chunk);
            }

            $duration = microtime(true) - $startTime;

            Log::info('Ranking ZSETs trimmed successfully', [
                'keys_processed' => count($this->keys),
                'trimmed_count' => $trimmedCount,
                'duration_seconds' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to trim ranking ZSETs', [
                'error' => $e->getMessage(),
                'keys_count' => count($this->keys),
            ]);

            throw $e;
        }
    }

    /**
     * Trim a chunk of keys
     */
    private function trimChunk(array $keys): int
    {
        $trimmed = 0;

        Redis::pipeline(function ($pipe) use ($keys, &$trimmed) {
            foreach ($keys as $key) {
                // Skip certain ranking types that shouldn't be trimmed
                if ($this->shouldSkipTrim($key)) {
                    continue;
                }

                $this->trimZset($pipe, $key, $this->maxSize);
                $trimmed++;
            }
        });

        return $trimmed;
    }

    /**
     * Check if a key should be skipped
     */
    private function shouldSkipTrim(string $key): bool
    {
        // Don't trim location hierarchy rankings
        $skipPatterns = [
            ':rank:c:litter',
            ':rank:c:photos',
            ':rank:s:litter',
            ':rank:s:photos',
            ':rank:ci:litter',
            ':rank:ci:photos',
            ':rank:contributors',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TrimRankingZsetsJob failed after retries', [
            'error' => $exception->getMessage(),
            'keys_count' => count($this->keys),
        ]);
    }
}
