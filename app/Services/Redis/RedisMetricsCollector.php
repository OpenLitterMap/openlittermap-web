<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * RedisMetricsCollector - Production-ready metrics tracking service
 *
 * Tracks user and location metrics from photo uploads with:
 * - O(1) reads via denormalized totals
 * - Correct streak tracking for non-contiguous dates
 * - Memory-bounded ranking ZSETs with automatic trimming
 * - Data validation to prevent abuse
 * - Redis Cluster compatibility via hash-tags
 *
 * Key Structure:
 * - {u:ID}:stats         - User stats (uploads, xp, streak, litter)
 * - {u:ID}:up            - Bitmap for daily uploads (streak calculation)
 * - {u:ID}:c/t/m/brands  - User category/object/material/brand counts
 * - {g}:stats            - Global denormalized totals
 * - {c:ID}:stats         - Location denormalized totals
 * - {c:ID}:rank:s:litter - State rankings within country
 * - {scope}:t:p          - Daily time series (2-year TTL)
 */
final class RedisMetricsCollector
{
    // Configuration constants
    private const MAX_ITEMS_PER_PHOTO = 1000;
    private const MAX_QUANTITY_PER_ITEM = 100;
    private const MAX_RANKING_ITEMS = 500;
    private const CHUNK_SIZE = 500;

    // TTL settings
    private const TIME_SERIES_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years in ms
    private const MONTHLY_RANKING_TTL = 60 * 60 * 24 * 90; // 90 days in seconds

    // Key patterns
    private const USER_STATS_KEY = '{u:%d}:stats';
    private const USER_BITMAP_KEY = '{u:%d}:up';
    private const USER_CATEGORIES_KEY = '{u:%d}:c';
    private const USER_OBJECTS_KEY = '{u:%d}:t';
    private const USER_MATERIALS_KEY = '{u:%d}:m';
    private const USER_BRANDS_KEY = '{u:%d}:brands';
    private const USER_CUSTOM_KEY = '{u:%d}:custom';

    private const GLOBAL_STATS = '{g}:stats';
    private const GLOBAL_CATEGORIES = '{g}:c';
    private const GLOBAL_OBJECTS = '{g}:t';
    private const GLOBAL_MATERIALS = '{g}:m';
    private const GLOBAL_BRANDS = '{g}:brands';

    // Ranking ZSETs
    private const GLOBAL_COUNTRY_LITTER_RANKING = '{g}:rank:c:litter';
    private const GLOBAL_COUNTRY_PHOTOS_RANKING = '{g}:rank:c:photos';

    /**
     * Process a single photo
     */
    public static function queue(Photo $photo): void
    {
        // Mark as processed atomically
        $updated = Photo::where('id', $photo->id)
            ->whereNull('processed_at')
            ->update(['processed_at' => now('UTC')]);

        if ($updated === 0) {
            return; // Already processed
        }

        self::processSinglePhoto($photo);
    }

    /**
     * Process a batch of photos with deduplication
     */
    public static function queueBatch(int $userId, Collection $photos): void
    {
        if ($photos->isEmpty()) {
            return;
        }

        // Deduplicate and mark as processed
        $unprocessedIds = self::markPhotosAsProcessed($photos);

        if (empty($unprocessedIds)) {
            return;
        }

        // Process only newly marked photos
        $photosToProcess = $photos->whereIn('id', $unprocessedIds)->keyBy('id')->values();
        self::processBatch($userId, $photosToProcess);
    }

    /**
     * Mark photos as processed and return IDs of newly processed ones
     */
    private static function markPhotosAsProcessed(Collection $photos): array
    {
        $ids = $photos->pluck('id')->unique()->values()->all();

        // Check which are already processed
        $processed = Photo::whereIn('id', $ids)
            ->whereNotNull('processed_at')
            ->pluck('id')
            ->all();

        $unprocessedIds = array_diff($ids, $processed);

        if (empty($unprocessedIds)) {
            return [];
        }

        // Mark as processed atomically
        $updated = Photo::whereIn('id', $unprocessedIds)
            ->whereNull('processed_at')
            ->update(['processed_at' => now('UTC')]);

        return $updated > 0 ? $unprocessedIds : [];
    }

    /**
     * Process a single photo with all metrics updates
     */
    private static function processSinglePhoto(Photo $photo): void
    {
        $userId = $photo->user_id;
        $xp = (int) ($photo->xp ?? 0);
        $createdAt = $photo->created_at ?? now('UTC');
        $date = $createdAt->setTimezone('UTC')->format('Y-m-d');
        $month = $createdAt->format('Y-m');

        // Extract and validate tag deltas
        $deltas = self::extractValidatedDeltas($photo);
        $litterTotal = array_sum($deltas['objects']);

        // Get all location scopes
        $scopes = self::getLocationScopes($photo->country_id, $photo->state_id, $photo->city_id);

        // Prepare ranking updates
        $rankingUpdates = self::prepareRankingUpdates($deltas, $scopes, $month);

        // Execute all updates in a single pipeline
        Redis::pipeline(function ($pipe) use (
            $userId, $xp, $date, $month, $deltas, $photo, $litterTotal, $scopes, $rankingUpdates
        ) {
            // User metrics
            self::updateUserMetrics($pipe, $userId, $xp, $litterTotal, $deltas);

            // Global metrics
            self::updateGlobalMetrics($pipe, $month, $xp, $litterTotal, $deltas);

            // Location metrics
            foreach ($scopes as $scope) {
                if ($scope !== '{g}') {
                    self::updateLocationMetrics($pipe, $scope, $month, $date, $xp, $litterTotal, $deltas, $userId);
                }
            }

            // Update all ranking ZSETs
            self::updateRankings($pipe, $rankingUpdates, $photo, $litterTotal);
        });

        // Post-pipeline operations
        self::trimRankingZSets($rankingUpdates);
        self::updateUserStreak($userId, $date);
    }

    /**
     * Process a batch of photos efficiently
     */
    private static function processBatch(int $userId, Collection $photos): void
    {
        if ($photos->isEmpty()) {
            return;
        }

        // Aggregate all metrics across the batch
        $aggregated = self::aggregateBatchMetrics($photos);

        // Execute batch updates in pipeline
        Redis::pipeline(function ($pipe) use ($userId, $aggregated) {
            // User metrics
            self::updateUserMetrics($pipe, $userId, $aggregated['totalXp'], $aggregated['totalLitter'], $aggregated['userDeltas']);

            // Global metrics
            foreach ($aggregated['globalMonthly'] as $month => $data) {
                self::updateGlobalMetrics($pipe, $month, $data['xp'], $data['litter'], $aggregated['globalDeltas']);
            }

            // Location metrics
            foreach ($aggregated['locationData'] as $scope => $data) {
                self::updateLocationMetricsBatch($pipe, $scope, $data, $aggregated['locationUsers'][$scope] ?? []);
            }

            // Daily time series
            foreach ($aggregated['dailyCounts'] as $scopeDate => $count) {
                [$scope, $date] = explode('|', $scopeDate, 2);
                $pipe->hIncrBy("$scope:t:p", $date, $count);
                $pipe->pExpire("$scope:t:p", self::TIME_SERIES_TTL_MS);
            }

            // Update rankings
            self::updateBatchRankings($pipe, $aggregated['rankingUpdates']);
        });

        // Post-pipeline operations
        self::trimBatchRankingZSets($aggregated['rankingUpdates']);

        // Update streak for today only
        $today = now('UTC')->format('Y-m-d');
        if (isset($aggregated['dates'][$today])) {
            self::updateUserStreak($userId, $today);
        }
    }

    /**
     * Extract and validate deltas from photo
     */
    private static function extractValidatedDeltas(Photo $photo): array
    {
        $deltas = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        $totalItems = 0;

        foreach ($photo->summary['tags'] ?? [] as $categoryKey => $objects) {
            $categoryId = TagKeyCache::getOrCreateId('category', $categoryKey);

            foreach ($objects as $objectKey => $data) {
                // Validate and cap quantity
                $quantity = min(self::MAX_QUANTITY_PER_ITEM, max(0, (int) ($data['quantity'] ?? 0)));
                if ($quantity <= 0) continue;

                // Check total items cap
                $totalItems += $quantity;
                if ($totalItems > self::MAX_ITEMS_PER_PHOTO) {
                    Log::warning('Photo exceeded max items cap', [
                        'photo_id' => $photo->id,
                        'total_attempted' => $totalItems
                    ]);
                    break 2;
                }

                $objectId = TagKeyCache::getOrCreateId('object', $objectKey);

                $deltas['categories'][$categoryId] = ($deltas['categories'][$categoryId] ?? 0) + $quantity;
                $deltas['objects'][$objectId] = ($deltas['objects'][$objectId] ?? 0) + $quantity;

                // Process sub-dimensions
                self::processSubDimension($data['materials'] ?? [], 'material', $deltas['materials']);
                self::processSubDimension($data['brands'] ?? [], 'brand', $deltas['brands']);
                self::processSubDimension($data['custom_tags'] ?? [], 'customTag', $deltas['custom_tags']);
            }
        }

        return $deltas;
    }

    /**
     * Process sub-dimension tags with validation
     */
    private static function processSubDimension(array $items, string $dimension, array &$deltas): void
    {
        foreach ($items as $key => $quantity) {
            $quantity = min(self::MAX_QUANTITY_PER_ITEM, max(0, (int) $quantity));
            if ($quantity > 0) {
                $id = TagKeyCache::getOrCreateId($dimension, $key);
                $deltas[$id] = ($deltas[$id] ?? 0) + $quantity;
            }
        }
    }

    /**
     * Update user metrics
     */
    private static function updateUserMetrics($pipe, int $userId, int $xp, int $litterTotal, array $deltas): void
    {
        $statsKey = sprintf(self::USER_STATS_KEY, $userId);
        $userTag = "{u:$userId}";

        // Update stats with denormalized litter total
        $pipe->hIncrBy($statsKey, 'uploads', 1);
        if ($xp > 0) {
            $pipe->hIncrBy($statsKey, 'xp', $xp);
        }
        if ($litterTotal > 0) {
            $pipe->hIncrBy($statsKey, 'litter', $litterTotal);
        }

        // Update dimension counts
        foreach ($deltas['categories'] as $id => $count) {
            $pipe->hIncrBy("$userTag:c", (string)$id, $count);
        }
        foreach ($deltas['objects'] as $id => $count) {
            $pipe->hIncrBy("$userTag:t", (string)$id, $count);
        }
        foreach ($deltas['materials'] as $id => $count) {
            $pipe->hIncrBy("$userTag:m", (string)$id, $count);
        }
        foreach ($deltas['brands'] as $id => $count) {
            $pipe->hIncrBy("$userTag:brands", (string)$id, $count);
        }
        foreach ($deltas['custom_tags'] as $id => $count) {
            $pipe->hIncrBy("$userTag:custom", (string)$id, $count);
        }
    }

    /**
     * Update global metrics
     */
    private static function updateGlobalMetrics($pipe, string $month, int $xp, int $litterTotal, array $deltas): void
    {
        // Denormalized global stats
        $pipe->hIncrBy(self::GLOBAL_STATS, 'photos', 1);
        if ($litterTotal > 0) {
            $pipe->hIncrBy(self::GLOBAL_STATS, 'litter', $litterTotal);
        }

        // Global dimension counts
        foreach ($deltas['categories'] as $id => $count) {
            $pipe->hIncrBy(self::GLOBAL_CATEGORIES, (string)$id, $count);
        }
        foreach ($deltas['objects'] as $id => $count) {
            $pipe->hIncrBy(self::GLOBAL_OBJECTS, (string)$id, $count);
        }
        foreach ($deltas['materials'] as $id => $count) {
            $pipe->hIncrBy(self::GLOBAL_MATERIALS, (string)$id, $count);
        }
        foreach ($deltas['brands'] as $id => $count) {
            $pipe->hIncrBy(self::GLOBAL_BRANDS, (string)$id, $count);
        }

        // Monthly aggregates
        $monthKey = "{g}:$month:t";
        $pipe->hIncrBy($monthKey, 'p', 1);
        if ($xp > 0) {
            $pipe->hIncrBy($monthKey, 'xp', $xp);
        }
        if ($litterTotal > 0) {
            $pipe->hIncrBy($monthKey, 'l', $litterTotal);
        }
    }

    /**
     * Update location metrics
     */
    private static function updateLocationMetrics($pipe, string $scope, string $month, string $date, int $xp, int $litterTotal, array $deltas, int $userId): void
    {
        // Denormalized location stats
        $pipe->hIncrBy("$scope:stats", 'photos', 1);
        if ($litterTotal > 0) {
            $pipe->hIncrBy("$scope:stats", 'litter', $litterTotal);
        }

        // Add user to contributors set
        $pipe->sAdd("$scope:users", (string)$userId);

        // Location dimension counts
        foreach ($deltas['categories'] as $id => $count) {
            $pipe->hIncrBy("$scope:c", (string)$id, $count);
        }
        foreach ($deltas['objects'] as $id => $count) {
            $pipe->hIncrBy("$scope:t", (string)$id, $count);
        }
        foreach ($deltas['materials'] as $id => $count) {
            $pipe->hIncrBy("$scope:m", (string)$id, $count);
        }
        foreach ($deltas['brands'] as $id => $count) {
            $pipe->hIncrBy("$scope:brands", (string)$id, $count);
        }

        // Daily time series
        $pipe->hIncrBy("$scope:t:p", $date, 1);
        $pipe->pExpire("$scope:t:p", self::TIME_SERIES_TTL_MS);

        // Monthly aggregates
        $monthKey = "$scope:$month:t";
        $pipe->hIncrBy($monthKey, 'p', 1);
        if ($xp > 0) {
            $pipe->hIncrBy($monthKey, 'xp', $xp);
        }
        if ($litterTotal > 0) {
            $pipe->hIncrBy($monthKey, 'l', $litterTotal);
        }
    }

    /**
     * Update location metrics for batch
     */
    private static function updateLocationMetricsBatch($pipe, string $scope, array $data, array $userIds): void
    {
        // Update stats
        $pipe->hIncrBy("$scope:stats", 'photos', $data['photos']);
        $pipe->hIncrBy("$scope:stats", 'litter', $data['litter']);

        // Add users in chunks
        if (!empty($userIds)) {
            $chunks = array_chunk(array_map('strval', array_keys($userIds)), self::CHUNK_SIZE);
            foreach ($chunks as $chunk) {
                $pipe->sAdd("$scope:users", ...$chunk);
            }
        }

        // Update dimensions
        foreach ($data['categories'] as $id => $count) {
            $pipe->hIncrBy("$scope:c", (string)$id, $count);
        }
        foreach ($data['objects'] as $id => $count) {
            $pipe->hIncrBy("$scope:t", (string)$id, $count);
        }
        foreach ($data['materials'] as $id => $count) {
            $pipe->hIncrBy("$scope:m", (string)$id, $count);
        }
        foreach ($data['brands'] as $id => $count) {
            $pipe->hIncrBy("$scope:brands", (string)$id, $count);
        }

        // Monthly aggregates
        foreach ($data['monthly'] ?? [] as $month => $monthData) {
            $monthKey = "$scope:$month:t";
            $pipe->hIncrBy($monthKey, 'p', $monthData['p']);
            if ($monthData['xp'] > 0) {
                $pipe->hIncrBy($monthKey, 'xp', $monthData['xp']);
            }
            if ($monthData['l'] > 0) {
                $pipe->hIncrBy($monthKey, 'l', $monthData['l']);
            }
        }
    }

    /**
     * Prepare ranking updates
     */
    private static function prepareRankingUpdates(array $deltas, array $scopes, string $month): array
    {
        $updates = [];

        foreach ($scopes as $scope) {
            // Dimension rankings
            foreach (['objects', 'categories', 'materials', 'brands'] as $dimension) {
                if (!empty($deltas[$dimension])) {
                    $updates["$scope:rank:$dimension"] = $deltas[$dimension];

                    // Monthly rankings for objects and brands
                    if (in_array($dimension, ['objects', 'brands'])) {
                        $updates["$scope:rank:$dimension:m:$month"] = $deltas[$dimension];
                    }
                }
            }
        }

        return $updates;
    }

    /**
     * Update all ranking ZSETs
     */
    private static function updateRankings($pipe, array $rankingUpdates, Photo $photo, int $litterTotal): void
    {
        // Update dimension rankings
        foreach ($rankingUpdates as $zsetKey => $items) {
            foreach ($items as $id => $increment) {
                $pipe->zIncrBy($zsetKey, $increment, (string)$id);
            }

            // Set TTL on monthly rankings
            if (str_contains($zsetKey, ':m:')) {
                $pipe->expire($zsetKey, self::MONTHLY_RANKING_TTL);
            }
        }

        // Update location hierarchy rankings
        if ($photo->country_id) {
            $pipe->zIncrBy(self::GLOBAL_COUNTRY_LITTER_RANKING, $litterTotal, (string)$photo->country_id);
            $pipe->zIncrBy(self::GLOBAL_COUNTRY_PHOTOS_RANKING, 1, (string)$photo->country_id);

            if ($photo->state_id) {
                $pipe->zIncrBy("{c:{$photo->country_id}}:rank:s:litter", $litterTotal, (string)$photo->state_id);
                $pipe->zIncrBy("{c:{$photo->country_id}}:rank:s:photos", 1, (string)$photo->state_id);

                if ($photo->city_id) {
                    $pipe->zIncrBy("{s:{$photo->state_id}}:rank:ci:litter", $litterTotal, (string)$photo->city_id);
                    $pipe->zIncrBy("{s:{$photo->state_id}}:rank:ci:photos", 1, (string)$photo->city_id);
                }
            }
        }
    }

    /**
     * Update batch rankings
     */
    private static function updateBatchRankings($pipe, array $rankingUpdates): void
    {
        foreach ($rankingUpdates as $zsetKey => $items) {
            foreach ($items as $id => $increment) {
                $pipe->zIncrBy($zsetKey, $increment, (string)$id);
            }

            // Set TTL on monthly rankings
            if (str_contains($zsetKey, ':m:')) {
                $pipe->expire($zsetKey, self::MONTHLY_RANKING_TTL);
            }
        }
    }

    /**
     * Trim ranking ZSETs to prevent unbounded growth
     */
    private static function trimRankingZSets(array $rankingUpdates): void
    {
        foreach (array_keys($rankingUpdates) as $zsetKey) {
            // Don't trim location hierarchy rankings (countries, states, cities)
            if (str_contains($zsetKey, ':rank:s:') || str_contains($zsetKey, ':rank:ci:') || str_contains($zsetKey, ':rank:c:')) {
                continue;
            }

            // Trim dimension rankings to top N
            Redis::zRemRangeByRank($zsetKey, 0, -(self::MAX_RANKING_ITEMS + 1));
        }
    }

    /**
     * Trim batch ranking ZSETs
     */
    private static function trimBatchRankingZSets(array $rankingUpdates): void
    {
        $keysToTrim = [];

        foreach (array_keys($rankingUpdates) as $zsetKey) {
            // Skip location hierarchy rankings
            if (str_contains($zsetKey, ':rank:s:') || str_contains($zsetKey, ':rank:ci:') || str_contains($zsetKey, ':rank:c:')) {
                continue;
            }
            $keysToTrim[] = $zsetKey;
        }

        // Trim in a separate pipeline
        if (!empty($keysToTrim)) {
            Redis::pipeline(function ($pipe) use ($keysToTrim) {
                foreach ($keysToTrim as $key) {
                    $pipe->zRemRangeByRank($key, 0, -(self::MAX_RANKING_ITEMS + 1));
                }
            });
        }
    }

    /**
     * Update user streak (only for today)
     */
    private static function updateUserStreak(int $userId, string $date): void
    {
        $statsKey = sprintf(self::USER_STATS_KEY, $userId);
        $bitmapKey = sprintf(self::USER_BITMAP_KEY, $userId);
        $dayIndex = self::calculateDayIndex($date);

        // Mark bit for this date
        $result = self::executeBitfield(Redis::connection(), $bitmapKey, $dayIndex);

        // Only update streak if this is today
        $today = now('UTC')->format('Y-m-d');
        if ($date === $today) {
            $hadYesterday = ($result[0] ?? 0) === 1;
            $currentStreak = (int) Redis::hGet($statsKey, 'streak') ?: 0;
            $newStreak = $hadYesterday ? $currentStreak + 1 : 1;

            if ($newStreak !== $currentStreak) {
                Redis::hSet($statsKey, 'streak', $newStreak);
            }
        }
    }

    /**
     * Execute BITFIELD command
     */
    private static function executeBitfield($connection, string $key, int $dayIndex): array
    {
        $command = [
            'BITFIELD', $key,
            'GET', 'u1', (string)($dayIndex - 1),  // Get yesterday
            'SET', 'u1', (string)$dayIndex, '1'     // Set today
        ];

        // Convert to strings for compatibility
        $command = array_map('strval', $command);

        if (method_exists($connection, 'rawCommand')) {
            return $connection->rawCommand(...$command) ?: [];
        }

        if (method_exists($connection, 'executeRaw')) {
            return $connection->executeRaw($command) ?: [];
        }

        throw new \RuntimeException('No raw Redis command method available');
    }

    /**
     * Calculate day index for streak bitmap
     */
    private static function calculateDayIndex(string $date): int
    {
        static $epoch;
        $epoch ??= Carbon::createFromTimestampUTC(0);
        return (int) $epoch->diffInDays(Carbon::parse($date, 'UTC'));
    }

    /**
     * Get location scopes with proper hash-tags
     */
    private static function getLocationScopes(?int $countryId, ?int $stateId, ?int $cityId): array
    {
        return array_filter([
            '{g}',
            $countryId ? "{c:$countryId}" : null,
            $stateId ? "{s:$stateId}" : null,
            $cityId ? "{ci:$cityId}" : null,
        ]);
    }

    /**
     * Aggregate metrics across a batch of photos
     */
    private static function aggregateBatchMetrics(Collection $photos): array
    {
        $result = [
            'totalUploads' => 0,
            'totalXp' => 0,
            'totalLitter' => 0,
            'userDeltas' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ],
            'globalDeltas' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
            ],
            'globalMonthly' => [],
            'locationData' => [],
            'locationUsers' => [],
            'dailyCounts' => [],
            'rankingUpdates' => [],
            'dates' => [],
        ];

        foreach ($photos as $photo) {
            $result['totalUploads']++;
            $xp = (int) ($photo->xp ?? 0);
            $result['totalXp'] += $xp;

            $createdAt = $photo->created_at ?? now('UTC');
            $date = $createdAt->setTimezone('UTC')->format('Y-m-d');
            $month = $createdAt->format('Y-m');
            $result['dates'][$date] = true;

            // Extract validated deltas
            $deltas = self::extractValidatedDeltas($photo);
            $photoLitter = array_sum($deltas['objects']);
            $result['totalLitter'] += $photoLitter;

            // Aggregate user deltas
            foreach ($deltas as $dimension => $items) {
                foreach ($items as $id => $count) {
                    $result['userDeltas'][$dimension][$id] =
                        ($result['userDeltas'][$dimension][$id] ?? 0) + $count;

                    if ($dimension !== 'custom_tags') {
                        $result['globalDeltas'][$dimension][$id] =
                            ($result['globalDeltas'][$dimension][$id] ?? 0) + $count;
                    }
                }
            }

            // Global monthly
            if (!isset($result['globalMonthly'][$month])) {
                $result['globalMonthly'][$month] = ['xp' => 0, 'litter' => 0];
            }
            $result['globalMonthly'][$month]['xp'] += $xp;
            $result['globalMonthly'][$month]['litter'] += $photoLitter;

            // Location data
            $scopes = self::getLocationScopes($photo->country_id, $photo->state_id, $photo->city_id);

            foreach ($scopes as $scope) {
                // Daily counts
                $result['dailyCounts']["$scope|$date"] =
                    ($result['dailyCounts']["$scope|$date"] ?? 0) + 1;

                if ($scope === '{g}') continue;

                // Initialize location data
                if (!isset($result['locationData'][$scope])) {
                    $result['locationData'][$scope] = [
                        'photos' => 0,
                        'litter' => 0,
                        'categories' => [],
                        'objects' => [],
                        'materials' => [],
                        'brands' => [],
                        'monthly' => [],
                    ];
                    $result['locationUsers'][$scope] = [];
                }

                // Aggregate location metrics
                $result['locationData'][$scope]['photos']++;
                $result['locationData'][$scope]['litter'] += $photoLitter;
                $result['locationUsers'][$scope][$photo->user_id] = true;

                // Aggregate dimensions
                foreach ($deltas as $dimension => $items) {
                    if ($dimension === 'custom_tags') continue;

                    foreach ($items as $id => $count) {
                        $result['locationData'][$scope][$dimension][$id] =
                            ($result['locationData'][$scope][$dimension][$id] ?? 0) + $count;
                    }
                }

                // Monthly aggregates
                if (!isset($result['locationData'][$scope]['monthly'][$month])) {
                    $result['locationData'][$scope]['monthly'][$month] = [
                        'p' => 0,
                        'xp' => 0,
                        'l' => 0,
                    ];
                }
                $result['locationData'][$scope]['monthly'][$month]['p']++;
                $result['locationData'][$scope]['monthly'][$month]['xp'] += $xp;
                $result['locationData'][$scope]['monthly'][$month]['l'] += $photoLitter;

                // Ranking updates
                foreach ($deltas as $dimension => $items) {
                    if ($dimension === 'custom_tags') continue;

                    $rankKey = "$scope:rank:$dimension";
                    if (!isset($result['rankingUpdates'][$rankKey])) {
                        $result['rankingUpdates'][$rankKey] = [];
                    }

                    foreach ($items as $id => $count) {
                        $result['rankingUpdates'][$rankKey][$id] =
                            ($result['rankingUpdates'][$rankKey][$id] ?? 0) + $count;
                    }

                    // Monthly rankings
                    if (in_array($dimension, ['objects', 'brands'])) {
                        $monthlyKey = "$scope:rank:$dimension:m:$month";
                        if (!isset($result['rankingUpdates'][$monthlyKey])) {
                            $result['rankingUpdates'][$monthlyKey] = [];
                        }

                        foreach ($items as $id => $count) {
                            $result['rankingUpdates'][$monthlyKey][$id] =
                                ($result['rankingUpdates'][$monthlyKey][$id] ?? 0) + $count;
                        }
                    }
                }
            }

            // Location hierarchy rankings
            if ($photo->country_id) {
                $countryKey = self::GLOBAL_COUNTRY_LITTER_RANKING;
                $result['rankingUpdates'][$countryKey][$photo->country_id] =
                    ($result['rankingUpdates'][$countryKey][$photo->country_id] ?? 0) + $photoLitter;

                $photoKey = self::GLOBAL_COUNTRY_PHOTOS_RANKING;
                $result['rankingUpdates'][$photoKey][$photo->country_id] =
                    ($result['rankingUpdates'][$photoKey][$photo->country_id] ?? 0) + 1;

                if ($photo->state_id) {
                    $stateKey = "{c:{$photo->country_id}}:rank:s:litter";
                    $result['rankingUpdates'][$stateKey][$photo->state_id] =
                        ($result['rankingUpdates'][$stateKey][$photo->state_id] ?? 0) + $photoLitter;

                    $statePhotoKey = "{c:{$photo->country_id}}:rank:s:photos";
                    $result['rankingUpdates'][$statePhotoKey][$photo->state_id] =
                        ($result['rankingUpdates'][$statePhotoKey][$photo->state_id] ?? 0) + 1;

                    if ($photo->city_id) {
                        $cityKey = "{s:{$photo->state_id}}:rank:ci:litter";
                        $result['rankingUpdates'][$cityKey][$photo->city_id] =
                            ($result['rankingUpdates'][$cityKey][$photo->city_id] ?? 0) + $photoLitter;

                        $cityPhotoKey = "{s:{$photo->state_id}}:rank:ci:photos";
                        $result['rankingUpdates'][$cityPhotoKey][$photo->city_id] =
                            ($result['rankingUpdates'][$cityPhotoKey][$photo->city_id] ?? 0) + 1;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get user counts for achievements
     */
    public static function getUserCounts(int $userId): array
    {
        $results = Redis::pipeline(function($pipe) use ($userId) {
            $pipe->hGetAll(sprintf(self::USER_STATS_KEY, $userId));
            $pipe->hGetAll(sprintf(self::USER_CATEGORIES_KEY, $userId));
            $pipe->hGetAll(sprintf(self::USER_OBJECTS_KEY, $userId));
            $pipe->hGetAll(sprintf(self::USER_MATERIALS_KEY, $userId));
            $pipe->hGetAll(sprintf(self::USER_BRANDS_KEY, $userId));
            $pipe->hGetAll(sprintf(self::USER_CUSTOM_KEY, $userId));
        });

        return [
            'uploads' => (int) ($results[0]['uploads'] ?? 0),
            'streak' => (int) ($results[0]['streak'] ?? 0),
            'xp' => (float) ($results[0]['xp'] ?? 0),
            'litter' => (int) ($results[0]['litter'] ?? 0),
            'categories' => array_map('intval', $results[1] ?: []),
            'objects' => array_map('intval', $results[2] ?: []),
            'materials' => array_map('intval', $results[3] ?: []),
            'brands' => array_map('intval', $results[4] ?: []),
            'custom_tags' => array_map('intval', $results[5] ?: []),
        ];
    }

    /**
     * Get user counts with string keys for display
     */
    public static function getUserCountsWithKeys(int $userId): array
    {
        $counts = self::getUserCounts($userId);

        $dimensionTables = [
            'categories' => 'categories',
            'objects' => 'litter_objects',
            'materials' => 'materials',
            'brands' => 'brandslist',
            'custom_tags' => 'custom_tags_new',
        ];

        foreach ($dimensionTables as $dimension => $table) {
            if (empty($counts[$dimension])) continue;

            $ids = array_map('intval', array_keys($counts[$dimension]));
            $keys = TagKeyCache::keysBatch($table, $ids);

            $named = [];
            foreach ($counts[$dimension] as $id => $count) {
                $named[$keys[$id] ?? "unknown_$id"] = $count;
            }
            $counts[$dimension] = $named;
        }

        return $counts;
    }
}
