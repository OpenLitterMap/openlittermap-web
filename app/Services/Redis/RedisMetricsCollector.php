<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Simplified Redis metrics collector for v5 tagging system
 *
 * Processes photo summaries into Redis metrics across:
 * - 4 location scopes (global, country, state, city)
 * - 5 time windows (daily, weekly, monthly, yearly, all-time)
 */
final class RedisMetricsCollector
{
    // TTLs in seconds
    private const TTL_DAILY = 7776000;    // 90 days
    private const TTL_WEEKLY = 63072000;   // 2 years
    private const TTL_MONTHLY = 157680000; // 5 years
    // Yearly and all-time have no TTL

    /**
     * Process a single photo into Redis metrics
     */
    public static function processPhoto(Photo $photo): void
    {
        // Skip if already processed
        if ($photo->processed_at !== null) {
            return;
        }

        // Simple lock with short TTL
        $lockKey = "lock:p:{$photo->id}";
        if (!Redis::set($lockKey, 1, 'NX', 'EX', 30)) {
            return; // Another process is handling this
        }

        try {
            // Extract metrics from pre-computed summary
            $metrics = self::extractFromSummary($photo);

            // Update all metrics in a single pipeline
            self::updateAllMetrics($photo, $metrics);

            // Store what we processed for future updates
            $photo->update([
                'processed_at' => now(),
                'processed_tags' => json_encode($metrics['tags'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process photo metrics', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * Process a batch of photos for a user
     */
    public static function processBatch(int $userId, Collection $photos): void
    {
        $photos = $photos->filter(fn($p) => $p->processed_at === null);

        if ($photos->isEmpty()) {
            return;
        }

        // Process each photo individually for simplicity
        // The pipeline in updateAllMetrics handles efficiency
        foreach ($photos as $photo) {
            self::processPhoto($photo);
        }
    }

    /**
     * Update existing photo (handles tag changes)
     */
    public static function updatePhoto(Photo $photo): void
    {
        if ($photo->processed_at === null) {
            // Never processed, just process normally
            self::processPhoto($photo);
            return;
        }

        $lockKey = "lock:p:{$photo->id}";
        if (!Redis::set($lockKey, 1, 'NX', 'EX', 30)) {
            return;
        }

        try {
            // Get old and new metrics
            $oldTags = json_decode($photo->processed_tags ?? '{}', true);
            $newMetrics = self::extractFromSummary($photo);
            $newTags = $newMetrics['tags'];

            // Calculate deltas
            $deltas = self::calculateDeltas($oldTags, $newTags);

            if (empty($deltas['increments']) && empty($deltas['decrements'])) {
                return; // No changes
            }

            // Apply deltas
            self::applyDeltas($photo, $newMetrics, $deltas);

            // Update processed tags
            $photo->update([
                'processed_tags' => json_encode($newTags)
            ]);

        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * Delete photo metrics (full removal)
     */
    public static function deletePhoto(Photo $photo): void
    {
        if ($photo->processed_at === null || empty($photo->processed_tags)) {
            return; // Nothing to delete
        }

        $lockKey = "lock:p:{$photo->id}";
        if (!Redis::set($lockKey, 1, 'NX', 'EX', 30)) {
            return;
        }

        try {
            $tags = json_decode($photo->processed_tags, true);
            $metrics = [
                'litter' => $photo->summary['totals']['total_objects'] ?? 0,
                'xp' => $photo->xp,
                'tags' => $tags
            ];

            // Apply negative deltas to remove counts
            $deltas = [
                'increments' => [],
                'decrements' => $tags
            ];

            self::applyDeltas($photo, $metrics, $deltas, true);

            // Clear processed data
            $photo->update([
                'processed_at' => null,
                'processed_tags' => null
            ]);

        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * Extract metrics from photo summary
     */
    private static function extractFromSummary(Photo $photo): array
    {
        $summary = $photo->summary;
        $tags = [];

        if (empty($summary['tags'])) {
            return [
                'litter' => 0,
                'xp' => $photo->xp ?? 0,
                'tags' => [],
                'timestamp' => $photo->created_at
            ];
        }

        // Flatten hierarchical structure into composite keys
        foreach ($summary['tags'] as $categoryKey => $objects) {
            if (!is_array($objects)) continue;

            $categoryTotal = 0;

            foreach ($objects as $objectKey => $data) {
                if (!is_array($data)) continue;

                $qty = (int)($data['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $categoryTotal += $qty;

                // Object count
                $tags["o:$objectKey"] = ($tags["o:$objectKey"] ?? 0) + $qty;

                // Materials
                foreach ($data['materials'] ?? [] as $matKey => $matQty) {
                    $tags["m:$matKey"] = ($tags["m:$matKey"] ?? 0) + (int)$matQty;
                }

                // Brands
                foreach ($data['brands'] ?? [] as $brandKey => $brandQty) {
                    $tags["b:$brandKey"] = ($tags["b:$brandKey"] ?? 0) + (int)$brandQty;
                }

                // Custom tags
                foreach ($data['custom_tags'] ?? [] as $customKey => $customQty) {
                    $tags["ct:$customKey"] = ($tags["ct:$customKey"] ?? 0) + (int)$customQty;
                }
            }

            // Category total
            if ($categoryTotal > 0) {
                $tags["c:$categoryKey"] = $categoryTotal;
            }
        }

        return [
            'litter' => $summary['totals']['total_objects'] ?? 0,
            'xp' => $photo->xp ?? 0,
            'tags' => $tags,
            'timestamp' => $photo->created_at
        ];
    }

    /**
     * Update all metrics in Redis
     */
    private static function updateAllMetrics(Photo $photo, array $metrics): void
    {
        $userId = $photo->user_id;
        $scopes = self::getScopes($photo);
        $timestamp = $metrics['timestamp'];

        Redis::pipeline(function($pipe) use ($userId, $scopes, $metrics, $timestamp) {
            // User metrics
            $userScope = "u:$userId";
            $pipe->hIncrBy("$userScope:s", 'uploads', 1);
            $pipe->hIncrBy("$userScope:s", 'xp', $metrics['xp']);
            $pipe->hIncrBy("$userScope:s", 'litter', $metrics['litter']);

            foreach ($metrics['tags'] as $tagKey => $count) {
                $pipe->hIncrBy("$userScope:t", $tagKey, $count);
            }

            // Update user bitmap for streak calculation
            $dayIndex = self::getDayIndex($timestamp);
            $pipe->setBit("$userScope:b", $dayIndex, 1);

            // Location and global metrics
            foreach ($scopes as $scope) {
                // All-time stats
                $pipe->hIncrBy("$scope:s", 'photos', 1);
                $pipe->hIncrBy("$scope:s", 'litter', $metrics['litter']);
                $pipe->hIncrBy("$scope:s", 'xp', $metrics['xp']);

                // Contributor tracking
                $pipe->pfAdd("$scope:hll", (string)$userId);
                $pipe->zIncrBy("$scope:contributors", 1, (string)$userId);

                // Tags
                foreach ($metrics['tags'] as $tagKey => $count) {
                    $pipe->hIncrBy("$scope:t", $tagKey, $count);

                    // Update rankings for significant dimensions
                    [$dimension, $id] = explode(':', $tagKey, 2);
                    if (in_array($dimension, ['o', 'b', 'c'])) {
                        $pipe->zIncrBy("$scope:r:$dimension", $count, $id);
                    }
                }

                // Time windows
                self::updateTimeWindows($pipe, $scope, $timestamp, $metrics);
            }
        });
    }

    /**
     * Update time window aggregates
     */
    private static function updateTimeWindows($pipe, string $scope, $timestamp, array $metrics): void
    {
        $date = $timestamp->format('Ymd');
        $week = $timestamp->format('YW');
        $month = $timestamp->format('Ym');
        $year = $timestamp->format('Y');

        // Daily (90 day TTL)
        $dayKey = "$scope:d:$date";
        $pipe->hIncrBy($dayKey, 'p', 1);
        $pipe->hIncrBy($dayKey, 'l', $metrics['litter']);
        $pipe->hIncrBy($dayKey, 'x', $metrics['xp']);
        $pipe->expire($dayKey, self::TTL_DAILY);

        // Weekly (2 year TTL)
        $weekKey = "$scope:w:$week";
        $pipe->hIncrBy($weekKey, 'p', 1);
        $pipe->hIncrBy($weekKey, 'l', $metrics['litter']);
        $pipe->hIncrBy($weekKey, 'x', $metrics['xp']);
        $pipe->expire($weekKey, self::TTL_WEEKLY);

        // Monthly (5 year TTL)
        $monthKey = "$scope:m:$month";
        $pipe->hIncrBy($monthKey, 'p', 1);
        $pipe->hIncrBy($monthKey, 'l', $metrics['litter']);
        $pipe->hIncrBy($monthKey, 'x', $metrics['xp']);
        $pipe->expire($monthKey, self::TTL_MONTHLY);

        // Yearly (no TTL)
        $yearKey = "$scope:y:$year";
        $pipe->hIncrBy($yearKey, 'p', 1);
        $pipe->hIncrBy($yearKey, 'l', $metrics['litter']);
        $pipe->hIncrBy($yearKey, 'x', $metrics['xp']);
    }

    /**
     * Calculate deltas between old and new tags
     */
    private static function calculateDeltas(array $oldTags, array $newTags): array
    {
        $increments = [];
        $decrements = [];

        // Find increases and new tags
        foreach ($newTags as $key => $newCount) {
            $oldCount = $oldTags[$key] ?? 0;
            if ($newCount > $oldCount) {
                $increments[$key] = $newCount - $oldCount;
            }
        }

        // Find decreases and removed tags
        foreach ($oldTags as $key => $oldCount) {
            $newCount = $newTags[$key] ?? 0;
            if ($oldCount > $newCount) {
                $decrements[$key] = $oldCount - $newCount;
            }
        }

        return ['increments' => $increments, 'decrements' => $decrements];
    }

    /**
     * Apply deltas to Redis
     */
    private static function applyDeltas(Photo $photo, array $metrics, array $deltas, bool $isDelete = false): void
    {
        $userId = $photo->user_id;
        $scopes = self::getScopes($photo);
        $timestamp = $metrics['timestamp'];

        Redis::pipeline(function($pipe) use ($userId, $scopes, $deltas, $timestamp, $isDelete, $metrics) {
            $userScope = "u:$userId";

            // Apply decrements
            foreach ($deltas['decrements'] as $tagKey => $count) {
                $pipe->hIncrBy("$userScope:t", $tagKey, -$count);

                foreach ($scopes as $scope) {
                    $pipe->hIncrBy("$scope:t", $tagKey, -$count);

                    [$dimension, $id] = explode(':', $tagKey, 2);
                    if (in_array($dimension, ['o', 'b', 'c'])) {
                        $pipe->zIncrBy("$scope:r:$dimension", -$count, $id);
                    }
                }
            }

            // Apply increments (skip if deleting)
            if (!$isDelete) {
                foreach ($deltas['increments'] as $tagKey => $count) {
                    $pipe->hIncrBy("$userScope:t", $tagKey, $count);

                    foreach ($scopes as $scope) {
                        $pipe->hIncrBy("$scope:t", $tagKey, $count);

                        [$dimension, $id] = explode(':', $tagKey, 2);
                        if (in_array($dimension, ['o', 'b', 'c'])) {
                            $pipe->zIncrBy("$scope:r:$dimension", $count, $id);
                        }
                    }
                }
            }

            // Update photo/litter/xp counts if deleting
            if ($isDelete) {
                $pipe->hIncrBy("$userScope:s", 'uploads', -1);
                $pipe->hIncrBy("$userScope:s", 'xp', -$metrics['xp']);
                $pipe->hIncrBy("$userScope:s", 'litter', -$metrics['litter']);

                foreach ($scopes as $scope) {
                    $pipe->hIncrBy("$scope:s", 'photos', -1);
                    $pipe->hIncrBy("$scope:s", 'litter', -$metrics['litter']);
                    $pipe->hIncrBy("$scope:s", 'xp', -$metrics['xp']);

                    // Note: Can't remove from HyperLogLog or time series
                }
            }
        });
    }

    /**
     * Get location scopes for a photo
     */
    private static function getScopes(Photo $photo): array
    {
        return array_filter([
            'g',  // Global
            $photo->country_id ? "c:{$photo->country_id}" : null,
            $photo->state_id ? "s:{$photo->state_id}" : null,
            $photo->city_id ? "ci:{$photo->city_id}" : null,
        ]);
    }

    /**
     * Calculate day index for bitmap
     */
    private static function getDayIndex($timestamp): int
    {
        $epoch = new \DateTime('2020-01-01', new \DateTimeZone('UTC'));
        $diff = $epoch->diff($timestamp);
        return $diff->days;
    }

    /**
     * Get user metrics (for achievements)
     */
    public static function getUserMetrics(int $userId): array
    {
        $userScope = "u:$userId";

        try {
            $results = Redis::pipeline(function($pipe) use ($userScope) {
                $pipe->hGetAll("$userScope:s");
                $pipe->hGetAll("$userScope:t");
            });
        } catch (\Exception $e) {
            Log::error('Failed to get user metrics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return self::emptyUserMetrics();
        }

        $stats = $results[0] ?? [];
        $tags = $results[1] ?? [];

        // Parse tags by dimension
        $dimensions = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => []
        ];

        foreach ($tags as $key => $count) {
            [$prefix, $id] = explode(':', $key, 2);
            $count = (int)$count;

            switch ($prefix) {
                case 'c':
                    $dimensions['categories'][$id] = $count;
                    break;
                case 'o':
                    $dimensions['objects'][$id] = $count;
                    break;
                case 'm':
                    $dimensions['materials'][$id] = $count;
                    break;
                case 'b':
                    $dimensions['brands'][$id] = $count;
                    break;
                case 'ct':
                    $dimensions['custom_tags'][$id] = $count;
                    break;
            }
        }

        return [
            'uploads' => (int)($stats['uploads'] ?? 0),
            'xp' => (int)($stats['xp'] ?? 0),
            'litter' => (int)($stats['litter'] ?? 0),
            'streak' => self::calculateStreak($userId),
            'categories' => $dimensions['categories'],
            'objects' => $dimensions['objects'],
            'materials' => $dimensions['materials'],
            'brands' => $dimensions['brands'],
            'custom_tags' => $dimensions['custom_tags'],
        ];
    }

    /**
     * Calculate user streak
     */
    private static function calculateStreak(int $userId): int
    {
        $bitmapKey = "u:$userId:b";
        $today = self::getDayIndex(now());
        $streak = 0;

        // Check backwards from today
        for ($i = 0; $i < 365; $i++) {
            $dayIndex = $today - $i;
            if ($dayIndex < 0) break;

            if (!Redis::getBit($bitmapKey, $dayIndex)) {
                // Allow 1 day gap for today
                if ($i === 0) continue;
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Empty user metrics structure
     */
    private static function emptyUserMetrics(): array
    {
        return [
            'uploads' => 0,
            'xp' => 0,
            'litter' => 0,
            'streak' => 0,
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];
    }
}
