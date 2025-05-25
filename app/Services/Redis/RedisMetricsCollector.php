<?php
/**
 * ----------------------------------------------------------------------
 * Enhanced RedisMetricsCollector with achievements support
 *
 * NEW KEYS for achievements:
 *  {u:<id>}:m       HASH  ── per-user materials quantities  glass → 119, plastic → 234
 *  {u:<id>}:brands  HASH  ── per-user brands quantities     coke → 14, pepsi → 8
 *  {u:<id>}:last    HASH  ── last achievement check timestamps
 *      • uploads    UNIX timestamp of last uploads check
 *      • objects    UNIX timestamp of last objects check
 *      • categories UNIX timestamp of last categories check
 *      • materials  UNIX timestamp of last materials check
 *      • brands     UNIX timestamp of last brands check
 *
 *  achievement:queue SET  ── user IDs that need achievement checking
 *  {g}:m            HASH  ── global materials totals
 *  {g}:brands       HASH  ── global brands totals
 */

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs (ms) ─────────────────────────────────────────────── */
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years
    private const UPLOAD_FLAG_TTL_SECONDS = 60 * 60 * 24 * 40; // 40 days for streak tracking

    /* ─── Key patterns ──────────────────────────────────────────── */
    private const KEY_STATS = '{u:%d}:stats';   // uploads, xp, streak
    private const KEY_CATEGORIES = '{u:%d}:c';  // category counts
    private const KEY_OBJECTS = '{u:%d}:t';     // object counts
    private const KEY_MATERIALS = '{u:%d}:m';   // material counts
    private const KEY_BRANDS = '{u:%d}:brands'; // brand counts
    private const KEY_LAST_CHECK = '{u:%d}:last'; // last achievement check
    private const ACHIEVEMENT_QUEUE = 'achievement:queue'; // users needing checks

    // Global keys
    private const GLOBAL_CATEGORIES = '{g}:c';
    private const GLOBAL_OBJECTS = '{g}:t';
    private const GLOBAL_MATERIALS = '{g}:m';
    private const GLOBAL_BRANDS = '{g}:brands';

    /**
     * Persist all Redis metrics for a single photo.
     *
     * @throws RedisException
     */
    public static function queue(Photo $photo): void
    {
        // 1. Idempotency: skip if already done
        if (self::alreadyProcessed($photo->id)) {
            return;
        }

        // 2. Common metadata
        $uid      = $photo->user_id;
        $uTag     = "{u:$uid}";
        $xp       = (int) ($photo->xp ?? 0);
        $ts       = $photo->created_at ?? now();
        $date     = $ts->format('Y-m-d');
        $monthTag = '{g}:' . $ts->format('Y-m'); // Fixed hash clustering

        $country = $photo->country_id ?? $photo->country?->id;
        $state   = $photo->state_id   ?? $photo->state?->id;
        $city    = $photo->city_id    ?? $photo->city?->id;

        // 3. Derive per-photo deltas from the summary
        $tagsTree = $photo->summary['tags'] ?? [];
        $catIncr = [];
        $objIncr = [];
        $matIncr = [];
        $brandIncr = [];

        foreach ($tagsTree as $cat => $objs) {
            foreach ($objs as $obj => $data) {
                $q = (int) ($data['quantity'] ?? 0);
                $catIncr[$cat] = ($catIncr[$cat] ?? 0) + $q;
                $objIncr[$obj] = ($objIncr[$obj] ?? 0) + $q;

                // Extract materials and brands separately
                foreach ($data['materials'] ?? [] as $material => $matQ) {
                    $matIncr[$material] = ($matIncr[$material] ?? 0) + $matQ;
                }
                foreach ($data['brands'] ?? [] as $brand => $brandQ) {
                    $brandIncr[$brand] = ($brandIncr[$brand] ?? 0) + $brandQ;
                }
            }
        }

        /* ── Main pipeline (single round‑trip) ─────────────────────────── */
        $statsKey = sprintf(self::KEY_STATS, $uid);
        $categoriesKey = sprintf(self::KEY_CATEGORIES, $uid);
        $objectsKey = sprintf(self::KEY_OBJECTS, $uid);
        $materialsKey = sprintf(self::KEY_MATERIALS, $uid);
        $brandsKey = sprintf(self::KEY_BRANDS, $uid);

        // 4. Pipeline the bulk writes
        Redis::pipeline(static function ($pipe) use (
            $statsKey, $categoriesKey, $objectsKey, $materialsKey, $brandsKey,
            $uTag, $uid, $xp, $date, $monthTag,
            $country, $state, $city,
            $catIncr, $objIncr, $matIncr, $brandIncr, $tagsTree
        ) {
            // 4.1 Initialize stats fields if missing
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp', 0);
            $pipe->hSetNx($statsKey, 'streak', 0);

            // 4.2 Bump uploads & xp
            $pipe->hIncrBy($statsKey, 'uploads', 1);
            if ($xp) {
                $pipe->hIncrByFloat($statsKey, 'xp', $xp);
            }

            // 4.3 Category, object, material, brand counts
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy("$uTag:c", $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy("$uTag:t", $o, $q);
            }
            foreach ($matIncr as $material => $q) {
                $pipe->hIncrBy($materialsKey, $material, $q);
            }
            foreach ($brandIncr as $brand => $q) {
                $pipe->hIncrBy($brandsKey, $brand, $q);
            }

            // 4.4 Global + scoped daily buckets
            $pipe->hIncrBy("{$monthTag}:t", 'p', 1);
            if ($xp) {
                $pipe->hIncrByFloat("{$monthTag}:t", 'xp', $xp);
            }

            foreach (self::geoScopes($country, $state, $city) as $scope) {
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, 1)
                    ->pExpire($tsKey, self::TS_TTL_MS);
            }

            // 4.5 Global category/object/material/brand tallies
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy(self::GLOBAL_CATEGORIES, $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy(self::GLOBAL_OBJECTS, $o, $q);
            }
            foreach ($matIncr as $m => $q) {
                $pipe->hIncrBy(self::GLOBAL_MATERIALS, $m, $q);
            }
            foreach ($brandIncr as $b => $q) {
                $pipe->hIncrBy(self::GLOBAL_BRANDS, $b, $q);
            }

            // 4.6 Queue user for achievement checking
            $pipe->sAdd(self::ACHIEVEMENT_QUEUE, $uid);
        });

        // 5. Streak logic (outside pipeline to avoid complexity)
        $upTodayKey     = "{$uTag}:up:{$date}";
        $upYesterdayKey = "{$uTag}:up:" . Carbon::parse($date)->subDay()->format('Y-m-d');
        $stKey          = "{$uTag}:streak";

        // 5.1 Mark today's upload with proper TTL
        Redis::setex($upTodayKey, self::UPLOAD_FLAG_TTL_SECONDS, '1');

        // 5.2 Compute new streak
        $hadYesterday = Redis::exists($upYesterdayKey);
        $oldStreak    = (int) (Redis::get($stKey) ?: 0);
        $newStreak    = $hadYesterday ? $oldStreak + 1 : 1;

        // 5.3 Write streak back to both locations (maintain backwards compatibility)
        Redis::set($stKey, $newStreak);
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

        return [
            'uploads' => (int) (Redis::hGet($statsKey, 'uploads') ?: 0),
            'streak' => (int) (Redis::hGet($statsKey, 'streak') ?: 0),
            'categories' => Redis::hGetAll($categoriesKey) ?: [],
            'objects' => Redis::hGetAll($objectsKey) ?: [],
            'materials' => Redis::hGetAll($materialsKey) ?: [],
            'brands' => Redis::hGetAll($brandsKey) ?: [],
        ];
    }

    /**
     * Get users queued for achievement checking (atomic pop)
     */
    public static function getAchievementQueue(int $limit = 100): array
    {
        // Use SPOP for atomic removal (Redis 6.2+)
        $userIds = Redis::sPop(self::ACHIEVEMENT_QUEUE, $limit);
        return is_array($userIds) ? array_map('intval', $userIds) : ($userIds ? [(int) $userIds] : []);
    }

    /**
     * Remove user from achievement queue (now redundant with SPOP, but kept for compatibility)
     */
    public static function removeFromAchievementQueue(int $userId): void
    {
        Redis::sRem(self::ACHIEVEMENT_QUEUE, $userId);
    }

    /**
     * Batch get counts for multiple users (for background processing)
     */
    public static function getBatchUserCounts(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $results = [];

        // Use pipeline to batch all Redis calls
        $pipelineResults = Redis::pipeline(function ($pipe) use ($userIds) {
            $commands = [];
            foreach ($userIds as $userId) {
                $statsKey = sprintf(self::KEY_STATS, $userId);
                $categoriesKey = sprintf(self::KEY_CATEGORIES, $userId);
                $objectsKey = sprintf(self::KEY_OBJECTS, $userId);
                $materialsKey = sprintf(self::KEY_MATERIALS, $userId);
                $brandsKey = sprintf(self::KEY_BRANDS, $userId);

                $commands[] = $pipe->hGet($statsKey, 'uploads');
                $commands[] = $pipe->hGet($statsKey, 'streak');
                $commands[] = $pipe->hGetAll($categoriesKey);
                $commands[] = $pipe->hGetAll($objectsKey);
                $commands[] = $pipe->hGetAll($materialsKey);
                $commands[] = $pipe->hGetAll($brandsKey);
            }
            return $commands;
        });

        // Process pipeline results
        $resultIndex = 0;
        foreach ($userIds as $userId) {
            $results[$userId] = [
                'uploads' => (int) ($pipelineResults[$resultIndex++] ?: 0),
                'streak' => (int) ($pipelineResults[$resultIndex++] ?: 0),
                'categories' => $pipelineResults[$resultIndex++] ?: [],
                'objects' => $pipelineResults[$resultIndex++] ?: [],
                'materials' => $pipelineResults[$resultIndex++] ?: [],
                'brands' => $pipelineResults[$resultIndex++] ?: [],
            ];
        }

        return $results;
    }

    /**
     * Mark user as achievement-checked for a specific dimension
     */
    public static function markAchievementChecked(int $userId, string $dimension): void
    {
        $lastCheckKey = sprintf(self::KEY_LAST_CHECK, $userId);
        Redis::hSet($lastCheckKey, $dimension, time());
    }

    /**
     * Get timestamp of last achievement check for a dimension
     */
    public static function getLastAchievementCheck(int $userId, string $dimension): ?int
    {
        $lastCheckKey = sprintf(self::KEY_LAST_CHECK, $userId);
        $timestamp = Redis::hGet($lastCheckKey, $dimension);
        return $timestamp ? (int) $timestamp : null;
    }

    /* ===================================================================== */

    /**
     * Generate geographic scopes for daily tracking
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

    /** Add photo id to processed set; returns true if it *was already* there. */
    private static function alreadyProcessed(?int $photoId): bool
    {
        if (!$photoId) {
            return false;
        }

        // Add & set TTL in one pipeline round-trip
        $results = Redis::pipeline(function ($pipe) use ($photoId) {
            $pipe->sAdd('p:done', $photoId);           // idx 0
            $pipe->expire('p:done', 60 * 60 * 24 * 90); // idx 1 - 90 day TTL
        });

        return ($results[0] ?? 0) === 0; // true → was already processed
    }
}
