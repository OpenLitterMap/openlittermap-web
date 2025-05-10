<?php
declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs (ms) ─────────────────────────────────────────────── */
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1000; // 2years

    /* ─── Lua SHA cache ─────────────────────────────────────────── */
    private static ?string $streakSha = null;

    /* ─── Packed‑stats key pattern ──────────────────────────────── */
    private const KEY_STATS = '{u:%d}:stats';   // hash fields xp, uploads, st

    /* ======================================================================
       PUBLIC API
       ====================================================================== */
    /**
     * Queue all metrics related to a single photo in one pipeline.
     *
     * @throws RedisException
     */
    public static function queue(Photo $photo): void
    {
        /* ---------------- idempotency gate ---------------- */
        if (self::alreadyProcessed($photo->id)) {
            return; // duplicate photo – ignore
        }

        self::bootLua();

        /* ---------------- common meta -------------------- */
        $uid   = $photo->user_id;
        $uTag  = "{u:$uid}";
        $xp    = (float)($photo->xp ?? 0);

        $ts     = $photo->created_at;
        $date   = $ts->format('Y-m-d');
        $monthTag = '{g:'.$ts->format('Y-m').'}';  // spread global hot slot

        $country = $photo->country_id ?? $photo->country?->id;
        $state   = $photo->state_id   ?? $photo->state?->id;
        $city    = $photo->city_id    ?? $photo->city?->id;

        /* ------------- per‑photo breakdown --------------- */
        $tagsTree = $photo->summary['tags']   ?? [];
        $totals   = $photo->summary['totals'] ?? [];

        $catIncr = $objIncr = [];
        foreach ($tagsTree as $cat => $objs) {
            foreach ($objs as $obj => $data) {
                $q = (int)($data['quantity'] ?? 0);
                $catIncr[$cat] = ($catIncr[$cat] ?? 0) + $q;
                $objIncr[$obj] = ($objIncr[$obj] ?? 0) + $q;
            }
        }

        /* ------------------- pipeline --------------------- */
        Redis::pipeline(static function ($pipe) use (
            $photo, $uid, $uTag, $xp, $date, $monthTag,
            $country, $state, $city,
            $catIncr, $objIncr, $tagsTree
        ) {
            /* 1. packed stats hash ---------------------------------- */
            $statsKey = sprintf(self::KEY_STATS, $uid);

            // create baseline only once (cheap if key exists)
            $pipe->hSetNx($statsKey, 'uploads', 0);
            $pipe->hSetNx($statsKey, 'xp',      0);
            $pipe->hSetNx($statsKey, 'st',      0);

            $pipe->hIncrBy($statsKey, 'uploads', 1);
            if ($xp) $pipe->hIncrByFloat($statsKey, 'xp', $xp);

            /* 2. legacy per‑user short keys (optional, comment to drop) */
            $pipe->incr("$uTag:u");              // uploads counter
            // $pipe->incrByFloat("$uTag:xp", $xp);   // ≤– remove when readers migrate

            /* 3. per‑category / per‑object ------------------------------------ */
            foreach ($catIncr as $c => $q) $pipe->hIncrBy("$uTag:c", $c, $q);
            foreach ($objIncr as $o => $q) $pipe->hIncrBy("$uTag:t", $o, $q);

            /* materials / brands / custom tags in one user hash */
            foreach ($tagsTree as $objects)
                foreach ($objects as $data) {
                    foreach ($data['materials']   ?? [] as $k => $q)
                        $pipe->hIncrBy("$uTag:b", "m:$k", $q);
                    foreach ($data['brands']      ?? [] as $k => $q)
                        $pipe->hIncrBy("$uTag:b", "b:$k", $q);
                    foreach ($data['custom_tags'] ?? [] as $k => $q)
                        $pipe->hIncrBy("$uTag:b", "c:$k", $q);
                }

            /* 4. streak Lua ---------------------------------------------------- */
            self::evalShaCompat($pipe, self::$streakSha, [
                "$uTag:up:$date",
                "$uTag:up:".Carbon::parse($date)->subDay()->format('Y-m-d'),
                "$uTag:st",
            ], []);

            /* copy streak value into stats hash (1 extra op) */
            $pipe->get("$uTag:st")
                ->hSet($statsKey, 'st', null);   // value will be provided by GET

            /* 5. global + location buckets ------------------------------------ */
            $pipe->hIncrBy("$monthTag:t", 'p', 1);
            if ($xp) $pipe->hIncrByFloat("$monthTag:t", 'xp', $xp);

            foreach (array_filter([
                '{g}'            ,
                "c:$country" => $country,
                "s:$state"   => $state,
                "ci:$city"   => $city,
            ]) as $scope) {
                $tsKey = "$scope:t:p";
                $pipe->hIncrBy($tsKey, $date, 1)->pExpire($tsKey, self::TS_TTL_MS);
            }

            /* 6. global cats / objects --------------------------------------- */
            foreach ($catIncr as $c => $q) $pipe->hIncrBy('{g}:c', $c, $q);
            foreach ($objIncr as $o => $q) $pipe->hIncrBy('{g}:t', $o, $q);
        });
    }

    /* ===================================================================== */

    /** duplicate‑photo gate */
    private static function alreadyProcessed(?int $photoId): bool
    {
        return $photoId ? Redis::sAdd('p:done', $photoId) === 0 : false;
    }

    private static function bootLua(): void
    {
        if (!self::$streakSha) {
            self::$streakSha = Redis::script('load',
                file_get_contents(base_path('app/Services/Redis/lua/streak.lua'))
            );
        }
    }

    private static function evalShaCompat($pipe, string $sha, array $keys, array $argv): void
    {
        try { $pipe->evalSha($sha, $keys,   $argv); return; } catch (\Throwable) {}
        try { $pipe->evalSha($sha, count($keys), ...$keys, ...$argv); return; } catch (\Throwable) {}
        $pipe->evalSha($sha, array_merge($keys, $argv));
    }
}
