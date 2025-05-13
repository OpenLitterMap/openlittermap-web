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
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs (ms) ─────────────────────────────────────────────── */
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2 years

    /* ─── Lua SHA cache ─────────────────────────────────────────── */
    private static ?string $streakSha = null;

    /* ─── Packed‑stats key pattern ──────────────────────────────── */
    private const KEY_STATS = '{u:%d}:stats';   // uploads, xp, st

    /* =====================================================================
       PUBLIC API
       ===================================================================== */
    /**
     * Persist all Redis metrics for a single photo.
     *
     * @throws RedisException
     */
    public static function queue(Photo $photo): void
    {
        /* ── Idempotency gate – avoid double‑counting the same photo ───── */
        if (self::alreadyProcessed($photo->id)) {
            return;
        }

        self::bootLua();

        /* ── Common metadata ───────────────────────────────────────────── */
        $uid     = $photo->user_id;
        $uTag    = "{u:$uid}";
        $xp      = (float) ($photo->xp ?? 0);

        $ts       = $photo->created_at;
        $date     = $ts->format('Y-m-d');
        $monthTag = '{g:'.$ts->format('Y-m').'}'; // disperse global hot slot

        $country = $photo->country_id ?? $photo->country?->id;
        $state   = $photo->state_id   ?? $photo->state?->id;
        $city    = $photo->city_id    ?? $photo->city?->id;

        /* ── Derive per‑photo deltas ───────────────────────────────────── */
        $tagsTree = $photo->summary['tags'] ?? [];

        $catIncr = $objIncr = [];
        foreach ($tagsTree as $cat => $objs) {
            foreach ($objs as $obj => $data) {
                $q = (int) ($data['quantity'] ?? 0);
                $catIncr[$cat] = ($catIncr[$cat] ?? 0) + $q;
                $objIncr[$obj] = ($objIncr[$obj] ?? 0) + $q;
            }
        }

        /* ── Main pipeline (single round‑trip) ─────────────────────────── */
        $statsKey = sprintf(self::KEY_STATS, $uid);

        Redis::pipeline(static function ($pipe) use (
            $statsKey, $uTag, $uid, $xp, $date, $monthTag,
            $country, $state, $city,
            $catIncr, $objIncr, $tagsTree
        ) {
            /* 1. per‑user packed stats ----------------------------------- */
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp',      0);
            $pipe->hSetNx($statsKey, 'st',      0);

            $pipe->hIncrBy      ($statsKey, 'uploads', 1);
            if ($xp) {
                $pipe->hIncrByFloat($statsKey, 'xp', $xp);
            }

            /* 2. per‑category / per‑object hashes ------------------------ */
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy("$uTag:c", $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy("$uTag:t", $o, $q);
            }

            /* 3. materials / brands / custom tags (shared hash) ---------- */
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

            /* 4. streak.lua updates  ------------------------------------- */
            self::evalShaCompat($pipe, self::$streakSha, [
                "$uTag:up:$date",                                  // KEYS[1]
                "$uTag:up:".Carbon::parse($date)->subDay()->format('Y-m-d'), // KEYS[2]
                "$uTag:st",                                       // KEYS[3]
            ], []);

            /* 5. global + scoped buckets --------------------------------- */
            $pipe->hIncrBy("$monthTag:t", 'p', 1);
            if ($xp) {
                $pipe->hIncrByFloat("$monthTag:t", 'xp', $xp);
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

            /* 6. global category / object tallies ------------------------ */
            foreach ($catIncr as $c => $q) {
                $pipe->hIncrBy('{g}:c', $c, $q);
            }
            foreach ($objIncr as $o => $q) {
                $pipe->hIncrBy('{g}:t', $o, $q);
            }
        });

        /* ── patch: copy computed streak into packed stats  -------------- */
        $streak = Redis::get("{u:$uid}:st");
        if ($streak !== null) {
            Redis::hSet($statsKey, 'st', $streak);
        }

        /* ── optional debug when running locally ------------------------ */
        if (app()->isLocal()) {
            Log::debug('[metrics] wrote stats for photo', [
                'photo_id' => $photo->id,
                'user_id'  => $uid,
                'stats'    => Redis::hGetAll($statsKey),
            ]);
        }
    }

    /* ===================================================================== */

    /** Add photo id to processed set; returns true if it *was already* there. */
    private static function alreadyProcessed(?int $photoId): bool
    {
        return $photoId ? Redis::sAdd('p:done', $photoId) === 0 : false;
    }

    /** Ensure streak.lua is loaded exactly once per process. */
    private static function bootLua(): void
    {
        if (! self::$streakSha) {
            self::$streakSha = Redis::script('load',
                file_get_contents(base_path('app/Services/Redis/lua/streak.lua'))
            );
            if (app()->isLocal()) {
                Log::debug('[metrics] streak.lua loaded', ['sha' => self::$streakSha]);
            }
        }
    }

    /** Call a Lua script while remaining compatible with older phpredis versions. */
    private static function evalShaCompat($pipe, string $sha, array $keys, array $argv): void
    {
        try { $pipe->evalSha($sha, $keys,   $argv); return; } catch (\Throwable) {}
        try { $pipe->evalSha($sha, count($keys), ...$keys, ...$argv); return; } catch (\Throwable) {}
        $pipe->evalSha($sha, array_merge($keys, $argv));
    }
}
