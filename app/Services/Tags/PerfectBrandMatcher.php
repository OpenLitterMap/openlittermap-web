<?php

namespace App\Services\Tags;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Perfect Brand Matching
 *
 * ONLY attaches brands to objects when there's an exact relationship defined.
 * No guessing. No defaults. Perfect or nothing.
 */
class PerfectBrandMatcher
{
    /**
     * Match brands to objects using ONLY defined relationships
     *
     * @return array ['matched' => [...], 'skipped' => [...]]
     */
    public static function matchBrandsToObjects(
        array $brands,
        array $objectsByCategory,
        int $photoId
    ): array {
        $matched = [];
        $skipped = [];

        foreach ($brands as $brandKey => $brandQuantity) {
            $brandMatched = false;

            // Get brand ID
            $brandId = self::getBrandId($brandKey);
            if (!$brandId) {
                $skipped[$brandKey] = [
                    'quantity' => $brandQuantity,
                    'reason' => 'brand_not_found',
                ];
                continue;
            }

            // Get defined relationships for this brand from database
            $relationships = self::getBrandRelationships($brandKey);

            if (empty($relationships)) {
                $skipped[$brandKey] = [
                    'quantity' => $brandQuantity,
                    'reason' => 'no_relationships_defined',
                ];
                continue;
            }

            // Try to find exact match in photo's objects
            foreach ($relationships as $rel) {
                $categoryKey = $rel->category;
                $objectKey = $rel->object;

                // Check if photo has this exact category and object
                if (isset($objectsByCategory[$categoryKey])) {
                    foreach ($objectsByCategory[$categoryKey] as $photoObject) {
                        if ($photoObject['key'] === $objectKey) {
                            // PERFECT MATCH FOUND!
                            $matched[] = [
                                'brand_key' => $brandKey,
                                'brand_id' => $brandId,
                                'brand_quantity' => $brandQuantity,
                                'category_key' => $categoryKey,
                                'object_key' => $objectKey,
                                'object_id' => $photoObject['id'],
                                'photo_tag_id' => $photoObject['photo_tag_id'] ?? null,
                            ];
                            $brandMatched = true;
                            break 2; // Break both loops
                        }
                    }
                }
            }

            if (!$brandMatched) {
                $skipped[$brandKey] = [
                    'quantity' => $brandQuantity,
                    'reason' => 'no_matching_objects_in_photo',
                ];
            }
        }

        // Log results for monitoring
        if (count($brands) > 0) {
            Log::info("Photo {$photoId} brand matching", [
                'total_brands' => count($brands),
                'matched' => count($matched),
                'skipped' => count($skipped),
                'match_rate' => count($brands) > 0 ? round(count($matched) / count($brands) * 100, 1) : 0,
            ]);
        }

        return [
            'matched' => $matched,
            'skipped' => $skipped,
        ];
    }

    /**
     * Get brand ID from brandslist table
     */
    private static function getBrandId(string $brandKey): ?int
    {
        return Cache::remember("brand_id:{$brandKey}", 3600, function () use ($brandKey) {
            return DB::table('brandslist')->where('key', $brandKey)->value('id');
        });
    }

    /**
     * Get defined relationships for a brand from taggables
     */
    private static function getBrandRelationships(string $brandKey): array
    {
        return Cache::remember("brand_relationships:{$brandKey}", 3600, function () use ($brandKey) {
            $brandId = self::getBrandId($brandKey);
            if (!$brandId) {
                return [];
            }

            return DB::table('taggables')
                ->join('category_litter_object', 'taggables.category_litter_object_id', '=', 'category_litter_object.id')
                ->join('categories', 'category_litter_object.category_id', '=', 'categories.id')
                ->join('litter_objects', 'category_litter_object.litter_object_id', '=', 'litter_objects.id')
                ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                ->where('taggables.taggable_id', $brandId)
                ->select(
                    'categories.key as category',
                    'litter_objects.key as object',
                    'category_litter_object.id as clo_id'
                )
                ->get()
                ->toArray();
        });
    }

    /**
     * Clear cache for a brand (use after updating relationships)
     */
    public static function clearBrandCache(string $brandKey): void
    {
        Cache::forget("brand_id:{$brandKey}");
        Cache::forget("brand_relationships:{$brandKey}");
    }

    /**
     * Attach matched brands to PhotoTags
     * This should be called after brands have been matched
     */
    public static function attachMatchedBrands(array $matched, int $photoId): int
    {
        $attached = 0;

        foreach ($matched as $match) {
            // Find the PhotoTag for this object
            $photoTag = DB::table('photo_tags')
                ->where('photo_id', $photoId)
                ->where('litter_object_id', $match['object_id'])
                ->first();

            if (!$photoTag) {
                Log::warning("PhotoTag not found for object", [
                    'photo_id' => $photoId,
                    'object_id' => $match['object_id'],
                    'brand' => $match['brand_key'],
                ]);
                continue;
            }

            // Check if brand already attached
            $exists = DB::table('photo_tag_extra_tags')
                ->where('photo_tag_id', $photoTag->id)
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $match['brand_id'])
                ->exists();

            if (!$exists) {
                DB::table('photo_tag_extra_tags')->insert([
                    'photo_tag_id' => $photoTag->id,
                    'tag_type' => 'brand',
                    'tag_type_id' => $match['brand_id'],
                    'quantity' => $match['brand_quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $attached++;
            }
        }

        return $attached;
    }

    /**
     * Create brands-only PhotoTag for skipped brands
     * Optional: You may choose NOT to create these for perfect matching
     */
    public static function createBrandsOnlyTag(array $skipped, int $photoId, bool $remaining): void
    {
        if (empty($skipped)) {
            return;
        }

        // Get brands category
        $brandsCategoryId = DB::table('categories')->where('key', 'brands')->value('id');
        if (!$brandsCategoryId) {
            $brandsCategoryId = DB::table('categories')->insertGetId([
                'key' => 'brands',
                'name' => 'Brands',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create PhotoTag for unmatched brands
        $photoTagId = DB::table('photo_tags')->insertGetId([
            'photo_id' => $photoId,
            'category_id' => $brandsCategoryId,
            'quantity' => array_sum(array_column($skipped, 'quantity')),
            'picked_up' => !$remaining,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attach each skipped brand
        foreach ($skipped as $brandKey => $data) {
            $brandId = self::getBrandId($brandKey);
            if (!$brandId) {
                // Create brand if it doesn't exist
                $brandId = DB::table('brandslist')->insertGetId([
                    'key' => $brandKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('photo_tag_extra_tags')->insert([
                'photo_tag_id' => $photoTagId,
                'tag_type' => 'brand',
                'tag_type_id' => $brandId,
                'quantity' => $data['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
