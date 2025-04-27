<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;
use RedisException;

/**
 * Single-pipeline Redis collector:
 *   • per-user counters, atomic streak
 *   • XP leaderboards (global + location, daily|monthly|yearly)
 *   • time-series buckets
 *   • global + country totals / breakdowns
 *
 * All keys use a hash-tag ({u:id}) so they stay in one slot in cluster mode.
 * One Lua call + ~70 fast commands = one network round-trip.
 */
final class RedisMetricsCollector
{
    /* ----- TTLs ---------------------------------------------------------- */
    private const DAILY_TTL   = 60 * 60 * 24 * 35;       // 35 days
    private const MONTHLY_TTL = 60 * 60 * 24 * 365 * 3;  // 3 years
    private const TS_TTL      = 60 * 60 * 24 * 365 * 2;  // 2 years

    private static ?string $streakSha = null;            // cached Lua SHA

    /* -------------------------------------------------------------------- */
    /**
     * Enqueue metrics for one photo upload in a single pipeline.
     *
     * @throws RedisException
     */
    public static function queue(Photo $photo): void
    {
        /* preload Lua once per PHP worker */
        self::$streakSha ??= Redis::script(
            'load',
            file_get_contents(base_path('app/Services/Redis/lua/streak.lua'))
        );

        // ---------- Gather metadata --------------------------------------
        $uid      = $photo->user_id;
        $country  = $photo->country_id ?? $photo->country?->id;
        $state    = $photo->state_id   ?? $photo->state?->id;
        $city     = $photo->city_id    ?? $photo->city?->id;
        $xp = (float) ($photo->xp ?? 0);

        $ts       = $photo->created_at;
        $date     = $ts->format('Y-m-d');
        $year     = $ts->format('Y');
        $month    = $ts->format('m');
        $ym       = "$year-$month";
        $week     = $ts->format('o-W');

        /* ---- derive per-category / per-object increments --------------- */
        $summary    = $photo->summary ?? [];
        $tagsTree   = $summary['tags']   ?? [];
        $totals     = $summary['totals'] ?? [];
        $byCategory = $totals['by_category'] ?? [];

        $catIncr = [];   // cat-key => qty
        $tagIncr = [];   // obj-key => qty

        foreach ($tagsTree as $catKey => $objects) {
            foreach ($objects as $objKey => $data) {
                $qty              = (int) ($data['quantity'] ?? 0);
                $catIncr[$catKey] = ($catIncr[$catKey] ?? 0) + $qty;
                $tagIncr[$objKey] = ($tagIncr[$objKey] ?? 0) + $qty;
            }
        }

        // user hash-tag keeps keys in one slot (cluster safe)
        $uidTag    = "{u:$uid}";
        $yesterday = $ts->copy()->subDay()->format('Y-m-d');

        /* ---------- Pipeline -------------------------------------------- */
        Redis::pipeline(static function ($pipe) use (
            $uid, $uidTag, $xp,
            $date, $ym, $year, $week, $yesterday,
            $country, $state, $city,
            $catIncr, $tagIncr,
            $totals, $byCategory, $tagsTree
        ) {
            /* 1. USER COUNTERS ----------------------------------------- */
            $pipe->incr("$uidTag:uploads");                         // all-time uploads
            foreach ($catIncr as $c => $q) $pipe->hincrby("$uidTag:cat", $c, $q);
            foreach ($tagIncr as $t => $q) $pipe->hincrby("$uidTag:tag", $t, $q);

            /* 2. STREAK (Lua) ----------------------------------------- */
            /* 2. STREAK (Lua) ----------------------------------------- */
            if (\is_callable([$pipe, 'evalSha'])) {
                $keys = [
                    "$uidTag:uploads:$date",      // KEYS[1]
                    "$uidTag:uploads:$yesterday", // KEYS[2]
                    "$uidTag:streak",             // KEYS[3]
                ];
                $argv = [self::DAILY_TTL];        // ARGV[1]

                try {
                    /* 1️⃣  phpredis ≥ 6  OR Predis-stub form (3 args, two arrays) */
                    $pipe->evalSha(self::$streakSha, $keys, $argv);
                } catch (\TypeError|\ArgumentCountError) {
                    try {
                        /* 2️⃣  phpredis ≤ 5 var-args form */
                        $pipe->evalSha(
                            self::$streakSha,
                            \count($keys),        // numKeys
                            ...$keys,
                            ...$argv
                        );
                    } catch (\TypeError|\ArgumentCountError) {
                        /* 3️⃣  Predis form (sha, keys+args merged) – very old stubs */
                        $pipe->evalSha(self::$streakSha, array_merge($keys, $argv));
                    }
                }
            }

            /* 3. XP LEADERBOARDS -------------------------------------- */
            $pipe->zincrby('lb:xp', $xp, $uid);                       // all-time
            foreach (["lb:xp:$date" => self::DAILY_TTL,
                         "lb:xp:$ym"   => self::MONTHLY_TTL,
                         "lb:xp:$year" => self::MONTHLY_TTL] as $k => $ttl) {
                $pipe->zincrby($k, $xp, $uid)->expire($k, $ttl);
            }

            foreach ([['country', $country], ['state', $state], ['city', $city]] as [$type, $id]) {
                if (!$id) continue;
                $base = "lb:loc:$type:$id";
                $pipe->zincrby("$base:total", $xp, $uid);            // all-time
                foreach (["$base:$date" => self::DAILY_TTL,
                             "$base:$ym"   => self::MONTHLY_TTL,
                             "$base:$year" => self::MONTHLY_TTL] as $k => $ttl) {
                    $pipe->zincrby($k, $xp, $uid)->expire($k, $ttl);
                }
            }

            /* 4. TIME-SERIES BUCKETS ---------------------------------- */
            $scopes = array_filter([
                'global',
                $country ? "country:$country" : null,
                $state   ? "state:$state"     : null,
                $city    ? "city:$city"       : null,
                "user:$uid",
            ]);
            foreach ($scopes as $s) {
                $pipe->incr("$s:ts:daily:photos:$date")->expire("$s:ts:daily:photos:$date", self::TS_TTL);
                $pipe->incr("$s:ts:weekly:photos:$week")->expire("$s:ts:weekly:photos:$week", self::TS_TTL);
                $pipe->incr("$s:ts:monthly:photos:$ym")->expire("$s:ts:monthly:photos:$ym", self::TS_TTL);
                $pipe->incr("$s:ts:yearly:photos:$year")->expire("$s:ts:yearly:photos:$year", self::TS_TTL);
            }

            /* 5. GLOBAL TOTALS + BREAKDOWN ---------------------------- */
            $pipe->hincrby('global:totals', 'photos', 1);
            $pipe->hincrby('global:totals', 'tags',        $totals['total_tags']    ?? 0);
            $pipe->hincrby('global:totals', 'custom_tags', $totals['custom_tags']   ?? 0);

            foreach ($byCategory as $cat => $q) $pipe->hincrby('global:totals:categories', $cat, $q);

            foreach ($tagsTree as $objects) {
                foreach ($objects as $objKey => $data) {
                    $pipe->hincrby('global:totals:objects', $objKey, $data['quantity']);
                    foreach (($data['materials']    ?? []) as $k => $q) $pipe->hincrby('global:totals:materials',    $k, $q);
                    foreach (($data['brands']       ?? []) as $k => $q) $pipe->hincrby('global:totals:brands',       $k, $q);
                    foreach (($data['custom_tags']  ?? []) as $k => $q) $pipe->hincrby('global:totals:custom_tags_breakdown', $k, $q);
                }
            }

            /* 6. COUNTRY TOTALS (optional) ---------------------------- */
            if ($country) {
                $p = "country:$country";
                $pipe->hincrby("$p:totals", 'photos', 1);
                $pipe->hincrby("$p:totals", 'tags',        $totals['total_tags']  ?? 0);
                $pipe->hincrby("$p:totals", 'custom_tags', $totals['custom_tags'] ?? 0);

                foreach ($byCategory as $cat => $q) $pipe->hincrby("$p:totals:categories", $cat, $q);
                foreach ($tagsTree as $objects) {
                    foreach ($objects as $objKey => $data)
                        $pipe->hincrby("$p:totals:objects", $objKey, $data['quantity']);
                }
            }
        });
    }
}
