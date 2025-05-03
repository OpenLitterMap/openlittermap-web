<?php
declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs (ms) ──────────────────────────────────────────────── */
    private const TS_TTL_MS = 60 * 60 * 24 * 365 * 2 * 1_000; // 2 y

    /* ─── Lua SHA cache ──────────────────────────────────────────── */
    private static ?string $streakSha = null;

    /* ----------------------------------------------------------------
       Public API
    -----------------------------------------------------------------*/
    /**
     * Queue all metrics related to a single photo in one pipeline.
     *
     * @throws RedisException
     */
    public static function queue(Photo $photo): void
    {
        // ── idempotency gate ────────────────────────────────────────
        if (self::alreadyProcessed($photo->id)) {
            return;                             // duplicate photo – ignore
        }

        self::bootLua();

        // ── 1. Common metadata ─────────────────────────────────────
        $uid    = $photo->user_id;
        $uTag   = "{u:$uid}";                   // hash‑slot tag
        $country= $photo->country_id ?? $photo->country?->id;
        $state  = $photo->state_id   ?? $photo->state?->id;
        $city   = $photo->city_id    ?? $photo->city?->id;
        $xp     = (float)($photo->xp ?? 0);

        $ts     = $photo->created_at;
        $date   = $ts->format('Y-m-d');         // field for time‑series HASH
        $monthTag = '{g:'.$ts->format('Y-m').'}'; // hot‑slot spreader

        // ── 2. Per‑photo breakdown ─────────────────────────────────
        $summary  = $photo->summary ?? [];
        $tagsTree = $summary['tags'] ?? [];
        $totals   = $summary['totals'] ?? [];

        // fast cat / obj counters
        $catIncr = $objIncr = [];
        foreach ($tagsTree as $cat => $objs) {
            foreach ($objs as $obj => $data) {
                $q = (int)($data['quantity'] ?? 0);
                $catIncr[$cat] = ($catIncr[$cat] ?? 0) + $q;
                $objIncr[$obj] = ($objIncr[$obj] ?? 0) + $q;
            }
        }

        // ── 3. Pipeline ────────────────────────────────────────────
        Redis::pipeline(static function ($pipe) use (
            $photo,
            $uid, $uTag, $xp, $date, $monthTag,
            $country, $state, $city,
            $catIncr, $objIncr, $tagsTree, $totals
        ) {
            /* 3‑1 user‑level counters (short keys) */
            $pipe->incr("$uTag:u");                    // uploads
            if ($xp) $pipe->incrByFloat("$uTag:xp", $xp);

            foreach ($catIncr as $c => $q) $pipe->hincrBy("$uTag:c", $c, $q);
            foreach ($objIncr as $o => $q) $pipe->hincrBy("$uTag:t", $o, $q);

            /* single hash for materials / brands / custom tags */
            foreach ($tagsTree as $objects) {
                foreach ($objects as $data) {
                    foreach (($data['materials'] ?? []) as $k => $q)
                        $pipe->hincrBy("$uTag:b", "m:$k", $q);

                    foreach (($data['brands'] ?? []) as $k => $q)
                        $pipe->hincrBy("$uTag:b", "b:$k", $q);

                    foreach (($data['custom_tags'] ?? []) as $k => $q)
                        $pipe->hincrBy("$uTag:b", "c:$k", $q);
                }
            }

            /* 3‑2 streak Lua (unchanged) */
            self::evalShaCompat($pipe, self::$streakSha, [
                "$uTag:up:$date",           // KEYS[1]
                "$uTag:up:".\Carbon\Carbon::parse($date)->subDay()->format('Y-m-d'),
                "$uTag:st",                 // streak key
            ], [/* args */]);

            /* 3‑3 global & location totals (bucketed by monthTag) */
            $pipe->hIncrBy("$monthTag:t", 'p', 1);     // photos
            if ($xp) $pipe->hIncrByFloat("$monthTag:t", 'xp', $xp);

            foreach (array_filter([
                'g'                 => null,
                "c:$country"        => $country,
                "s:$state"          => $state,
                "ci:$city"          => $city,
            ]) as $scopeKey => $id) {

                $scope   = $scopeKey === 'g' ? '{g}' : $scopeKey;
                $tsKey   = "$scope:t:p";                // HASH key
                $pipe->hIncrBy($tsKey, $date, 1)
                    ->pExpire($tsKey, self::TS_TTL_MS);
            }

            /* 3‑4 objects / categories (global hashes) */
            foreach ($catIncr as $c => $q)
                $pipe->hIncrBy('{g}:c', $c, $q);

            foreach ($objIncr as $o => $q)
                $pipe->hIncrBy('{g}:t', $o, $q);
        });
    }

    /* ----------------------------------------------------------------
       Helper methods
    -----------------------------------------------------------------*/
    /** true if photo already counted */
    private static function alreadyProcessed(?int $photoId): bool
    {
        // NOTE: swap to BF.ADD / setBit to use Bloom / bitmap
        if ($photoId === null) {
            return false;
        }

        return Redis::sAdd('p:done', $photoId) === 0;
    }

    private static function bootLua(): void
    {
        if (!self::$streakSha) {
            self::$streakSha = Redis::script(
                'load',
                file_get_contents(base_path('app/Services/Redis/lua/streak.lua'))
            );
        }
    }

    /** evalSha wrapper (unchanged) */
    private static function evalShaCompat($pipe, string $sha, array $keys, array $argv): void
    {
        try { $pipe->evalSha($sha, $keys, $argv); return; } catch (\Throwable) {}
        try { $pipe->evalSha($sha, \count($keys), ...$keys, ...$argv); return; } catch (\Throwable) {}
        $pipe->evalSha($sha, array_merge($keys, $argv));
    }
}
