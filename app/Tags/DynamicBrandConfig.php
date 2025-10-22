<?php

namespace App\Tags;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dynamic brand configuration using database instead of hard-coded file
 * Relationships are stored in the taggables table
 */
class DynamicBrandConfig
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get allowed objects for a brand in a category
     * Uses database with caching for performance
     */
    public static function getAllowedObjects(string $brandKey, string $categoryKey): array
    {
        $cacheKey = "brand_objects:{$brandKey}:{$categoryKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($brandKey, $categoryKey) {
            // Get brand ID
            $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
            if (!$brandId) {
                return [];
            }

            // Get category ID
            $categoryId = DB::table('categories')->where('key', $categoryKey)->value('id');
            if (!$categoryId) {
                return [];
            }

            // Get allowed objects from taggables
            return DB::table('taggables')
                ->join('category_litter_object', 'taggables.category_litter_object_id', '=', 'category_litter_object.id')
                ->join('litter_objects', 'category_litter_object.litter_object_id', '=', 'litter_objects.id')
                ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                ->where('taggables.taggable_id', $brandId)
                ->where('category_litter_object.category_id', $categoryId)
                ->pluck('litter_objects.key')
                ->toArray();
        });
    }

    /**
     * Check if a brand can attach to a specific object
     */
    public static function canBrandAttachToObject(
        string $brandKey,
        string $categoryKey,
        string $objectKey
    ): bool {
        $allowedObjects = self::getAllowedObjects($brandKey, $categoryKey);
        return in_array($objectKey, $allowedObjects);
    }

    /**
     * Get all categories a brand can appear in
     */
    public static function getBrandCategories(string $brandKey): array
    {
        $cacheKey = "brand_categories:{$brandKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($brandKey) {
            $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
            if (!$brandId) {
                return [];
            }

            return DB::table('taggables')
                ->join('category_litter_object', 'taggables.category_litter_object_id', '=', 'category_litter_object.id')
                ->join('categories', 'category_litter_object.category_id', '=', 'categories.id')
                ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                ->where('taggables.taggable_id', $brandId)
                ->distinct()
                ->pluck('categories.key')
                ->toArray();
        });
    }

    /**
     * Add a brand-object relationship
     */
    public static function addRelationship(
        string $brandKey,
        string $categoryKey,
        string $objectKey
    ): bool {
        // Get IDs
        $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
        $categoryId = DB::table('categories')->where('key', $categoryKey)->value('id');
        $objectId = DB::table('litter_objects')->where('key', $objectKey)->value('id');

        if (!$brandId || !$categoryId || !$objectId) {
            return false;
        }

        // Get or create category_litter_object
        $clo = DB::table('category_litter_object')
            ->where('category_id', $categoryId)
            ->where('litter_object_id', $objectId)
            ->first();

        if (!$clo) {
            $cloId = DB::table('category_litter_object')->insertGetId([
                'category_id' => $categoryId,
                'litter_object_id' => $objectId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $cloId = $clo->id;
        }

        // Check if relationship exists
        $exists = DB::table('taggables')
            ->where('category_litter_object_id', $cloId)
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->where('taggable_id', $brandId)
            ->exists();

        if (!$exists) {
            DB::table('taggables')->insert([
                'category_litter_object_id' => $cloId,
                'taggable_type' => 'App\\Models\\Litter\\Tags\\BrandList',
                'taggable_id' => $brandId,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Clear cache
            self::clearBrandCache($brandKey);

            return true;
        }

        return false;
    }

    /**
     * Remove a brand-object relationship
     */
    public static function removeRelationship(
        string $brandKey,
        string $categoryKey,
        string $objectKey
    ): bool {
        $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
        $categoryId = DB::table('categories')->where('key', $categoryKey)->value('id');
        $objectId = DB::table('litter_objects')->where('key', $objectKey)->value('id');

        if (!$brandId || !$categoryId || !$objectId) {
            return false;
        }

        $deleted = DB::table('taggables')
            ->join('category_litter_object', 'taggables.category_litter_object_id', '=', 'category_litter_object.id')
            ->where('category_litter_object.category_id', $categoryId)
            ->where('category_litter_object.litter_object_id', $objectId)
            ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->where('taggables.taggable_id', $brandId)
            ->delete();

        if ($deleted > 0) {
            self::clearBrandCache($brandKey);
            return true;
        }

        return false;
    }

    /**
     * Clear cache for a brand
     */
    public static function clearBrandCache(string $brandKey): void
    {
        Cache::forget("brand_categories:{$brandKey}");

        // Clear all category combinations
        $categories = DB::table('categories')->pluck('key');
        foreach ($categories as $category) {
            Cache::forget("brand_objects:{$brandKey}:{$category}");
        }
    }

    /**
     * Check if brand has any relationships configured
     */
    public static function brandHasRelationships(string $brandKey): bool
    {
        $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
        if (!$brandId) {
            return false;
        }

        return DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->where('taggable_id', $brandId)
            ->exists();
    }
}
