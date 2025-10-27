<?php

namespace App\Services\Tags;

use App\Models\CustomTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * @deprecated
 * Unifies brands from two sources:
 * 1. Brand::types() - official brands in brands table
 * 2. CustomTags with "brand:" or "brand=" prefix
 */
class UnifiedBrandService
{
    /**
     * Extract all brands from a photo (both sources)
     */
    public static function extractBrandsFromPhoto(int $photoId): array
    {
        $brands = [];

        // 1. Get brands from brands table
        $photo = DB::table('photos')->where('id', $photoId)->first();
        if ($photo && $photo->brands_id) {
            $brandRecord = DB::table('brands')->where('id', $photo->brands_id)->first();
            if ($brandRecord) {
                foreach ($brandRecord as $key => $value) {
                    if ($value > 0 && $key !== 'id' && !in_array($key, ['created_at', 'updated_at'])) {
                        $brands[$key] = (int) $value;
                    }
                }
            }
        }

        // 2. Get brands from custom tags
        $customBrands = self::extractCustomBrands($photoId);
        foreach ($customBrands as $brandKey => $qty) {
            // Merge with existing or add new
            $brands[$brandKey] = ($brands[$brandKey] ?? 0) + $qty;
        }

        return $brands;
    }

    /**
     * Extract brands from custom tags
     */
    private static function extractCustomBrands(int $photoId): array
    {
        $customTags = CustomTag::where('photo_id', $photoId)->get();
        $brands = [];

        foreach ($customTags as $customTag) {
            $tag = $customTag->tag;

            // Check for brand: or brand= prefix
            if (preg_match('/^brand[:=](.+)$/i', $tag, $matches)) {
                $brandKey = self::normalizeBrandKey($matches[1]);
                $brands[$brandKey] = ($brands[$brandKey] ?? 0) + 1;
            }
        }

        return $brands;
    }

    /**
     * Normalize brand key for consistency
     */
    public static function normalizeBrandKey(string $brand): string
    {
        // Convert to lowercase
        $normalized = strtolower(trim($brand));

        // Replace spaces and special chars with underscores
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);

        // Remove trailing underscores
        $normalized = trim($normalized, '_');

        // Map common variations
        $mappings = [
            '7_eleven' => 'seven_eleven',
            '7eleven' => 'seven_eleven',
            'coca_cola' => 'coke',
            'cocacola' => 'coke',
            'mc_donalds' => 'mcdonalds',
            'mc_donald_s' => 'mcdonalds',
            'burger_king' => 'burgerking',
            'dr_pepper' => 'drpepper',
            // Add more mappings as needed
        ];

        return $mappings[$normalized] ?? $normalized;
    }

    /**
     * Get all unique brands from database (both sources)
     */
    public static function getAllUniqueBrands(): array
    {
        return Cache::remember('all_unique_brands', 3600, function () {
            $brands = [];

            // 1. Get all columns from brands table (except system columns)
            $columns = DB::getSchemaBuilder()->getColumnListing('brands');
            $excludeColumns = ['id', 'created_at', 'updated_at'];
            foreach ($columns as $column) {
                if (!in_array($column, $excludeColumns)) {
                    $brands[$column] = 'brands_table';
                }
            }

            // 2. Get all brand: and brand= tags from custom_tags
            $customBrandTags = CustomTag::where('tag', 'like', 'brand:%')
                ->orWhere('tag', 'like', 'brand=%')
                ->pluck('tag')
                ->unique();

            foreach ($customBrandTags as $tag) {
                if (preg_match('/^brand[:=](.+)$/i', $tag, $matches)) {
                    $brandKey = self::normalizeBrandKey($matches[1]);
                    if (!isset($brands[$brandKey])) {
                        $brands[$brandKey] = 'custom_tag';
                    }
                }
            }

            return $brands;
        });
    }

    /**
     * Create or get brand from brandslist table
     */
    public static function ensureBrandExists(string $brandKey): int
    {
        // Check if brand exists in brandslist
        $brand = DB::table('brandslist')->where('key', $brandKey)->first();

        if ($brand) {
            return $brand->id;
        }

        // Create new brand
        return DB::table('brandslist')->insertGetId([
            'key' => $brandKey,
            'crowdsourced' => true,  // Mark as user-generated
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Migrate custom brand tags to new system
     */
    public static function migrateCustomBrandTags(int $photoId): array
    {
        $customBrands = self::extractCustomBrands($photoId);
        $migrated = [];

        foreach ($customBrands as $brandKey => $qty) {
            // Ensure brand exists in brandslist table
            $brandId = self::ensureBrandExists($brandKey);

            $migrated[] = [
                'key' => $brandKey,
                'id' => $brandId,
                'quantity' => $qty,
                'source' => 'custom_tag',
            ];
        }

        return $migrated;
    }
}
