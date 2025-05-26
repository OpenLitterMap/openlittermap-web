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
     * Persist all Redis metrics for a single photo
     */
    public static function queue(Photo $photo): void
    {
        if (self::alreadyProcessed($photo->id)) {
            return;
        }

        self::persistMetrics($photo);
        self::updateStreak($photo);
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

        // Single pipeline for all operations
        Redis::pipeline(function ($pipe) use ($uid, $xp, $date, $monthTag, $deltas, $photo, $ts) {
            $statsKey = sprintf(self::KEY_STATS, $uid);
            $uTag = "{u:$uid}";

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

            // Mark as processed
            $pipe->sAdd('p:done', $photo->id);
            $pipe->expire('p:done', 60 * 60 * 24 * 90);
        });
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
     * Update streak logic
     */
    private static function updateStreak(Photo $photo): void
    {
        $uid = $photo->user_id;
        $date = $photo->created_at->format('Y-m-d');
        $uTag = "{u:$uid}";

        $upTodayKey = "{$uTag}:up:{$date}";
        $upYesterdayKey = "{$uTag}:up:" . Carbon::parse($date)->subDay()->format('Y-m-d');
        $statsKey = sprintf(self::KEY_STATS, $uid);

        Redis::setex($upTodayKey, self::UPLOAD_FLAG_TTL_SECONDS, '1');

        $hadYesterday = Redis::exists($upYesterdayKey);
        $oldStreak = (int) (Redis::hGet($statsKey, 'streak') ?: 0);
        $newStreak = $hadYesterday ? $oldStreak + 1 : 1;

        Redis::hSet($statsKey, 'streak', $newStreak);
    }

    /**
     * Get user's current counts for achievement checking
     */
    public static function getUserCounts(int $userId): array
    {
        $statsKey = sprintf(self::KEY_STATS, $userId);
        $categoriesKey = sprintf(self::KEY_CATEGORIES, $userId);
        $objectsKey = sprintf(self::KEY_OBJECTS, $userId);
        $materialsKey = sprintf(self::KEY_MATERIALS, $userId);
        $brandsKey = sprintf(self::KEY_BRANDS, $userId);
        $customKey = sprintf(self::KEY_CUSTOM, $userId);

        return [
            'uploads' => (int) (Redis::hGet($statsKey, 'uploads') ?: 0),
            'streak' => (int) (Redis::hGet($statsKey, 'streak') ?: 0),
            'xp' => (float) (Redis::hGet($statsKey, 'xp') ?: 0),
            'categories' => Redis::hGetAll($categoriesKey) ?: [],
            'objects' => Redis::hGetAll($objectsKey) ?: [],
            'materials' => Redis::hGetAll($materialsKey) ?: [],
            'brands' => Redis::hGetAll($brandsKey) ?: [],
            'custom_tags' => Redis::hGetAll($customKey) ?: [],
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
