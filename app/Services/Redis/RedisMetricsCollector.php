<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

final class RedisMetricsCollector
{
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years
    private const UPLOAD_FLAG_TTL_SECONDS = 60 * 60 * 24 * 40; // 40 days

    private const KEY_STATS = '{u:%d}:stats';
    private const KEY_CATEGORIES = '{u:%d}:c';
    private const KEY_OBJECTS = '{u:%d}:t';
    private const KEY_MATERIALS = '{u:%d}:m';
    private const KEY_BRANDS = '{u:%d}:brands';
    private const KEY_CUSTOM = '{u:%d}:custom';

    private const GLOBAL_CATEGORIES = '{g}:c';
    private const GLOBAL_OBJECTS = '{g}:t';
    private const GLOBAL_MATERIALS = '{g}:m';
    private const GLOBAL_BRANDS = '{g}:brands';

    private const BLOOM_FILTER_KEY = 'photos:processed:bloom';
    private const BLOOM_ERROR_RATE = 0.01;  // 1% false positive rate
    private const BLOOM_CAPACITY = 100000000; // 100M items
    private static ?bool $bloomInitialized = null;
    private static ?bool $bloomAvailable = null;

    /**
     * Persist all Redis metrics for a single photo.
     */
    public static function queue(Photo $photo): void
    {
        if (self::alreadyProcessed($photo->id)) {
            return;
        }

        self::persistMetrics($photo);
    }

    /**
     * Check if bloom filter is available
     */
    private static function isBloomAvailable(): bool
    {
        if (self::$bloomAvailable !== null) {
            return self::$bloomAvailable;
        }

        try {
            // Try to check if module is loaded
            $conn = Redis::connection();
            if (method_exists($conn, 'rawCommand')) {
                $conn->rawCommand('BF.INFO', 'test');
                self::$bloomAvailable = true;
            } else {
                self::$bloomAvailable = false;
            }
        } catch (\Throwable $e) {
            self::$bloomAvailable = false;
        }

        return self::$bloomAvailable;
    }

    /**
     * Send a module command inside or outside a pipeline.
     */
    private static function sendRaw($connOrPipe, array $cmd): mixed
    {
        // Convert all arguments to strings to avoid scalar type errors
        $cmd = array_map(function ($arg) {
            return (string) $arg;
        }, $cmd);

        // PhpRedis: Redis|RedisCluster|Pipeline all have rawCommand()
        if (method_exists($connOrPipe, 'rawCommand')) {
            return $connOrPipe->rawCommand(...$cmd);
        }

        // Predis: Client and Pipeline objects have executeRaw(array $cmd)
        if (method_exists($connOrPipe, 'executeRaw')) {
            return $connOrPipe->executeRaw($cmd);
        }

        throw new \RuntimeException('No raw Redis command method found');
    }

    /**
     * Mark photo as processed using bloom filter or fallback
     */
    private static function markAsProcessed($connOrPipe, int $photoId): void
    {
        if (self::isBloomAvailable()) {
            try {
                self::sendRaw($connOrPipe, ['BF.ADD', self::BLOOM_FILTER_KEY, (string) $photoId]);
            } catch (\Throwable $e) {
                // Fallback to set if bloom fails
                $connOrPipe->sAdd('p:done', $photoId);
            }
        } else {
            // Use regular set as fallback
            $connOrPipe->sAdd('p:done', $photoId);
            $connOrPipe->expire('p:done', 60 * 60 * 24 * 90);
        }
    }

    /**
     * Check if photo was already processed
     */
    private static function alreadyProcessed(?int $photoId): bool
    {
        if (!$photoId) {
            return false;
        }

        if (self::isBloomAvailable()) {
            self::ensureBloomFilter();
            try {
                return self::sendRaw(Redis::connection(), ['BF.EXISTS', self::BLOOM_FILTER_KEY, (string) $photoId]) === 1;
            } catch (\Throwable $e) {
                // Fall through to set check
            }
        }

        // Fallback to regular set
        return Redis::sIsMember('p:done', $photoId);
    }

    /**
     * Ensure bloom filter exists
     */
    private static function ensureBloomFilter(): void
    {
        if (!self::isBloomAvailable() || self::$bloomInitialized) {
            return;
        }

        try {
            self::sendRaw(Redis::connection(), ['BF.INFO', self::BLOOM_FILTER_KEY]);
        } catch (\Throwable $e) {
            // if key doesn't exist create it (ignore duplicate-key race)
            try {
                self::sendRaw(Redis::connection(), [
                    'BF.RESERVE',
                    self::BLOOM_FILTER_KEY,
                    (string) self::BLOOM_ERROR_RATE,
                    (string) self::BLOOM_CAPACITY,
                ]);
            } catch (\Throwable $ignored) {
                // Another process might have created it
            }
        }

        self::$bloomInitialized = true;
    }

    /**
     * Instead of processing each photo individually,
     * Collect all the data first, then make one Redis call.
     */
    public static function queueBatch(int $userId, Collection $photos): void
    {
        if ($photos->isEmpty()) {
            return;
        }

        // Aggregate all deltas from all photos
        $totalDeltas = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        $totalUploads = 0;
        $totalXp = 0;
        $photoIds = [];

        // Group time series data by date/month
        $timeSeriesByDate = [];
        $timeSeriesByMonth = [];
        $geoScoped = [];

        // Step 1: Collect all changes
        foreach ($photos as $photo) {
            if (self::alreadyProcessed($photo->id)) {
                continue;
            }

            $photoIds[] = $photo->id;
            $deltas = self::extractDeltas($photo);
            $xp = (int) ($photo->xp ?? 0);
            $ts = $photo->created_at ?? now();
            $date = $ts->setTimezone('UTC')->format('Y-m-d');
            $monthTag = '{g}:' . $ts->format('Y-m');

            // Merge deltas
            foreach ($deltas as $type => $items) {
                foreach ($items as $key => $quantity) {
                    $totalDeltas[$type][$key] =
                        ($totalDeltas[$type][$key] ?? 0) + $quantity;
                }
            }

            $totalUploads++;
            $totalXp += $xp;

            // Aggregate time series data
            $timeSeriesByMonth[$monthTag] = [
                'photos' => ($timeSeriesByMonth[$monthTag]['photos'] ?? 0) + 1,
                'xp' => ($timeSeriesByMonth[$monthTag]['xp'] ?? 0) + $xp,
            ];

            // Aggregate geo-scoped data
            foreach (self::geoScopes($photo->country_id, $photo->state_id, $photo->city_id) as $scope) {
                $key = "$scope|$date";
                $geoScoped[$key] = ($geoScoped[$key] ?? 0) + 1;
            }
        }

        if (empty($photoIds)) {
            return; // All photos already processed
        }

        $statsKey = sprintf(self::KEY_STATS, $userId);
        $uTag = "{u:$userId}";

        // Step 2: ONE Redis pipeline for ALL updates
        Redis::pipeline(function($pipe) use (
            $userId, $totalDeltas, $totalUploads, $totalXp, $photoIds,
            $statsKey, $uTag, $timeSeriesByMonth, $geoScoped
        ) {
            // Initialize stats if needed
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp', 0);
            $pipe->hSetNx($statsKey, 'streak', 0);

            // Update stats ONCE with totals
            $pipe->hIncrBy($statsKey, 'uploads', $totalUploads);
            if ($totalXp) {
                $pipe->hIncrByFloat($statsKey, 'xp', $totalXp);
            }

            // Update user counts
            foreach ($totalDeltas['categories'] as $c => $q) {
                $pipe->hIncrBy("$uTag:c", (string)$c, $q);
            }
            foreach ($totalDeltas['objects'] as $o => $q) {
                $pipe->hIncrBy("$uTag:t", (string)$o, $q);
            }
            foreach ($totalDeltas['materials'] as $m => $q) {
                $pipe->hIncrBy("$uTag:m", (string)$m, $q);
            }
            foreach ($totalDeltas['brands'] as $b => $q) {
                $pipe->hIncrBy("$uTag:brands", (string)$b, $q);
            }
            foreach ($totalDeltas['custom_tags'] as $ct => $q) {
                $pipe->hIncrBy("$uTag:custom", (string)$ct, $q);
            }

            // Update global counts
            foreach ($totalDeltas['categories'] as $c => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, (string)$c, $q);
            }
            foreach ($totalDeltas['objects'] as $o => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, (string)$o, $q);
            }
            foreach ($totalDeltas['materials'] as $m => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, (string)$m, $q);
            }
            foreach ($totalDeltas['brands'] as $b => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, (string)$b, $q);
            }

            // Time series data
            foreach ($timeSeriesByMonth as $monthTag => $data) {
                $pipe->hIncrBy("{$monthTag}:t", 'p', $data['photos']);
                if ($data['xp'] > 0) {
                    $pipe->hIncrByFloat("{$monthTag}:t", 'xp', $data['xp']);
                }
            }

            // Geo scoped tracking
            foreach ($geoScoped as $scopeDate => $count) {
                [$scope, $date] = explode('|', $scopeDate, 2);
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, $count);
                $pipe->pExpire($tsKey, self::TS_TTL_MS);
            }

            // Mark all photos as processed
            foreach ($photoIds as $photoId) {
                self::markAsProcessed($pipe, $photoId);
            }
        });

        // Step 3: Handle streak updates separately (complex logic)
        self::updateStreakForBatch($userId, $photos);
    }

    /**
     * Update streak for batch of photos
     */
    private static function updateStreakForBatch(int $userId, Collection $photos): void
    {
        // Get unique dates sorted chronologically
        $photosByDate = $photos
            ->sortBy('created_at')
            ->groupBy(fn($photo) => $photo->created_at->setTimezone('UTC')->format('Y-m-d'));

        if ($photosByDate->isEmpty()) {
            return;
        }

        $statsKey = sprintf(self::KEY_STATS, $userId);

        // Process each date in chronological order
        foreach ($photosByDate as $date => $dailyPhotos) {
            $upTodayKey = "{u:$userId}:up:{$date}";
            $upYesterdayKey = "{u:$userId}:up:" . Carbon::parse($date)->subDay()->format('Y-m-d');

            // Check if we had uploads yesterday
            $hadYesterday = Redis::exists($upYesterdayKey);
            $currentStreak = (int) Redis::hGet($statsKey, 'streak') ?: 0;

            // Update streak
            $newStreak = $hadYesterday ? $currentStreak + 1 : 1;

            // Set today's flag and update streak
            Redis::setex($upTodayKey, self::UPLOAD_FLAG_TTL_SECONDS, '1');
            Redis::hSet($statsKey, 'streak', $newStreak);
        }
    }

    /**
     * Persist metrics with optimized pipeline
     */
    private static function persistMetrics(Photo $photo): void
    {
        $uid = $photo->user_id;
        $xp = (int) ($photo->xp ?? 0);
        $ts = $photo->created_at ?? now();
        $date = $ts->setTimezone('UTC')->format('Y-m-d');
        $monthTag = '{g}:' . $ts->format('Y-m');

        // Extract all deltas from photo
        $deltas = self::extractDeltas($photo);

        $upTodayKey = "{u:$uid}:up:{$date}";
        $upYesterdayKey = "{u:$uid}:up:" . Carbon::parse($date)->subDay()->format('Y-m-d');
        $statsKey = sprintf(self::KEY_STATS, $uid);

        // Single pipeline for all operations
        $results = Redis::pipeline(function ($pipe) use (
            $uid, $xp, $date, $monthTag, $deltas, $photo, $ts,
            $upTodayKey, $upYesterdayKey,
            $statsKey,
        ) {
            $uTag = "{u:$uid}";

            // Streak operations first (so we know their indices)
            $pipe->exists($upYesterdayKey);      // index 0
            $pipe->hGet($statsKey, 'streak');    // index 1

            // Initialize and update stats
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp', 0);
            $pipe->hSetNx($statsKey, 'streak', 0);
            $pipe->hIncrBy($statsKey, 'uploads', 1);

            if ($xp) {
                $pipe->hIncrByFloat($statsKey, 'xp', $xp);
            }

            // Update user counts
            foreach ($deltas['categories'] as $c => $q) {
                $pipe->hIncrBy("$uTag:c", (string)$c, $q);
            }
            foreach ($deltas['objects'] as $o => $q) {
                $pipe->hIncrBy("$uTag:t", (string)$o, $q);
            }
            foreach ($deltas['materials'] as $m => $q) {
                $pipe->hIncrBy("$uTag:m", (string)$m, $q);
            }
            foreach ($deltas['brands'] as $b => $q) {
                $pipe->hIncrBy("$uTag:brands", (string)$b, $q);
            }
            foreach ($deltas['custom_tags'] as $ct => $q) {
                $pipe->hIncrBy("$uTag:custom", (string)$ct, $q);
            }

            // Update global counts
            foreach ($deltas['categories'] as $c => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, (string)$c, $q);
            }
            foreach ($deltas['objects'] as $o => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, (string)$o, $q);
            }
            foreach ($deltas['materials'] as $m => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, (string)$m, $q);
            }
            foreach ($deltas['brands'] as $b => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, (string)$b, $q);
            }

            // Time series data
            $pipe->hIncrBy("{$monthTag}:t", 'p', 1);
            if ($xp) {
                $pipe->hIncrByFloat("{$monthTag}:t", 'xp', $xp);
            }

            // Geo scoped tracking
            foreach (self::geoScopes($photo->country_id, $photo->state_id, $photo->city_id) as $scope) {
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, 1);
                $pipe->pExpire($tsKey, self::TS_TTL_MS);
            }

            // Set today's upload flag
            $pipe->setex($upTodayKey, self::UPLOAD_FLAG_TTL_SECONDS, '1');

            // Mark as processed
            self::markAsProcessed($pipe, $photo->id);
        });

        // Handle streak calculation after pipeline
        $hadYesterday = $results[0];  // exists result
        $oldStreak = (int) ($results[1] ?: 0);  // current streak
        $newStreak = $hadYesterday ? $oldStreak + 1 : 1;

        if ($newStreak !== $oldStreak) {
            Redis::hSet($statsKey, 'streak', $newStreak);
        }
    }

    // ... rest of the methods remain the same (extractDeltas, getUserCounts, etc.)

    /**
     * Extract deltas from photo summary
     */
    private static function extractDeltas(Photo $photo): array
    {
        $deltas = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        foreach ($photo->summary['tags'] ?? [] as $cat => $objs)
        {
            $catId = TagKeyCache::getOrCreateId('category', $cat);

            foreach ($objs as $obj => $data)
            {
                // Ensure quantity is a non-negative integer
                $q = max(0, (int) ($data['quantity'] ?? 0));
                if ($q <= 0) continue;

                $objId = TagKeyCache::getOrCreateId('object', $obj);

                $deltas['categories'][$catId] = ($deltas['categories'][$catId] ?? 0) + $q;
                $deltas['objects'][$objId] = ($deltas['objects'][$objId] ?? 0) + $q;

                foreach ($data['materials'] ?? [] as $material => $matQ) {
                    $matQ = max(0, (int) $matQ);
                    if ($matQ > 0) {
                        $matId = TagKeyCache::getOrCreateId('material', $material);
                        $deltas['materials'][$matId] = ($deltas['materials'][$matId] ?? 0) + $matQ;
                    }
                }

                foreach ($data['brands'] ?? [] as $brand => $brandQ) {
                    $brandQ = max(0, (int) $brandQ);
                    if ($brandQ > 0) {
                        $brandId = TagKeyCache::getOrCreateId('brand', $brand);
                        $deltas['brands'][$brandId] = ($deltas['brands'][$brandId] ?? 0) + $brandQ;
                    }
                }

                foreach ($data['custom_tags'] ?? [] as $customTag => $customQ) {
                    $customQ = max(0, (int) $customQ);
                    if ($customQ > 0) {
                        $tagId = TagKeyCache::getOrCreateId('customTag', $customTag);
                        $deltas['custom_tags'][$tagId] = ($deltas['custom_tags'][$tagId] ?? 0) + $customQ;
                    }
                }
            }
        }

        return $deltas;
    }

    /**
     * Get user's current counts for achievement checking
     */
    public static function getUserCounts(int $userId): array
    {
        $results = Redis::pipeline(function($pipe) use ($userId) {
            $pipe->hGetAll(sprintf(self::KEY_STATS, $userId));
            $pipe->hGetAll(sprintf(self::KEY_CATEGORIES, $userId));
            $pipe->hGetAll(sprintf(self::KEY_OBJECTS, $userId));
            $pipe->hGetAll(sprintf(self::KEY_MATERIALS, $userId));
            $pipe->hGetAll(sprintf(self::KEY_BRANDS, $userId));
            $pipe->hGetAll(sprintf(self::KEY_CUSTOM, $userId));
        });

        return [
            'uploads' => (int) ($results[0]['uploads'] ?? 0),
            'streak' => (int) ($results[0]['streak'] ?? 0),
            'xp' => (float) ($results[0]['xp'] ?? 0),
            'categories' => $results[1] ?: [],
            'objects' => $results[2] ?: [],
            'materials' => $results[3] ?: [],
            'brands' => $results[4] ?: [],
            'custom_tags' => $results[5] ?: [],
        ];
    }

    private static function geoScopes(?int $country, ?int $state, ?int $city): array
    {
        return array_filter([
            '{g}',
            $country ? "c:$country" : null,
            $state ? "s:$state" : null,
            $city ? "ci:$city" : null,
        ]);
    }

    /**
     * Queue batch with change tracking
     * Returns which dimensions changed for this batch
     */
    public static function queueBatchWithTracking(int $userId, Collection $photos): array
    {
        if ($photos->isEmpty()) {
            return [
                'changed_dimensions' => [],
                'previous_counts' => [],
                'new_counts' => []
            ];
        }

        // Get current counts before updates
        $previousCounts = self::getUserCounts($userId);

        // Process the batch normally (your existing method)
        self::queueBatch($userId, $photos);

        // Get new counts after updates
        $newCounts = self::getUserCounts($userId);

        // Track what changed
        $changedDimensions = self::detectChangedDimensions($previousCounts, $newCounts);

        return [
            'changed_dimensions' => $changedDimensions,
            'previous_counts' => $previousCounts,
            'new_counts' => $newCounts
        ];
    }

    /**
     * Detect which dimensions changed between two count sets
     */
    private static function detectChangedDimensions(array $previousCounts, array $newCounts): array
    {
        $changed = [];

        // Check uploads
        if (($newCounts['uploads'] ?? 0) > ($previousCounts['uploads'] ?? 0)) {
            $changed[] = 'uploads';
        }

        // Check streak
        if (($newCounts['streak'] ?? 0) !== ($previousCounts['streak'] ?? 0)) {
            $changed[] = 'streak';
        }

        // Check categories
        if (self::hasArrayChanges($newCounts['categories'] ?? [], $previousCounts['categories'] ?? [])) {
            $changed[] = 'categories';
        }

        // Check objects
        if (self::hasArrayChanges($newCounts['objects'] ?? [], $previousCounts['objects'] ?? [])) {
            $changed[] = 'objects';
        }

        // Check materials
        if (self::hasArrayChanges($newCounts['materials'] ?? [], $previousCounts['materials'] ?? [])) {
            $changed[] = 'materials';
        }

        // Check brands
        if (self::hasArrayChanges($newCounts['brands'] ?? [], $previousCounts['brands'] ?? [])) {
            $changed[] = 'brands';
        }

        // Check custom tags
        if (self::hasArrayChanges($newCounts['custom_tags'] ?? [], $previousCounts['custom_tags'] ?? [])) {
            $changed[] = 'custom_tags';
        }

        return array_unique($changed);
    }

    /**
     * Check if array values have increased or new keys added
     */
    private static function hasArrayChanges(array $new, array $old): bool
    {
        // Check if any existing values increased
        foreach ($new as $key => $value) {
            if ($value > ($old[$key] ?? 0)) {
                return true;
            }
        }

        // Check if any new keys were added
        $newKeys = array_diff_key($new, $old);
        if (!empty($newKeys)) {
            // Make sure the new keys have non-zero values
            foreach ($newKeys as $value) {
                if ($value > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Reset bloom filter state (for testing)
     */
    public static function resetBloomState(): void
    {
        self::$bloomInitialized = null;
        self::$bloomAvailable = null;
    }

    /**
     * Get user counts with string keys instead of numeric IDs (for display)
     */
    public static function getUserCountsWithKeys(int $userId): array
    {
        $counts = self::getUserCounts($userId);

        // Map of count keys to tag dimensions
        $dimensionMap = [
            'categories' => 'category',
            'objects' => 'object',
            'materials' => 'material',
            'brands' => 'brand',
            'custom_tags' => 'customTag'
        ];

        // Convert numeric IDs back to string keys
        foreach ($dimensionMap as $countKey => $dimension) {
            if (!empty($counts[$countKey])) {
                // Extract numeric IDs
                $ids = array_map('intval', array_keys($counts[$countKey]));

                // Get string keys for these IDs
                $keys = TagKeyCache::getKeysForIds($dimension, $ids);

                // Build new array with string keys
                $keyedCounts = [];
                foreach ($counts[$countKey] as $id => $count) {
                    $key = $keys[$id] ?? "unknown_$id";
                    $keyedCounts[$key] = $count;
                }

                $counts[$countKey] = $keyedCounts;
            }
        }

        return $counts;
    }
}
