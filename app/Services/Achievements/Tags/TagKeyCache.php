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

    /**
     * Get tag key to ID mapping for a dimension
     */
    public static function get(string $dim): array
    {
        // Check memory cache first
        if (isset(self::$forward[$dim])) {
            return self::$forward[$dim];
        }

        $mapping = Cache::rememberForever("ach:map:{$dim}", function () use ($dim) {
            $table = self::getTableForDimension($dim);
            if (!$table) {
                return [];
            }

            return DB::table($table)->pluck('id', 'key')->all();
        });

        // Store in memory for faster subsequent access
        self::$forward[$dim] = $mapping;

        return $mapping;
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
     * Only used in tests & migrtion script
     */
    public static function warmCache(): void
    {
        $dimensions = ['object', 'category', 'material', 'brand', 'customTag'];

        foreach ($dimensions as $dim) {
            self::get($dim); // This will load and cache
        }
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
}
