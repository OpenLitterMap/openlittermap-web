<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class RedisMetricsCollector
{
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years

    /** One bit per UTC day since Unix epoch   ({u:123}:up) */
    private const KEY_UPLOAD_BITMAP = '{u:%d}:up';

    /**
     * Atomically read yesterday’s bit and set today’s bit to 1.
     * Works on a plain Redis connection **or** inside a pipeline.
     *
     * @param  mixed   $connOrPipe  Redis|Pipeline|Predis\Client
     * @param  string  $key         Bitmap key ({u:id}:up)
     * @param  int     $dayIdx      Days since Unix epoch (UTC)
     * @return mixed                • outside pipeline → array [yesterdayBit, todayBit]
     *                              • inside pipeline  → null|bool placeholder
     */
    private static function bitfield($connOrPipe, string $key, int $dayIdx): mixed
    {
        return self::sendRaw(
            $connOrPipe,
            [
                'BITFIELD', $key,
                'GET', 'u1', (string)($dayIdx - 1),   // yesterday?
                'SET', 'u1', (string)$dayIdx, '1'     // mark today
            ]
        );
    }

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

    private const DIM_TO_TABLE = [
        'categories'   => 'categories',
        'objects'      => 'litter_objects',
        'materials'    => 'materials',
        'brands'       => 'brandslist',
        'custom_tags'  => 'custom_tags_new',
    ];

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

    /** Days since Unix epoch for a YYYY-MM-DD UTC string */
    private static function dayIndex(string $date): int
    {
        static $epoch;   // cache Carbon instance
        $epoch ??= Carbon::createFromTimestampUTC(0);
        return (int)$epoch->diffInDays(Carbon::parse($date, 'UTC'));
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
        if ($photos->isEmpty()) return;

        // 1. Dedupe and bloom check
        $ids = $photos->pluck('id')->filter()->unique()->values()->all();
        $processedMap = array_combine($ids, self::bloomCheckMany($ids));
        $photos = $photos->reject(fn($p) => $processedMap[$p->id] ?? false);

        if ($photos->isEmpty()) return;

        // 2. Aggregate all data
        $delta = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        $stringTags = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => []
        ];

        $totalUploads = 0;
        $totalXp = 0;
        $photoIds = [];
        $dateGroups = [];
        $timeSeriesByMonth = [];
        $geoScoped = [];

        foreach ($photos as $photo) {
            $photoIds[] = $photo->id;
            $totalUploads++;
            $xp = (int) ($photo->xp ?? 0);
            $totalXp += $xp;

            $ts   = ($photo->created_at ?? now())->setTimezone('UTC');
            $date = $ts->format('Y-m-d');
            $monthTag = '{g}:' . $ts->format('Y-m');

            // Track dates for streak
            $dateGroups[$date] = true;

            // Aggregate time series by month
            $timeSeriesByMonth[$monthTag] = [
                'photos' => ($timeSeriesByMonth[$monthTag]['photos'] ?? 0) + 1,
                'xp' => ($timeSeriesByMonth[$monthTag]['xp'] ?? 0) + $xp,
            ];

            // Aggregate geo-scoped data
            foreach (self::geoScopes($photo->country_id, $photo->state_id, $photo->city_id) as $scope) {
                $key = "$scope|$date";
                $geoScoped[$key] = ($geoScoped[$key] ?? 0) + 1;
            }

            // Extract tags from summary
            foreach ($photo->summary['tags'] ?? [] as $catStr => $objects) {
                $stringTags['categories'][$catStr] = true;

                foreach ($objects as $objStr => $data) {
                    $q = (int) ($data['quantity'] ?? 0);
                    if ($q <= 0) continue;

                    $stringTags['objects'][$objStr] = true;

                    $delta['categories'][$catStr] = ($delta['categories'][$catStr] ?? 0) + $q;
                    $delta['objects'][$objStr] = ($delta['objects'][$objStr] ?? 0) + $q;

                    foreach ($data['materials'] ?? [] as $matStr => $matQ) {
                        $matQ = (int) $matQ;
                        if ($matQ > 0) {
                            $stringTags['materials'][$matStr] = true;
                            $delta['materials'][$matStr] = ($delta['materials'][$matStr] ?? 0) + $matQ;
                        }
                    }

                    foreach ($data['brands'] ?? [] as $brStr => $brQ) {
                        $brQ = (int) $brQ;
                        if ($brQ > 0) {
                            $stringTags['brands'][$brStr] = true;
                            $delta['brands'][$brStr] = ($delta['brands'][$brStr] ?? 0) + $brQ;
                        }
                    }

                    foreach ($data['custom_tags'] ?? [] as $ctStr => $ctQ) {
                        $ctQ = (int) $ctQ;
                        if ($ctQ > 0) {
                            $stringTags['custom_tags'][$ctStr] = true;
                            $delta['custom_tags'][$ctStr] = ($delta['custom_tags'][$ctStr] ?? 0) + $ctQ;
                        }
                    }
                }
            }
        }

        // 3. Batch resolve all tag strings to IDs
        $idMaps = [];
        foreach ($stringTags as $dim => $tagSet) {
            $idMaps[$dim] = self::resolveTags(array_keys($tagSet), $dim);
        }

        // 4. Convert string keys to numeric IDs
        $numericDeltas = [];
        foreach ($delta as $dim => $pairs) {
            $numericDeltas[$dim] = [];
            foreach ($pairs as $stringKey => $quantity) {
                $id = $idMaps[$dim][$stringKey] ?? null;
                if ($id === null) {
                    Log::warning("Tag not found in {$dim}: {$stringKey}");
                    continue;
                }
                $numericDeltas[$dim][$id] = $quantity;
            }
        }

        // 5. Single pipeline for all updates
        $statsKey = sprintf(self::KEY_STATS, $userId);
        $uTag = "{u:$userId}";

        Redis::pipeline(function($pipe) use (
            $statsKey, $uTag, $numericDeltas, $totalUploads, $totalXp,
            $photoIds, $timeSeriesByMonth, $geoScoped
        ) {
            // Update stats
            $pipe->hIncrBy($statsKey, 'uploads', $totalUploads);
            if ($totalXp > 0) {
                $pipe->hIncrByFloat($statsKey, 'xp', $totalXp);
            }

            // Update user counts
            foreach ($numericDeltas['categories'] as $id => $q) {
                $pipe->hIncrBy("$uTag:c", (string)$id, $q);
            }
            foreach ($numericDeltas['objects'] as $id => $q) {
                $pipe->hIncrBy("$uTag:t", (string)$id, $q);
            }
            foreach ($numericDeltas['materials'] as $id => $q) {
                $pipe->hIncrBy("$uTag:m", (string)$id, $q);
            }
            foreach ($numericDeltas['brands'] as $id => $q) {
                $pipe->hIncrBy("$uTag:brands", (string)$id, $q);
            }
            foreach ($numericDeltas['custom_tags'] as $id => $q) {
                $pipe->hIncrBy("$uTag:custom", (string)$id, $q);
            }

            // Update global counts
            foreach ($numericDeltas['categories'] as $id => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, (string)$id, $q);
            }
            foreach ($numericDeltas['objects'] as $id => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, (string)$id, $q);
            }
            foreach ($numericDeltas['materials'] as $id => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, (string)$id, $q);
            }
            foreach ($numericDeltas['brands'] as $id => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, (string)$id, $q);
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

            // Mark photos as processed
            self::bloomAddMany($pipe, $photoIds);
        });

        // 6. Handle streak updates for each date
        $sortedDates = array_keys($dateGroups);
        sort($sortedDates);

        foreach ($sortedDates as $date) {
            self::updateStreakForDate($userId, $date);
        }
    }

    /**
     * Mark one UTC date and recompute streak for the user.
     *
     * @param int    $userId user id
     * @param string $date   YYYY-MM-DD in UTC
     */
    private static function updateStreakForDate(int $userId, string $date): void
    {
        $statsKey   = sprintf(self::KEY_STATS, $userId);
        $bitmapKey  = sprintf(self::KEY_UPLOAD_BITMAP, $userId);
        $dayIdx     = self::dayIndex($date);

        // Atomically read yesterday + set today
        $res = self::bitfield(Redis::connection(), $bitmapKey, $dayIdx);

        $hadYesterday = ($res[0] ?? 0) === 1;
        $curStreak    = (int) Redis::hGet($statsKey, 'streak') ?: 0;
        $newStreak    = $hadYesterday ? $curStreak + 1 : 1;

        if ($newStreak !== $curStreak) {
            Redis::hSet($statsKey, 'streak', $newStreak);
        }
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

        // Process each date in chronological order
        foreach ($photosByDate as $date => $dailyPhotos) {
            self::updateStreakForDate($userId, $date);
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

        $statsKey = sprintf(self::KEY_STATS, $uid);

        // Single pipeline for all operations
        $results = Redis::pipeline(function ($pipe) use (
            $uid, $xp, $date, $monthTag, $deltas, $photo, $ts, $statsKey
        ) {
            $uTag = "{u:$uid}";

            // Initialize and update stats
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

            // Mark as processed
            self::markAsProcessed($pipe, $photo->id);
        });

        // Handle streak calculation after pipeline
        self::updateStreakForDate($uid, $date);
    }

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
            'categories'  => self::castValues($results[1] ?: []),
            'objects'     => self::castValues($results[2] ?: []),
            'materials'   => self::castValues($results[3] ?: []),
            'brands'      => self::castValues($results[4] ?: []),
            'custom_tags' => self::castValues($results[5] ?: []),
        ];
    }

    // cast every hash value to int
    private static function castValues(array $hash): array
    {
        // preserve keys, cast values
        foreach ($hash as $k => $v) {
            $hash[$k] = (int) $v;
        }
        return $hash;
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
     * Get user counts with string keys instead of numeric IDs (for display)
     */
    public static function getUserCountsWithKeys(int $userId): array
    {
        $counts = self::getUserCounts($userId);

        foreach (self::DIM_TO_TABLE as $dimension => $table) {
            if (empty($counts[$dimension])) {           // <-- use dimension!
                continue;
            }

            $ids  = array_map('intval', array_keys($counts[$dimension]));
            $keys = TagKeyCache::keysBatch($table, $ids);   // id ⇒ key

            $pretty = [];
            foreach ($counts[$dimension] as $id => $cnt) {
                $pretty[$keys[$id] ?? "unknown_$id"] = $cnt;
            }
            $counts[$dimension] = $pretty;
        }

        return $counts;
    }

    /* --------------------------------------------------------------------- */
    /* Tag-string → id resolution in bulk                                    */
    /* --------------------------------------------------------------------- */

    private static function resolveTags(array $strings, string $dimension): array
    {
        if (!$strings) return [];
        $table = self::DIM_TO_TABLE[$dimension];
        // [key => id]
        return TagKeyCache::idsBatch($table, array_values($strings));
    }


    /* --------------------------------------------------------------------- */
    /* Bloom helpers                                                         */
    /* --------------------------------------------------------------------- */

    private static function bloomCheckMany(array $ids): array
    {
        if (!$ids) return [];
        if (self::isBloomAvailable()) {
            return self::sendRaw(
                Redis::connection(),
                array_merge(['BF.MEXISTS', self::BLOOM_FILTER_KEY], $ids)
            );
        }
        return Redis::command('SMISMEMBER', array_merge(['p:done'], $ids));
    }

    private static function bloomAddMany($pipe, array $ids): void
    {
        if (!$ids) return;
        if (self::isBloomAvailable()) {
            self::sendRaw($pipe,
                array_merge(['BF.MADD', self::BLOOM_FILTER_KEY], $ids)
            );
        } else {
            $pipe->sAdd('p:done', ...$ids);
        }
    }

    /**
     * Reset bloom filter state (for testing)
     */
    public static function resetBloomState(): void
    {
        self::$bloomInitialized = null;
        self::$bloomAvailable = null;
    }
}
