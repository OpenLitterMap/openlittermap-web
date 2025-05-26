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

    /**
     * Get tag key to ID mapping for a dimension
     */
    public static function get(string $dim): array
    {
        return Cache::rememberForever("ach:map:{$dim}", function () use ($dim) {
            $table = self::getTableForDimension($dim);
            if (!$table) {
                return [];
            }

            return DB::table($table)->pluck('key', 'id')->all();
        });
    }

    /**
     * Get tag ID for a given key
     */
    public static function idFor(string $dim, string $key): ?int
    {
        if (!isset(self::$reverse[$dim])) {
            self::$reverse[$dim] = array_flip(self::get($dim));
        }

        return self::$reverse[$dim][$key] ?? null;
    }

    /**
     * Forget cache for a specific dimension
     */
    public static function forget(string $dim): void
    {
        Cache::forget("ach:map:{$dim}");
        unset(self::$reverse[$dim]);
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
            'customTag' => 'custom_tags',
            default => null,
        };
    }
}
