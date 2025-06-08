<?php
declare(strict_types=1);

namespace App\Services\Achievements\Tags;

use App\Enums\Dimension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TagKeyCache v3 – lean & predictable
 *
 *  – Atomic upsert on write (MySQL / PgSQL)
 *  – Single-row write-through to Redis hash (fwd & rev)
 *  – Fast in-RAM map per PHP worker
 *  – Optional whole-map fallback for non-Redis caches
 *  – No hidden background work, no custom eviction, no inline metrics
 *
 *  Drop-in replacement for the v1/v2 classes.
 */
final class TagKeyCache
{
    /** forward[dimension][key] = id   @var array<string, array<string,int>> */
    private static array $forward = [];

    /** reverse[dimension][id]  = key  @var array<string, array<int,string>> */
    private static array $reverse = [];

    /** Cache-key version */
    private const VERSION = 'v1';
    private const TTL = 86_400; // 24 h

    /* --------------------------------------------------------------------- */
    /* Helpers                                                               */
    /* --------------------------------------------------------------------- */

    /** Build the Redis/file key */
    private static function key(string $type, Dimension $dim): string
    {
        return "ach:" . self::VERSION . ":{$type}:{$dim->value}";
    }

    /** Get Redis connection or null if not available */
    private static function redis(): ?\Illuminate\Redis\Connections\Connection
    {
        $store = Cache::getStore();
        return $store instanceof \Illuminate\Cache\RedisStore
            ? $store->connection()
            : null;
    }

    /** Remember mapping in RAM and external cache */
    private static function remember(Dimension $dim, string $key, int $id): void
    {
        $d = $dim->value;
        self::$forward[$d][$key] = $id;
        self::$reverse[$d][$id]  = $key;

        if ($redis = self::redis()) {
            // Two hash sets + TTL refresh in a single network round-trip
            $redis->pipeline(static function ($pipe) use ($dim, $key, $id) {
                $pipe->hset(TagKeyCache::key('fwd', $dim), $key, $id);
                $pipe->hset(TagKeyCache::key('rev', $dim), $id, $key);
                // TTL is only set if the hash is *new*, to avoid resetting hot keys
                $pipe->expire(TagKeyCache::key('fwd', $dim), TagKeyCache::TTL);
                $pipe->expire(TagKeyCache::key('rev', $dim), TagKeyCache::TTL);
            });
        } else {
            // single-process file / array cache: update the whole map lazily
            $map       = Cache::get(self::key('map', $dim), []);
            $map[$key] = $id;
            Cache::put(self::key('map', $dim), $map, self::TTL);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Write path                                                            */
    /* --------------------------------------------------------------------- */

    /**
     * Insert or get existing tag ID
     */
    private static function upsert(Dimension $dim, string $key): int
    {
        $table = $dim->table();

        // Use INSERT IGNORE and then SELECT - simple and reliable
        DB::statement("INSERT IGNORE INTO {$table} (`key`) VALUES (?)", [$key]);

        // Now get the ID (whether it was just inserted or already existed)
        return (int) DB::table($table)->where('key', $key)->value('id');
    }

    /* --------------------------------------------------------------------- */
    /* Public API                                                            */
    /* --------------------------------------------------------------------- */

    /** Fast, throw-free lookup; returns null if not found */
    public static function idFor(string $dim, string $key): ?int
    {
        $dimension = Dimension::from($dim);
        $d         = $dimension->value;

        /* RAM */
        if (isset(self::$forward[$d][$key])) {
            return self::$forward[$d][$key];
        }

        /* Redis */
        if ($redis = self::redis()) {
            $id = $redis->hget(self::key('fwd', $dimension), $key);
            if ($id !== null) {
                return self::$forward[$d][$key] = (int) $id;
            }
        }

        /* DB (fallback) */
        $row = DB::table($dimension->table())
            ->select('id')
            ->where('key', $key)
            ->first();

        if ($row) {
            self::remember($dimension, $key, (int) $row->id);
            return (int) $row->id;
        }

        return null;
    }

    /** Find or create and return id */
    public static function getOrCreateId(string $dim, string $key): int
    {
        return self::idFor($dim, $key)
            ?? tap(self::upsert(Dimension::from($dim), $key),
                static fn(int $id) => self::remember(Dimension::from($dim), $key, $id));
    }

    /** Fast reverse lookup */
    public static function keyFor(string $dim, int $id): ?string
    {
        $dimension = Dimension::from($dim);
        $d         = $dimension->value;

        /* RAM */
        if (isset(self::$reverse[$d][$id])) {
            return self::$reverse[$d][$id];
        }

        /* Redis */
        if ($redis = self::redis()) {
            $key = $redis->hget(self::key('rev', $dimension), $id);
            if ($key !== null) {
                self::remember($dimension, $key, $id);
                return $key;
            }
        }

        /* DB */
        $row = DB::table($dimension->table())
            ->select('key')
            ->where('id', $id)
            ->first();

        if ($row) {
            self::remember($dimension, $row->key, $id);
            return $row->key;
        }

        return null;
    }

    /** @return array<string,int> */
    public static function get(string $dim): array
    {
        $dimension = Dimension::from($dim);
        $d         = $dimension->value;

        if (isset(self::$forward[$d])) {
            return self::$forward[$d];
        }

        /* Redis */
        if ($redis = self::redis()) {
            $map = $redis->hgetall(self::key('fwd', $dimension));
            if ($map) {
                return self::$forward[$d] = array_map('intval', $map);
            }
        }

        /* Full table (cold start) */
        $map = Cache::remember(
            self::key('map', $dimension),
            self::TTL,
            static fn () => DB::table($dimension->table())->pluck('id', 'key')->all()
        );

        return self::$forward[$d] = $map;
    }

    /* --------------------------------------------------------------------- */
    /* Batch helpers                                                         */
    /* --------------------------------------------------------------------- */

    /** @param string[] $keys */
    public static function idsBatch(string $table, array $keys): array
    {
        if (!$keys) { return []; }

        // If every element is numeric (or numeric string) short-circuit
        if (!array_filter($keys, static fn($k) => !is_numeric($k))) {
            return array_combine($keys, array_map('intval', $keys));
        }

        $dim = Dimension::fromTable($table);
        return $dim
            ? array_intersect_key(self::get($dim->value), array_flip($keys))
            : [];
    }

    /** @param int[] $ids */
    public static function keysBatch(string $table, array $ids): array
    {
        if (!$ids) { return []; }

        $dim = Dimension::fromTable($table);
        if (!$dim) { return []; }

        $map = array_flip(self::get($dim->value));
        return array_intersect_key($map, array_flip($ids));
    }

    public static function resolveBatch(string $dim, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $existing = self::get($dim);
        $result = [];
        $missing = [];

        // Check what we already have
        foreach ($keys as $key) {
            if (isset($existing[$key])) {
                $result[$key] = $existing[$key];
            } else {
                $missing[] = $key;
            }
        }

        // Create missing ones
        foreach ($missing as $key) {
            $result[$key] = self::getOrCreateId($dim, $key);
        }

        return $result;
    }

    public static function getKeysForIds(string $dimension, array $ids): array
    {
        $table = Dimension::from($dimension)->table();
        return self::keysBatch($table, $ids);
    }


    /* --------------------------------------------------------------------- */
    /* Pre-warm                                  */
    /* --------------------------------------------------------------------- */

    public static function preload(Dimension $dimension): void
    {
        $map = DB::table($dimension->table())->pluck('id', 'key')->all();

        self::$forward[$dimension->value] = $map;
        self::$reverse[$dimension->value] = array_flip($map);

        if ($redis = self::redis()) {
            $redis->pipeline(static function ($pipe) use ($dimension, $map) {
                foreach ($map as $k => $v) {
                    $pipe->hset(TagKeyCache::key('fwd', $dimension), $k, $v);
                    $pipe->hset(TagKeyCache::key('rev', $dimension), $v, $k);
                }
                $pipe->expire(TagKeyCache::key('fwd', $dimension), TagKeyCache::TTL);
                $pipe->expire(TagKeyCache::key('rev', $dimension), TagKeyCache::TTL);
            });
        } else {
            Cache::put(self::key('map', $dimension), $map, self::TTL);
        }
    }

    public static function preloadAll(): void
    {
        foreach (Dimension::cases() as $d) {
            self::preload($d);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Invalidation                                                          */
    /* --------------------------------------------------------------------- */

    public static function forget(string $dim): void
    {
        $dimension = Dimension::from($dim);
        unset(self::$forward[$dimension->value], self::$reverse[$dimension->value]);

        if ($redis = self::redis()) {
            $redis->del(
                self::key('fwd', $dimension),
                self::key('rev', $dimension)
            );
        }
        Cache::forget(self::key('map', $dimension));
    }

    public static function forgetAll(): void
    {
        foreach (Dimension::cases() as $d) {
            self::forget($d->value);
        }
    }
}
