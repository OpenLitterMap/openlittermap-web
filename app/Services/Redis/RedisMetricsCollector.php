<?php
declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;
use RedisException;

final class RedisMetricsCollector
{
    /* ─── TTLs ─────────────────────────────────────────────────────── */
    private const DAILY_TTL   = 60 * 60 * 24 * 35;       // 35 days
    private const MONTHLY_TTL = 60 * 60 * 24 * 365 * 3;  // 3 years
    private const TS_TTL      = 60 * 60 * 24 * 365 * 2;  // 2 years

    /* ─── Key templates ────────────────────────────────────────────── */
    private const KEY_USER_XP      = '{u:%d}:xp';
    private const KEY_USER_UPLOADS = '{u:%d}:uploads';

    /* ─── Lua SHA cache ────────────────────────────────────────────── */
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
        self::bootLua();

        // ——— 1. Common metadata ————————————————————————————————
        $uid      = $photo->user_id;
        $uidTag   = "{u:$uid}"; // hash-tag keeps all keys in a slot
        $country  = $photo->country_id ?? $photo->country?->id;
        $state    = $photo->state_id   ?? $photo->state?->id;
        $city     = $photo->city_id    ?? $photo->city?->id;
        $xp       = (float) ($photo->xp ?? 0);

        $ts       = $photo->created_at; // Carbon instance
        $date     = $ts->format('Y-m-d');
        $year     = $ts->format('Y');
        $month    = $ts->format('m');
        $ym       = "$year-$month"; // safe ‘YYYY-MM’
        $week     = $ts->format('o-W');
        $yesterday= $ts->copy()->subDay()->format('Y-m-d');

        // ——— 2. Per-photo breakdown ————————————————————————————
        $summary    = $photo->summary ?? [];
        $tagsTree   = $summary['tags']   ?? [];
        $totals     = $summary['totals'] ?? [];
        $byCategory = $totals['by_category'] ?? [];

        $catIncr = []; // catKey ⇒ qty
        $tagIncr = []; // objKey ⇒ qty
        foreach ($tagsTree as $catKey => $objects) {
            foreach ($objects as $objKey => $data) {
                $qty              = (int) ($data['quantity'] ?? 0);
                $catIncr[$catKey] = ($catIncr[$catKey] ?? 0) + $qty;
                $tagIncr[$objKey] = ($tagIncr[$objKey] ?? 0) + $qty;
            }
        }

        /* Capture only the bits we still need inside the closure   */
        $objectsForGlobal = $tagsTree; // already trimmed above

        // ——— 3. Pipeline ————————————————————————————————
        Redis::pipeline(static function ($pipe) use (
            $uid, $uidTag, $xp,
            $date, $ym, $year, $week, $yesterday,
            $country, $state, $city,
            $catIncr, $tagIncr,
            $totals, $byCategory, $objectsForGlobal
        ) {

            /* — 3.1 User counters ———————————————— */
            $pipe->incr(sprintf(self::KEY_USER_UPLOADS, $uid)); // total uploads
            $pipe->incrByFloat(sprintf(self::KEY_USER_XP, $uid), $xp); // total XP

            foreach ($catIncr as $c => $q) $pipe->hincrby("$uidTag:cat", $c, $q);
            foreach ($tagIncr as $t => $q) $pipe->hincrby("$uidTag:tag", $t, $q);

            /* — 3.2 Streak Lua ———————————————— */
            self::evalShaCompat($pipe, self::$streakSha, [
                "$uidTag:uploads:$date",      // KEYS[1]
                "$uidTag:uploads:$yesterday", // KEYS[2]
                "$uidTag:streak",             // KEYS[3]
            ], [self::DAILY_TTL]);

            /* — 3.3 XP leaderboards —————————— */
            self::zincrWithWindow($pipe, 'lb:xp', $xp, $uid, $date, $ym, $year);
            self::zincrByLocation($pipe, $xp, $uid, $date, $ym, $year, [
                ['country', $country],
                ['state',   $state],
                ['city',    $city],
            ]);

            /* — 3.4 Time-series buckets —————— */
            self::bumpPhotoCounters($pipe, $date, $ym, $year, $week, [
                'global',
                $country ? "country:$country" : null,
                $state   ? "state:$state"     : null,
                $city    ? "city:$city"       : null,
                "user:$uid",
            ]);

            /* — 3.5 Global totals ——————————— */
            $pipe->hincrby(     'global:totals', 'photos', 1);
            $pipe->hincrByFloat('global:totals', 'xp',     $xp);
            $pipe->hincrby(     'global:totals', 'tags',        $totals['total_tags']  ?? 0);
            $pipe->hincrby(     'global:totals', 'custom_tags', $totals['custom_tags'] ?? 0);

            foreach ($byCategory as $cat => $q)
                $pipe->hincrby('global:totals:categories', $cat, $q);

            foreach ($objectsForGlobal as $objects) {
                foreach ($objects as $objKey => $data) {
                    $pipe->hincrby('global:totals:objects', $objKey, $data['quantity']);
                    foreach (($data['materials'] ?? []) as $k => $q)
                        $pipe->hincrby('global:totals:materials', $k, $q);
                    foreach (($data['brands'] ?? []) as $k => $q)
                        $pipe->hincrby('global:totals:brands', $k, $q);
                    foreach (($data['custom_tags'] ?? []) as $k => $q)
                        $pipe->hincrby('global:totals:custom_tags_breakdown', $k, $q);
                }
            }

            /* — 3.6 Country totals (optional) — */
            if ($country) {
                $p = "country:$country";
                $pipe->hincrby($p.':totals', 'photos',      1);
                $pipe->hincrby($p.':totals', 'tags',        $totals['total_tags']  ?? 0);
                $pipe->hincrby($p.':totals', 'custom_tags', $totals['custom_tags'] ?? 0);

                foreach ($byCategory as $cat => $q)
                    $pipe->hincrby("$p:totals:categories", $cat, $q);

                foreach ($objectsForGlobal as $objects) {
                    foreach ($objects as $objKey => $data)
                        $pipe->hincrby("$p:totals:objects", $objKey, $data['quantity']);
                }
            }
        });
    }

    /* ----------------------------------------------------------------
       Helper methods
    -----------------------------------------------------------------*/
    private static function bootLua(): void
    {
        if (self::$streakSha === null) {
            self::$streakSha = Redis::script(
                'load',
                file_get_contents(base_path('app/Services/Redis/lua/streak.lua'))
            );
        }
    }

    /**
     * Increment a ZSET in several time windows (day, month, year).
     */
    private static function zincrWithWindow(
        $pipe, string $base, float $score, int $member,
        string $date, string $ym, string $year
    ): void {
        $pipe->zincrby($base, $score, $member);
        foreach ([
                     "$base:$date" => self::DAILY_TTL,
                     "$base:$ym"   => self::MONTHLY_TTL,
                     "$base:$year" => self::MONTHLY_TTL,
                 ] as $k => $ttl) {
            $pipe->zincrby($k, $score, $member)->pexpire($k, $ttl * 1000);
        }
    }

    /**
     * Leaderboards broken down by country/state/city.
     */
    private static function zincrByLocation(
        $pipe, float $xp, int $uid,
        string $date, string $ym, string $year,
        array $tuples
    ): void {
        foreach ($tuples as [$type, $id]) {
            if (!$id) continue;
            $base = "lb:loc:$type:$id";
            self::zincrWithWindow($pipe, "$base:total", $xp, $uid, $date, $ym, $year);
        }
    }

    /**
     * Increment time-series counters for each scope.
     */
    private static function bumpPhotoCounters(
        $pipe, string $date, string $ym, string $year, string $week, array $scopes
    ): void {
        foreach (array_filter($scopes) as $scope) {
            $pipe->incr("$scope:ts:daily:photos:$date")  ->pexpire("$scope:ts:daily:photos:$date",  self::TS_TTL * 1000);
            $pipe->incr("$scope:ts:weekly:photos:$week") ->pexpire("$scope:ts:weekly:photos:$week", self::TS_TTL * 1000);
            $pipe->incr("$scope:ts:monthly:photos:$ym")  ->pexpire("$scope:ts:monthly:photos:$ym",  self::TS_TTL * 1000);
            $pipe->incr("$scope:ts:yearly:photos:$year") ->pexpire("$scope:ts:yearly:photos:$year", self::TS_TTL * 1000);
        }
    }

    /**
     * Call evalSha with whichever argument form the client supports.
     *
     * @param mixed  $pipe  PhpRedis|Predis pipeline instance
     * @param string $sha
     * @param array  $keys
     * @param array  $argv
     */
    private static function evalShaCompat($pipe, string $sha, array $keys, array $argv): void
    {
        // try phpredis ≥ 6 or Predis-stub form: (sha, array $keys, array $args)
        try {
            $pipe->evalSha($sha, $keys, $argv);
            return;
        } catch (\TypeError|\ArgumentCountError) {
            /* fall through */
        }

        // try phpredis ≤ 5: (sha, int $numKeys, ...$keys, ...$args)
        try {
            $pipe->evalSha($sha, \count($keys), ...$keys, ...$argv);
            return;
        } catch (\TypeError|\ArgumentCountError) {
            /* fall through */
        }

        // very old Predis-stub: (sha, array $keys+$args merged)
        $pipe->evalSha($sha, array_merge($keys, $argv));
    }
}
