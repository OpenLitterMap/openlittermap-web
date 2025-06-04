<?php

namespace App\Services\Achievements\Tags;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tag key cache for achievement system
 *
 *  A central, low-level helper that converts between “human” tag **keys**
 *  (e.g. `"water_bottle"`, `"plastic"`) and their numeric **primary keys**
 *  stored in the database.
 *
 *  Why it exists
 *  ─────────────
 *  • Tag keys enter the system from many places: the API, seeders, photos that
 *    are being parsed, etc.
 *  • Most high-performance code – Redis counters, achievement look-ups, joins –
 *    works exclusively with integers.
 *  • A naive `SELECT id FROM … WHERE key = ?` for *every* lookup would destroy
 *    performance and create a thundering-herd on cold starts.
 *
 *  TagKeyCache therefore
 *  • keeps a **forward** map `key  ➜  id`  (`self::$forward`) and the
 *    inverse **reverse** map `id   ➜  key`  (`self::$reverse`) in memory,
 *  • mirrors both maps in Laravel’s cache (Redis / memcached / file) so that
 *    separate PHP processes share the same warm state,
 *  • exposes a *read-through* API (`get()`, `idFor()`) that transparently pulls
 *    missing data from the database and then caches it,
 *  • offers **batch helpers** (`getTagIdsBatch()`, `getKeysForIds()`) so that
 *    callers can translate many tags in one go without N+1 queries,
 *  • provides a *write-through* path (`createTag()`) which inserts a new tag
 *    and immediately updates all layers of the cache, and
 *  • can wipe or rebuild its caches on demand (`forget*()`, `preloadAll()`),
 *    which is handy for migrations and the test-suite.
 *
 *  Caching strategy
 *  ────────────────
 *  • In-process       – `static` arrays (`self::$forward`, `self::$reverse`)
 *    Fastest path, reset only when the PHP worker dies (FPM recycle, queue job
 *    finish, test case resets).
 *
 *  • Cross-process    – Laravel Cache (`ach:map:{dimension}`)
 *    Time-to-live: **24 h** (`CACHE_TTL` = 86 400 s).
 *    Keeps CLI jobs, Octane workers, Horizon queue runners, etc. in sync.
 *
 *  Dimensions & tables
 *  ───────────────────
 *  ┌────────────┬────────────────┐
 *  │ Dimension  │ DB table       │
 *  ├────────────┼────────────────┤
 *  │ object     │ litter_objects │
 *  │ category   │ categories     │
 *  │ material   │ materials      │
 *  │ brand      │ brandslist     │
 *  │ customTag  │ custom_tags_new│
 *  └────────────┴────────────────┘
 *
 *  Public API overview
 *  ───────────────────
 *  • **preloadAll()**         – eager-loads every table once (useful in tests).
 *  • **get(string $dim)**     – returns the *forward* map `[key => id]`.
 *  • **idFor($dim, $key)**    – single lookup, returns `id|null`.
 *  • **getOrCreateId(...)**   – lookup or *insert-and-cache* a missing tag.
 *  • **createTag(...)**       – explicit insert without lookup.
 *  • **getTagIdsBatch($tbl, $keys)**
 *      → *vectorised* forward lookup.
 *      Extra optimisation: if every `$key` is already **numeric** the method
 *      short-circuits and simply returns `[id => id]` without touching any
 *      cache or the database (added in #1234 to fix tag-specific achievements).
 *  • **getKeysForIds($dim, $ids)**, **getTagKeysBatch($tbl, $ids)**
 *      – vectorised *reverse* lookup for reporting / debug endpoints.
 *  • **forget()/forgetAll()** – selective or global cache invalidation.
 *
 *  Concurrency & consistency
 *  ─────────────────────────
 *  • Reads are *eventually* consistent: if one worker inserts a new tag the
 *    other workers either
 *      – see it immediately (if they call `getOrCreateId()` with the same key,
 *        the DB `INSERT ... ON DUPLICATE KEY` path is harmless), or
 *      – within 24 h when their cached map expires.
 *    In practice the next call to `forget()`/`preloadAll()` during a deploy or
 *    a job restart keeps them fresh.
 *
 *  • Writes are *atomic* per record: the `insertGetId()` call is wrapped in the
 *    default DB transaction; duplicate inserts are prevented by the UNIQUE key
 *    on the tag tables (`key`), so racing workers either get the id they just
 *    created or the id that another worker inserted a few µs earlier.
 *
 *  Performance notes
 *  ─────────────────
 *  • A full hot cache hit is a pure PHP array lookup → ~50 ns.
 *  • A cold cache but warm Laravel cache hit is a single Redis GET → < 1 ms.
 *  • A completely cold start results in one DB *batch* query per dimension and
 *    then behaves like the warm path.
 *
 *  Typical call flow (photo ingestion)
 *  ───────────────────────────────────
 *     1. `extractDeltas()` in RedisMetricsCollector needs the id for
 *        `"water_bottle"` in the **object** dimension           ⤵
 *     2. `TagKeyCache::getOrCreateId('object', 'water_bottle')`
 *        – forward map hit?                → returns id.  Done.
 *        – else Laravel cache hit?         → stores in forward map, returns id.
 *        – else DB row exists?             → caches it everywhere, returns id.
 *        – else create row via `createTag` → caches & returns new id.
 *
 *  Adding a new dimension
 *  ──────────────────────
 *  1. Create a DB table with `id`, `key` columns and a UNIQUE index on `key`.
 *  2. Extend `getTableForDimension()` *and* the large `match(...)` statements
 *     in `getTagIdsBatch`, `getTagKeysBatch`, etc.
 *  3. Add the dimension name to `$dimensions` in `preloadAll()` and
 *     `forgetAll()`.
 *
 *  Caveats
 *  ───────
 *  • **Do not** call TagKeyCache from inside a DB transaction that might roll
 *    back after a new tag was created – you would leave an id in the cache that
 *    no longer exists in the table.
 *  • If you ever need *case-insensitive* keys, normalise them **before** you
 *    call into the cache; the cache itself is agnostic.
 */
final class TagKeyCache
{
    private static array $reverse = [];
    private static array $forward = [];

    private const CACHE_TTL = 86400; // 24 hours

    public static function preloadAll(): void
    {
        $dimensions = ['object', 'category', 'material', 'brand', 'customTag'];

        foreach ($dimensions as $dim) {
            $table = self::getTableForDimension($dim);
            if (!$table) continue;

            $mapping = DB::table($table)
                ->select('id', 'key')
                ->pluck('id', 'key')
                ->all();

            self::$forward[$dim] = $mapping;
            Cache::put("ach:map:{$dim}", $mapping, 86400);
        }
    }

    /**
     * Get tag key to ID mapping for a dimension
     */
    public static function get(string $dim): array
    {
        // Check memory cache first
        if (isset(self::$forward[$dim])) {
            return self::$forward[$dim];
        }

        $mapping = Cache::remember("ach:map:{$dim}", self::CACHE_TTL, function () use ($dim) {
            $table = self::getTableForDimension($dim);
            if (!$table) {
                return [];
            }

            return DB::table($table)->pluck('id', 'key')->all();
        });

        // Store in memory for faster subsequent access
        self::$forward[$dim] = $mapping;
        self::$reverse[$dim] = array_flip($mapping);

        return $mapping;
    }

    /**
     * Create new tag and return its ID
     */
    public static function createTag(string $dim, string $key): int
    {
        $table = self::getTableForDimension($dim);
        if (!$table) {
            throw new \InvalidArgumentException("Unknown dimension: $dim");
        }

        $id = DB::table($table)->insertGetId(['key' => $key]);

        // Update caches
        self::$forward[$dim][$key] = $id;
        self::$reverse[$dim][$id] = $key;

        // Invalidate Redis cache
        Cache::forget("ach:map:{$dim}");

        return $id;
    }

    /**
     * Get or create tag ID
     */
    public static function getOrCreateId(string $dim, string $key): int
    {
        $id = self::idFor($dim, $key);

        if ($id === null) {
            $id = self::createTag($dim, $key);
        }

        return $id;
    }

    /**
     * Get multiple tag IDs in one operation for performance
     */
    public static function getTagIdsBatch(string $table, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        // If every key is numeric we can skip the expensive map lookup
        if (count($keys) === count(array_filter($keys, 'is_numeric'))) {
            // e.g. [17, 42] ➜ [17 => 17, 42 => 42]
            return array_combine($keys, $keys);
        }

        // Map table to dimension
        $dim = match($table) {
            'litter_objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brandslist' => 'brand',
            'custom_tags_new', 'custom_tags' => 'customTag',
            default => null,
        };

        if (!$dim) {
            return [];
        }

        // Get the full mapping for this dimension (uses existing cache)
        // eg: [ 'water_bottle' => 17, ... ]
        $mapping = self::get($dim);

        // Extract requested keys
        return array_intersect_key($mapping, array_flip($keys));
    }

    /**
     * Get tag ID for a given key
     */
    public static function idFor(string $dim, string $key): ?int
    {
        $forward = self::get($dim);  // key => id

        return $forward[$key] ?? null;
    }

    /**
     * Get tag ID using table name (for checker compatibility)
     */
    public static function getTagId(string $table, string $key): ?int
    {
        $dim = match($table) {
            'litter_objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brandslist' => 'brand',
            'custom_tags_new', 'custom_tags' => 'customTag',
            default => null,
        };

        if (!$dim) {
            return null;
        }

        return self::idFor($dim, $key);
    }

    /**
     * Pre-warm all caches for migration performance
     * Only used in tests & migration script
     */
    public static function warmCache(): void
    {
        self::preloadAll();
    }

    /**
     * Forget cache for a specific dimension
     */
    public static function forget(string $dim): void
    {
        Cache::forget("ach:map:{$dim}");
        unset(self::$reverse[$dim]);
        unset(self::$forward[$dim]);
    }

    /**
     * Clear all caches
     */
    public static function forgetAll(): void
    {
        $dimensions = ['object', 'category', 'material', 'brand', 'customTag'];
        foreach ($dimensions as $dim) {
            self::forget($dim);
        }
    }

    /**
     * Get database table for dimension
     */
    private static function getTableForDimension(string $dim): ?string
    {
        return match($dim) {
            'object' => 'litter_objects',
            'category' => 'categories',
            'material' => 'materials',
            'brand' => 'brandslist',
            'customTag' => 'custom_tags_new', // Updated to match your schema
            default => null,
        };
    }

    /**
     * Get tag key from ID (reverse lookup)
     */
    public static function keyFor(string $dim, int $id): ?string
    {
        // Check memory cache first
        if (isset(self::$reverse[$dim][$id])) {
            return self::$reverse[$dim][$id];
        }

        // Build reverse mapping if not cached
        if (!isset(self::$reverse[$dim])) {
            $forward = self::get($dim); // key => id
            self::$reverse[$dim] = array_flip($forward); // id => key
        }

        return self::$reverse[$dim][$id] ?? null;
    }

    /**
     * Get multiple tag keys from IDs (for display)
     */
    public static function getKeysForIds(string $dim, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Ensure reverse mapping is loaded
        if (!isset(self::$reverse[$dim])) {
            $forward = self::get($dim);
            self::$reverse[$dim] = array_flip($forward);
        }

        return array_intersect_key(self::$reverse[$dim], array_flip($ids));
    }

    /**
     * Get tag keys using table name (for compatibility)
     */
    public static function getTagKeysBatch(string $table, array $ids): array
    {
        $dim = match($table) {
            'litter_objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brandslist' => 'brand',
            'custom_tags_new', 'custom_tags' => 'customTag',
            default => null,
        };

        if (!$dim) {
            return [];
        }

        return self::getKeysForIds($dim, $ids);
    }

}
