<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Enums\Dimension;
use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class RedisMetricsCollector
{
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years

    /** One bit per UTC day since Unix epoch ({u:123}:up) */
    private const KEY_UPLOAD_BITMAP = '{u:%d}:up';

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

    private const DIM_TO_TABLE = [
        'categories'   => 'categories',
        'objects'      => 'litter_objects',
        'materials'    => 'materials',
        'brands'       => 'brandslist',
        'custom_tags'  => 'custom_tags_new',
    ];

    private const PLURAL_TO_ENUM = [
        'objects'      => Dimension::LITTER_OBJECT,
        'categories'   => Dimension::CATEGORY,
        'materials'    => Dimension::MATERIAL,
        'brands'       => Dimension::BRAND,
        'custom_tags'  => Dimension::CUSTOM_TAG,
    ];

    /**
     * Persist all Redis metrics for a single photo.
     */
    public static function queue(Photo $photo): void
    {
        $updated = Photo::where('id', $photo->id)
            ->whereNull('processed_at')
            ->update(['processed_at' => now()]);

        if ($updated === 0) {
            return;
        }

        self::persistMetrics($photo);
    }

    /**
     * Queue batch with optimized deduplication
     */
    public static function queueBatch(int $userId, Collection $photos): void
    {
        if ($photos->isEmpty()) return;


        // 1. Grab the candidate IDs
        $ids = $photos->pluck('id')->all();

        // 2. Atomically mark as processed and keep the ones we actually changed
        $now = now();
        $affected = Photo::whereNull('processed_at')
            ->whereIn('id', $ids)
            ->update(['processed_at' => $now]);

        if ($affected === 0) {
            return;                 // nothing new
        }

        // 3. Pick only the photos we really updated
        $toProcess = $photos->filter(
            fn ($p) => is_null($p->processed_at)   // value we had in memory
                || $p->processed_at < $now     // updated right now
        );

        self::processBatchMetrics($userId, $toProcess);
    }

    /**
     * Process batch metrics after deduplication
     */
    private static function processBatchMetrics(int $userId, Collection $photos): void
    {
        // Aggregate all data
        $aggregated = self::aggregatePhotoData($photos);

        // Batch resolve tags
        $idMaps = self::resolveAllTags($aggregated['stringTags']);

        // Convert to numeric IDs
        $numericDeltas = self::convertToNumericDeltas($aggregated['deltas'], $idMaps);

        // Execute Redis updates
        self::executeRedisUpdates($userId, $aggregated, $numericDeltas);

        // Update streaks
        self::updateStreaksForDates($userId, array_keys($aggregated['dateGroups']));
    }

    /**
     * Aggregate data from photos with type safety
     */
    private static function aggregatePhotoData(Collection $photos): array
    {
        $result = [
            'deltas' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ],
            'stringTags' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
                'custom_tags' => []
            ],
            'totalUploads' => 0,
            'totalXp' => 0,
            'photoIds' => [],
            'dateGroups' => [],
            'timeSeriesByMonth' => [],
            'geoScoped' => [],
            'photosByYear' => []
        ];

        foreach ($photos as $photo) {
            $result['photoIds'][] = $photo->id;
            $result['totalUploads']++;
            $xp = (int) ($photo->xp ?? 0);
            $result['totalXp'] += $xp;

            $ts = ($photo->created_at ?? now())->setTimezone('UTC');
            $date = $ts->format('Y-m-d');
            $monthTag = '{g}:' . $ts->format('Y-m');

            // Track dates
            $result['dateGroups'][$date] = true;

            // Time series
            $result['timeSeriesByMonth'][$monthTag] = [
                'photos' => ($result['timeSeriesByMonth'][$monthTag]['photos'] ?? 0) + 1,
                'xp' => ($result['timeSeriesByMonth'][$monthTag]['xp'] ?? 0) + $xp,
            ];

            // Geo scoped
            foreach (self::geoScopes($photo->country_id, $photo->state_id, $photo->city_id) as $scope) {
                $key = "$scope|$date";
                $result['geoScoped'][$key] = ($result['geoScoped'][$key] ?? 0) + 1;
            }

            // Extract tags with type safety
            self::extractTagsFromPhoto($photo, $result['deltas'], $result['stringTags']);
        }

        return $result;
    }

    /**
     * Extract tags from a single photo with type validation
     */
    private static function extractTagsFromPhoto(Photo $photo, array &$deltas, array &$stringTags): void
    {
        foreach ($photo->summary['tags'] ?? [] as $catStr => $objects) {
            if (!is_string($catStr)) {
                Log::warning("Invalid category type in photo {$photo->id}", ['category' => $catStr]);
                continue;
            }

            $stringTags['categories'][$catStr] = true;

            if (!is_array($objects)) {
                continue;
            }

            foreach ($objects as $objStr => $data) {
                if (!is_string($objStr)) {
                    Log::warning("Invalid object type in photo {$photo->id}", ['object' => $objStr]);
                    continue;
                }

                $q = (int) ($data['quantity'] ?? 0);
                if ($q <= 0) continue;

                $stringTags['objects'][$objStr] = true;
                $deltas['categories'][$catStr] = ($deltas['categories'][$catStr] ?? 0) + $q;
                $deltas['objects'][$objStr] = ($deltas['objects'][$objStr] ?? 0) + $q;

                // Process sub-dimensions
                self::processSubDimension('materials', $data['materials'] ?? [], $deltas, $stringTags);
                self::processSubDimension('brands', $data['brands'] ?? [], $deltas, $stringTags);
                self::processSubDimension('custom_tags', $data['custom_tags'] ?? [], $deltas, $stringTags);
            }
        }
    }

    /**
     * Process sub-dimension tags
     */
    private static function processSubDimension(string $dimension, array $items, array &$deltas, array &$stringTags): void
    {
        foreach ($items as $key => $quantity) {
            if (!is_string($key)) continue;

            $q = (int) $quantity;
            if ($q > 0) {
                $stringTags[$dimension][$key] = true;
                $deltas[$dimension][$key] = ($deltas[$dimension][$key] ?? 0) + $q;
            }
        }
    }

    /**
     * Execute all Redis updates in a single pipeline
     */
    private static function executeRedisUpdates(int $userId, array $aggregated, array $numericDeltas): void
    {
        $statsKey = sprintf(self::KEY_STATS, $userId);

        Redis::pipeline(function($pipe) use (
            $statsKey, $numericDeltas, $aggregated, $userId
        ) {
            // Update stats
            $pipe->hIncrBy($statsKey, 'uploads', $aggregated['totalUploads']);
            if ($aggregated['totalXp'] > 0) {
                $pipe->hIncrByFloat($statsKey, 'xp', $aggregated['totalXp']);
            }

            // Update user and global counts for each dimension
            foreach (['categories', 'objects', 'materials', 'brands', 'custom_tags'] as $dim) {
                $userKey = self::getUserKeyForDimension($userId, $dim);
                $globalKey = self::getGlobalKeyForDimension($dim);

                foreach ($numericDeltas[$dim] as $id => $quantity) {
                    $pipe->hIncrBy($userKey, (string)$id, $quantity);
                    if ($globalKey) {
                        $pipe->hIncrBy($globalKey, (string)$id, $quantity);
                    }
                }
            }

            // Time series updates
            foreach ($aggregated['timeSeriesByMonth'] as $monthTag => $data) {
                $pipe->hIncrBy("{$monthTag}:t", 'p', $data['photos']);
                if ($data['xp'] > 0) {
                    $pipe->hIncrByFloat("{$monthTag}:t", 'xp', $data['xp']);
                }
            }

            // Geo scoped updates
            foreach ($aggregated['geoScoped'] as $scopeDate => $count) {
                [$scope, $date] = explode('|', $scopeDate, 2);
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, $count);
                $pipe->pExpire($tsKey, self::TS_TTL_MS);
            }
        });
    }

    /**
     * Get user key for dimension
     */
    private static function getUserKeyForDimension(int $userId, string $dimension): string
    {
        $keyMap = [
            'categories' => self::KEY_CATEGORIES,
            'objects' => self::KEY_OBJECTS,
            'materials' => self::KEY_MATERIALS,
            'brands' => self::KEY_BRANDS,
            'custom_tags' => self::KEY_CUSTOM,
        ];

        return sprintf($keyMap[$dimension], $userId);
    }

    /**
     * Get global key for dimension
     */
    private static function getGlobalKeyForDimension(string $dimension): ?string
    {
        $keyMap = [
            'categories' => self::GLOBAL_CATEGORIES,
            'objects' => self::GLOBAL_OBJECTS,
            'materials' => self::GLOBAL_MATERIALS,
            'brands' => self::GLOBAL_BRANDS,
            'custom_tags' => null, // No global tracking for custom tags
        ];

        return $keyMap[$dimension];
    }

    /**
     * Update streaks for multiple dates
     */
    private static function updateStreaksForDates(int $userId, array $dates): void
    {
        sort($dates);
        foreach ($dates as $date) {
            self::updateStreakForDate($userId, $date);
        }
    }

    /**
     * Days since Unix epoch for a YYYY-MM-DD UTC string
     */
    private static function dayIndex(string $date): int
    {
        static $epoch;
        $epoch ??= Carbon::createFromTimestampUTC(0);
        return (int)$epoch->diffInDays(Carbon::parse($date, 'UTC'));
    }

    /**
     * Atomically read yesterday's bit and set today's bit to 1.
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
        Redis::pipeline(function ($pipe) use (
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
     * Get geographic scopes for a photo
     */
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
     * Convert string-based deltas to numeric IDs
     */
    private static function convertToNumericDeltas(array $deltas, array $idMaps): array
    {
        $numericDeltas = [];

        foreach ($deltas as $dim => $pairs) {
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

        return $numericDeltas;
    }

    /**
     * Batch resolve all tag strings to IDs
     */
    private static function resolveAllTags(array $stringTags): array
    {
        $idMaps = [];

        foreach ($stringTags as $dim => $tagSet) {

            /** 1. Which SQL table holds this dimension? */
            $table = self::DIM_TO_TABLE[$dim] ?? null;
            if (!$table) {
                $idMaps[$dim] = [];
                continue;
            }

            $dimension = Dimension::tryFrom($dim)
                ?? self::PLURAL_TO_ENUM[$dim]
                ?? null;

            // Skip unknown dimension keys (should not happen)
            if (!$dimension) {
                $idMaps[$dim] = [];
                continue;
            }

            $strings = array_keys($tagSet);
            if ($strings === []) {
                $idMaps[$dim] = [];
                continue;
            }

            // 2. Ask the cache for ids - PASS THE TABLE NAME, NOT THE DIMENSION VALUE
            $raw = TagKeyCache::idsBatch($table, $strings);
            $firstKey = array_key_first($raw);

            // 3. Orient to string → id
            $idMaps[$dim] = is_numeric($firstKey) ? array_flip($raw) : $raw;
        }

        return $idMaps;
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

    /**
     * Cast every hash value to int
     */
    private static function castValues(array $hash): array
    {
        foreach ($hash as $k => $v) {
            $hash[$k] = (int) $v;
        }
        return $hash;
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

        // Process the batch normally
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
            if (empty($counts[$dimension])) {
                continue;
            }

            $ids  = array_map('intval', array_keys($counts[$dimension]));
            $keys = TagKeyCache::keysBatch($table, $ids);

            $pretty = [];
            foreach ($counts[$dimension] as $id => $cnt) {
                $pretty[$keys[$id] ?? "unknown_$id"] = $cnt;
            }
            $counts[$dimension] = $pretty;
        }

        return $counts;
    }
}
