<?php

/**
 * ----------------------------------------------------------------------
 * RedisMetricsCollector with achievements support
 *
 * KEYS for achievements:
 *  {u:<id>}:stats  HASH  ── per-user stats
 *      • uploads    total uploads count
 *      • xp         total XP earned
 *      • streak     current streak of consecutive uploads
 *  {u:<id>}:m       HASH  ── per-user materials quantities  glass → 119, plastic → 234
 *  {u:<id>}:brands  HASH  ── per-user brands quantities     coke → 14, pepsi → 8
 *  {u:<id>}:last    HASH  ── last achievement check timestamps
 *      • uploads    UNIX timestamp of last uploads check
 *      • objects    UNIX timestamp of last objects check
 *      • categories UNIX timestamp of last categories check
 *      • materials  UNIX timestamp of last materials check
 *      • brands     UNIX timestamp of last brands check
 */

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
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
            $date = $ts->format('Y-m-d');
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
                $pipe->hIncrBy("$uTag:c", $c, $q);
            }
            foreach ($totalDeltas['objects'] as $o => $q) {
                $pipe->hIncrBy("$uTag:t", $o, $q);
            }
            foreach ($totalDeltas['materials'] as $m => $q) {
                $pipe->hIncrBy("$uTag:m", $m, $q);
            }
            foreach ($totalDeltas['brands'] as $b => $q) {
                $pipe->hIncrBy("$uTag:brands", $b, $q);
            }
            foreach ($totalDeltas['custom_tags'] as $ct => $q) {
                $pipe->hIncrBy("$uTag:custom", $ct, $q);
            }

            // Update global counts
            foreach ($totalDeltas['categories'] as $c => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, $c, $q);
            }
            foreach ($totalDeltas['objects'] as $o => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, $o, $q);
            }
            foreach ($totalDeltas['materials'] as $m => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, $m, $q);
            }
            foreach ($totalDeltas['brands'] as $b => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, $b, $q);
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
                [$scope, $date] = explode('|', $scopeDate, 2); // Gets "c:1" and "2025-01-20"
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, $count);
                $pipe->pExpire($tsKey, self::TS_TTL_MS);
            }

            // Mark all photos as processed
            foreach ($photoIds as $photoId) {
                $pipe->sAdd('p:done', $photoId);
            }
            $pipe->expire('p:done', 60 * 60 * 24 * 90);
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
            ->groupBy(fn($photo) => $photo->created_at->format('Y-m-d'));

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
        $date = $ts->format('Y-m-d');
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
                $pipe->hIncrBy("$uTag:c", $c, $q);
            }
            foreach ($deltas['objects'] as $o => $q) {
                $pipe->hIncrBy("$uTag:t", $o, $q);
            }
            foreach ($deltas['materials'] as $m => $q) {
                $pipe->hIncrBy("$uTag:m", $m, $q);
            }
            foreach ($deltas['brands'] as $b => $q) {
                $pipe->hIncrBy("$uTag:brands", $b, $q);
            }
            foreach ($deltas['custom_tags'] as $ct => $q) {
                $pipe->hIncrBy("$uTag:custom", $ct, $q);
            }

            // Update global counts
            foreach ($deltas['categories'] as $c => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, $c, $q);
            }
            foreach ($deltas['objects'] as $o => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, $o, $q);
            }
            foreach ($deltas['materials'] as $m => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, $m, $q);
            }
            foreach ($deltas['brands'] as $b => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, $b, $q);
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
            $pipe->sAdd('p:done', $photo->id);
            $pipe->expire('p:done', 60 * 60 * 24 * 90);
        });

        // Handle streak calculation after pipeline
        $hadYesterday = $results[0];  // exists result
        $oldStreak = (int) ($results[1] ?: 0);  // current streak
        $newStreak = $hadYesterday ? $oldStreak + 1 : 1;

        if ($newStreak !== $oldStreak) {
            Redis::hSet($statsKey, 'streak', $newStreak);
        }
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

        foreach ($photo->summary['tags'] ?? [] as $cat => $objs) {
            foreach ($objs as $obj => $data) {

                // Ensure quantity is a non-negative integer
                $q = max(0, (int) ($data['quantity'] ?? 0));

                if ($q > 0) {
                    $deltas['categories'][$cat] = ($deltas['categories'][$cat] ?? 0) + $q;
                    $deltas['objects'][$obj] = ($deltas['objects'][$obj] ?? 0) + $q;

                    foreach ($data['materials'] ?? [] as $material => $matQ) {
                        $matQ = max(0, (int) $matQ);
                        if ($matQ > 0) {
                            $deltas['materials'][$material] = ($deltas['materials'][$material] ?? 0) + $matQ;
                        }
                    }

                    foreach ($data['brands'] ?? [] as $brand => $brandQ) {
                        $brandQ = max(0, (int) $brandQ);
                        if ($brandQ > 0) {
                            $deltas['brands'][$brand] = ($deltas['brands'][$brand] ?? 0) + $brandQ;
                        }
                    }

                    foreach ($data['custom_tags'] ?? [] as $customTag => $customQ) {
                        $customQ = max(0, (int) $customQ);
                        if ($customQ > 0) {
                            $deltas['custom_tags'][$customTag] = ($deltas['custom_tags'][$customTag] ?? 0) + $customQ;
                        }
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

    private static function alreadyProcessed(?int $photoId): bool
    {
        if (!$photoId) {
            return false;
        }

        return !Redis::sAdd('p:done', $photoId);
    }
}
