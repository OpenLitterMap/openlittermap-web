<?php

namespace App\Services\Achievements\Tags;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tag key cache for achievement system
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
                ->select('key', 'id')
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

            return DB::table($table)->pluck('key', 'id')->all();
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
