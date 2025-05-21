<?php
/**
 * ----------------------------------------------------------------------
 * RedisMetricsCollector – single‑entry point that atomically persists every
 * metric derived from one uploaded photo. Designed for *production* – no legacy
 * leftovers, no redundant keys, minimal fan‑out.
 *
 *  {u:<id>}:stats   HASH  ── per‑user packed counters
 *      • uploads    INT   total photos the user has uploaded
 *      • xp         FLOAT total XP awarded to the user
 *      • st         INT   current consecutive‑day upload streak
 *
 *  {u:<id>}:c       HASH  ── per‑user category quantities       alcohol → 630 …
 *  {u:<id>}:t       HASH  ── per‑user litter‑object quantities  beer_can → 373 …
 *  {u:<id>}:b       HASH  ── materials / brands / custom tags (shared hash)
 *                         ◦ m:glass 119   ◦ b:coke 14   ◦ c:myTag 3
 *
 *  {u:<id>}:st      STRING── current streak (written by streak.lua)
 *  {u:<id>}:up:<ISO‑date> STRING value = 1; existence used by streak.lua
 *
 *  {g}:c            HASH  ── global category totals
 *  {g}:t            HASH  ── global object totals
 *  {g:<YYYY‑MM>}    HASH  ── global monthly histogram  p → photos, xp → XP
 *  {g}:t:p          HASH  ── global daily photo count  <date> → uploads
 *  {g}:t:p,<scope>  HASH  ── same daily roll‑ups for country / state / city scopes
 */

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs (ms) ─────────────────────────────────────────────── */
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years

    /* ─── Packed‑stats key pattern ──────────────────────────────── */
    private const KEY_STATS = '{u:%d}:stats';   // uploads, xp, st

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
        $uid     = $photo->user_id;
        $uTag    = "{u:$uid}";
        $xp      = (float) ($photo->xp ?? 0);
        $ts       = $photo->created_at;
        $date     = $ts->format('Y-m-d');
        $monthTag = '{g:'.$ts->format('Y-m').'}'; // disperse global hot slot

        $country = $photo->country_id ?? $photo->country?->id;
        $state   = $photo->state_id   ?? $photo->state?->id;
        $city    = $photo->city_id    ?? $photo->city?->id;

        // 3. Derive per-photo deltas from the summary
        $tagsTree = $photo->summary['tags'] ?? [];
        $catIncr = [];
        $objIncr = [];
        foreach ($tagsTree as $cat => $objs) {
            foreach ($objs as $obj => $data) {
                $q = (int) ($data['quantity'] ?? 0);
                $catIncr[$cat] = ($catIncr[$cat] ?? 0) + $q;
                $objIncr[$obj] = ($objIncr[$obj] ?? 0) + $q;
            }
        }

        /* ── Main pipeline (single round‑trip) ─────────────────────────── */
        $statsKey = sprintf(self::KEY_STATS, $uid);

        // 4. Pipeline the bulk writes (no streak logic here!)
        Redis::pipeline(static function ($pipe) use (
            $statsKey, $uTag, $uid, $xp, $date, $monthTag,
            $country, $state, $city,
            $catIncr, $objIncr, $tagsTree
        ) {
            // 4.1 initialize stats fields if missing
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp',      0);
            $pipe->hSetNx($statsKey, 'st',      0);

            // 4.2 bump uploads & xp
            $pipe->hIncrBy($statsKey, 'uploads', 1);
            if ($xp) {
                $pipe->hIncrByFloat($statsKey, 'xp', $xp);
            }

            // 4.3 per-category and per-object hashes
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy("$uTag:c", $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy("$uTag:t", $o, $q);
            }

            // 4.4 materials / brands / custom_tags in shared hash
            foreach ($tagsTree as $objects) {
                foreach ($objects as $data) {
                    foreach ($data['materials'] ?? [] as $k => $q) {
                        $pipe->hIncrBy("$uTag:b", "m:$k", $q);
                    }
                    foreach ($data['brands'] ?? [] as $k => $q) {
                        $pipe->hIncrBy("$uTag:b", "b:$k", $q);
                    }
                    foreach ($data['custom_tags'] ?? [] as $k => $q) {
                        $pipe->hIncrBy("$uTag:b", "c:$k", $q);
                    }
                }
            }

            // 4.5 global + scoped daily buckets
            $pipe->hIncrBy("{$monthTag}:t", 'p', 1);
            if ($xp) {
                $pipe->hIncrByFloat("{$monthTag}:t", 'xp', $xp);
            }

            foreach (array_filter([
                '{g}',
                "c:$country" => $country,
                "s:$state"   => $state,
                "ci:$city"   => $city,
            ]) as $scope) {
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, 1)
                    ->pExpire($tsKey, self::TS_TTL_MS);
            }

            // 4.6 global category/object tallies
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy('{g}:c', $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy('{g}:t', $o, $q);
            }
        });

        // 5) Now handle streak logic *outside* the pipeline on the real client
        $upTodayKey     = "{$uTag}:up:{$date}";
        $upYesterdayKey = "{$uTag}:up:" . Carbon::parse($date)->subDay()->format('Y-m-d');
        $stKey          = "{$uTag}:streak";

        // 5.1 mark today’s upload (key expiry in seconds)
        Redis::setex($upTodayKey, (int)(self::TS_TTL_MS / 1000), '1');

        // 5.2 compute new streak
        $hadYesterday = Redis::exists($upYesterdayKey);
        $oldStreak    = (int) Redis::get($stKey);
        $newStreak    = $hadYesterday ? $oldStreak + 1 : 1;

        // 5.3 write streak back to both the string key and the packed stats
        Redis::set($stKey, $newStreak);
        Redis::hSet($statsKey, 'streak', $newStreak);
    }

    /* ===================================================================== */

    /** Add photo id to processed set; returns true if it *was already* there. */
    private static function alreadyProcessed(?int $photoId): bool
    {
        return $photoId
            ? Redis::sAdd('p:done', $photoId) === 0
            : false;
    }
}
