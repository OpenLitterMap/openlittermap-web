<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTagsService
{
    public ClassifyTagsService $classifyTags;
    public GeneratePhotoSummaryService $generatePhotoSummaryService;

    public function __construct(
        ClassifyTagsService $classifyTags,
        GeneratePhotoSummaryService $generatePhotoSummaryService
    )
    {
        $this->classifyTags = $classifyTags;
        $this->generatePhotoSummaryService = $generatePhotoSummaryService;
    }

    public function updateTags(Photo $photo): void
    {
        if ($photo->migrated_at !== null) {
            return;
        }

        [$originalTags, $customTagsOld] = $this->getTags($photo);

        // If no tags at all, just mark migrated
        if (empty($originalTags) && $customTagsOld->isEmpty()) {
            $photo->update(['migrated_at' => now()]);
            return;
        }

        $parsedTags = $this->parseTags($originalTags, $customTagsOld, $photo->id);

        $this->createPhotoTags($photo, $parsedTags);

        $this->generatePhotoSummaryService->run($photo);

        $photo->update(['migrated_at' => now()]);
    }

    public function getTags(Photo $photo): array
    {
        $tags = $photo->tags() ?? [];
        $customTagsOld = $photo->customTags ?? new EloquentCollection();

        return [$tags, $customTagsOld];
    }

    public function parseTags(array $originalTags, EloquentCollection $customTagsOld, int $photoId): array
    {
        $groups             = [];
        $globalBrands       = [];
        $topLevelCustomTags = [];

        // 1) Category-based blocks
        foreach ($originalTags as $categoryKey => $items) {
            if ($categoryKey === 'brands') {
                foreach ($items as $tag => $qtyRaw) {
                    $qty = (int) $qtyRaw;
                    if ($qty <= 0) {
                        continue;
                    }

                    // Try to get brand ID from brandslist table
                    $brandId = DB::table('brandslist')
                        ->where('key', $tag)
                        ->value('id');

                    if ($brandId) {
                        $globalBrands[] = [
                            'id' => $brandId,
                            'key' => $tag,
                            'type' => 'brand',
                            'quantity' => $qty
                        ];
                    } else {
                        Log::warning("Brand not found in brandslist", [
                            'brand_key' => $tag,
                            'photo_id' => $photoId
                        ]);
                        // Still add it but without ID (will become brands-only tag)
                        $globalBrands[] = [
                            'id' => null,
                            'key' => $tag,
                            'type' => 'brand',
                            'quantity' => $qty
                        ];
                    }
                }
                continue;
            }

            $category = $this->classifyTags->getCategory($categoryKey);
            if (! $category) {
                Log::warning("No matching Category for key: {$categoryKey}");
                continue;
            }

            $groups[$categoryKey] = [
                'category_id' => $category->id,
                'objects'     => [],
                'brands'      => [],
                'materials'   => [],
            ];

            foreach ($items as $tag => $qtyRaw) {
                $qty = (int) $qtyRaw;
                if ($qty <= 0) {
                    continue;
                }

                // Initial classification
                $parsed = $this->classifyTags->classify($tag);
                $parsed['quantity'] = $qty;

                // Determine classification
                if ($parsed['type'] === 'brand') {
                    $globalBrands[] = $parsed;
                } elseif ($parsed['type'] === 'material') {
                    $groups[$categoryKey]['materials'][] = $parsed;
                } elseif ($parsed['type'] === 'object') {
                    // Check if this is a deprecated tag with material(s)
                    $mapping = ClassifyTagsService::normalizeDeprecatedTag($tag);
                    if ($mapping !== null) {
                        $parsed['object'] = $mapping['object'];
                        $parsed['materials'] = $mapping['materials'] ?? [];
                    }
                    $groups[$categoryKey]['objects'][] = $parsed;
                } elseif ($parsed['type'] === 'custom_tag') {
                    // Ensure custom tag exists in custom_tags_new table
                    $customTagId = DB::table('custom_tags_new')
                        ->where('key', $tag)
                        ->value('id');

                    if (!$customTagId) {
                        // Create the custom tag in the correct table
                        $customTagId = DB::table('custom_tags_new')->insertGetId([
                            'key' => $tag,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        Log::info("Created custom tag in custom_tags_new", [
                            'key' => $tag,
                            'id' => $customTagId
                        ]);
                    }

                    $parsed['id'] = $customTagId;
                    $topLevelCustomTags[] = $parsed;
                } else {
                    // Unknown type - treat as custom tag
                    $customTagId = DB::table('custom_tags_new')
                        ->where('key', $tag)
                        ->value('id');

                    if (!$customTagId) {
                        $customTagId = DB::table('custom_tags_new')->insertGetId([
                            'key' => $tag,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        Log::info("Created unknown tag as custom tag in custom_tags_new", [
                            'key' => $tag,
                            'id' => $customTagId
                        ]);
                    }

                    $topLevelCustomTags[] = [
                        'id' => $customTagId,
                        'key' => $tag,
                        'type' => 'custom_tag',
                        'quantity' => $qty
                    ];
                }
            }
        }

        // 2) Custom tags
        foreach ($customTagsOld as $ct) {
            // Check if this custom tag is actually a brand
            $brandId = DB::table('brandslist')
                ->where('key', $ct->tag)
                ->value('id');

            if ($brandId) {
                $globalBrands[] = [
                    'id' => $brandId,
                    'key' => $ct->tag,
                    'type' => 'brand',
                    'quantity' => 1
                ];
            } else {
                // It's a real custom tag - ensure it exists in custom_tags_new
                $customTagId = DB::table('custom_tags_new')
                    ->where('key', $ct->tag)
                    ->value('id');

                if (!$customTagId) {
                    // Create the custom tag in the correct table
                    $customTagId = DB::table('custom_tags_new')->insertGetId([
                        'key' => $ct->tag,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Log::info("Migrated custom tag to custom_tags_new", [
                        'key' => $ct->tag,
                        'id' => $customTagId
                    ]);
                }

                $topLevelCustomTags[] = [
                    'id' => $customTagId,
                    'key' => $ct->tag,
                    'type' => 'custom_tag',
                    'quantity' => 1
                ];
            }
        }

        return [
            'groups'       => $groups,
            'globalBrands' => $globalBrands,
            'customTags'   => $topLevelCustomTags,
        ];
    }

    public function createPhotoTags(Photo $photo, array $parsedTags): void
    {
        $groups             = $parsedTags['groups'] ?? [];
        $globalBrands       = $parsedTags['globalBrands'] ?? [];
        $topLevelCustomTags = $parsedTags['customTags'] ?? [];

        $createdPhotoTags = [];
        $hasObjects = false;

        // First pass: Create PhotoTags for objects and materials
        foreach ($groups as $categoryKey => $categoryData) {
            $categoryId = $categoryData['category_id'] ?? null;
            $objects    = $categoryData['objects'] ?? [];

            if (!empty($objects)) {
                $hasObjects = true;

                foreach ($objects as $object) {
                    $photoTag = PhotoTag::create([
                        'photo_id'         => $photo->id,
                        'category_id'      => $categoryId,
                        'litter_object_id' => $object['id'],
                        'quantity'         => $object['quantity'],
                        'picked_up'        => !$photo->remaining,
                    ]);

                    $createdPhotoTags[] = [
                        'photo_tag'   => $photoTag,
                        'category_id' => $categoryId,
                        'object'      => $object,
                    ];

                    // Attach materials from deprecated tags
                    if (!empty($object['materials'])) {
                        $materialsToAttach = [];
                        foreach ($object['materials'] as $materialKey) {
                            // Get material ID using TagKeyCache
                            $materialId = \App\Services\Achievements\Tags\TagKeyCache::idFor('material', $materialKey);
                            if ($materialId) {
                                $materialsToAttach[] = [
                                    'id'       => $materialId,
                                    'key'      => $materialKey,
                                    'quantity' => $object['quantity']
                                ];
                            }
                        }

                        if (!empty($materialsToAttach)) {
                            $photoTag->attachExtraTags($materialsToAttach, 'material', 0);
                        }
                    }
                }
            }
        }

        // Second pass: Attach brands using ONLY existing pivot relationships
        if ($hasObjects && !empty($globalBrands)) {
            $this->attachBrandsUsingExistingPivots($photo, $globalBrands, $createdPhotoTags);
        }

        // If no objects at all but have brands, create brands-only tag
        if (!$hasObjects && !empty($globalBrands)) {
            $this->createBrandsOnlyTag($photo, $globalBrands);
            return;
        }

        // Custom-only
        if (!$hasObjects && !empty($topLevelCustomTags)) {
            $this->createCustomOnlyTag($photo, $topLevelCustomTags);
            return;
        }

        // Attach any top-level custom tags to the last created tag
        if ($hasObjects && !empty($topLevelCustomTags)) {
            $this->attachCustomTagsToLast($photo, $topLevelCustomTags);
        }
    }

    /**
     * Attach brands using ONLY existing pivot relationships
     * Unmatched brands get their own brands-only PhotoTag
     */
    private function attachBrandsUsingExistingPivots(Photo $photo, array $brands, array $createdPhotoTags): void
    {
        $attachedBrandIds = [];
        $unmatchedBrands = [];

        // Pretty print photo summary
        $this->logPhotoSummary($photo, $brands, $createdPhotoTags);

        foreach ($brands as $brand) {
            $brandId = $brand['id'] ?? null;
            $brandKey = $brand['key'] ?? 'unknown';
            $matched = false;

            if (!$brandId) {
                Log::warning("Brand has no ID", [
                    'photo_id' => $photo->id,
                    'brand_key' => $brandKey
                ]);
                $unmatchedBrands[] = $brand;
                continue;
            }

            // Skip if already attached
            if (in_array($brandId, $attachedBrandIds, true)) {
                continue;
            }

            // Log what we're searching for
            Log::info("════════════════════════════════");
            Log::info("🔍 SEARCHING FOR BRAND: {$brandKey} (ID: {$brandId})");

            // Get ALL possible pivots for this brand
            $this->logAllBrandPivots($brandKey, $brandId);

            // Check each created PhotoTag for a pivot relationship
            $possibleMatches = [];

            foreach ($createdPhotoTags as $tagData) {
                $objectId = $tagData['object']['id'];
                $objectKey = $tagData['object']['key'] ?? 'unknown';
                $categoryId = $tagData['category_id'] ?? null;

                if (!$categoryId) {
                    continue;
                }

                // Check if there's a CategoryObject pivot
                $categoryObject = DB::table('category_litter_object')
                    ->where('category_id', $categoryId)
                    ->where('litter_object_id', $objectId)
                    ->first();

                if (!$categoryObject) {
                    Log::debug("  ❌ No category_object for {$objectKey}");
                    continue;
                }

                // Check if this brand has a pre-existing relationship
                $hasPivot = DB::table('taggables')
                    ->where('category_litter_object_id', $categoryObject->id)
                    ->where('taggable_type', BrandList::class)
                    ->where('taggable_id', $brandId)
                    ->exists();

                if ($hasPivot) {
                    $possibleMatches[] = [
                        'object' => $objectKey,
                        'object_id' => $objectId,
                        'category_object_id' => $categoryObject->id,
                        'tag_data' => $tagData
                    ];

                    Log::info("  ✅ Found pivot: {$brandKey} → {$objectKey} (category_object_id: {$categoryObject->id})");
                } else {
                    Log::debug("  ❌ No pivot: {$brandKey} → {$objectKey}");
                }
            }

            // Decision logic
            if (count($possibleMatches) === 1) {
                // Only one match, use it
                $match = $possibleMatches[0];
                Log::info("📌 ATTACHING: {$brandKey} → {$match['object']} (only match)");

                $match['tag_data']['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                $attachedBrandIds[] = $brandId;
                $matched = true;

            } elseif (count($possibleMatches) > 1) {
                // Multiple matches - log them all and choose
                Log::warning("⚠️  MULTIPLE PIVOTS FOUND for {$brandKey}:");
                foreach ($possibleMatches as $pm) {
                    Log::warning("    - {$pm['object']} (category_object_id: {$pm['category_object_id']})");
                }

                // Choose the first one (you could add smarter logic here)
                $match = $possibleMatches[0];
                Log::info("📌 CHOOSING FIRST: {$brandKey} → {$match['object']}");

                $match['tag_data']['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                $attachedBrandIds[] = $brandId;
                $matched = true;
            }

            // If no pivot match found
            if (!$matched) {
                Log::warning("❌ NO MATCH: {$brandKey} will become brands-only tag");
                $unmatchedBrands[] = $brand;
            }

            Log::info("────────────────────────────────");
        }

        // Create brands-only tag for unmatched brands
        if (!empty($unmatchedBrands)) {
            $this->createBrandsOnlyTag($photo, $unmatchedBrands);
        }
    }

    /**
     * Log a pretty summary of the photo's tags
     */
    private function logPhotoSummary(Photo $photo, array $brands, array $createdPhotoTags): void
    {
        Log::info("╔════════════════════════════════════════════════════════╗");
        Log::info("║ PHOTO {$photo->id} MIGRATION                          ║");
        Log::info("╠════════════════════════════════════════════════════════╣");

        // Log objects
        Log::info("║ OBJECTS:");
        foreach ($createdPhotoTags as $tag) {
            $objectKey = $tag['object']['key'] ?? 'unknown';
            $objectId = $tag['object']['id'] ?? '?';
            $quantity = $tag['object']['quantity'] ?? 1;
            Log::info("║   - {$objectKey} (ID: {$objectId}, Qty: {$quantity})");
        }

        // Log brands
        Log::info("║ BRANDS:");
        foreach ($brands as $brand) {
            $brandKey = $brand['key'] ?? 'unknown';
            $brandId = $brand['id'] ?? '?';
            $quantity = $brand['quantity'] ?? 1;
            Log::info("║   - {$brandKey} (ID: {$brandId}, Qty: {$quantity})");
        }

        Log::info("╚════════════════════════════════════════════════════════╝");
    }

    /**
     * Log all existing pivots for a brand
     */
    private function logAllBrandPivots(string $brandKey, int $brandId): void
    {
        $pivots = DB::table('taggables as t')
            ->join('category_litter_object as clo', 't.category_litter_object_id', '=', 'clo.id')
            ->join('litter_objects as lo', 'clo.litter_object_id', '=', 'lo.id')
            ->join('categories as c', 'clo.category_id', '=', 'c.id')
            ->where('t.taggable_type', BrandList::class)
            ->where('t.taggable_id', $brandId)
            ->select(
                't.id as taggable_id',
                't.category_litter_object_id',
                'c.key as category',
                'lo.key as object',
                't.quantity'
            )
            ->get();

        if ($pivots->isEmpty()) {
            Log::warning("  ⚠️  NO PIVOTS EXIST for {$brandKey}");
        } else {
            Log::info("  📋 EXISTING PIVOTS for {$brandKey}:");
            foreach ($pivots as $pivot) {
                Log::info("    - {$pivot->category}/{$pivot->object} (taggable.id: {$pivot->taggable_id}, cat_obj_id: {$pivot->category_litter_object_id})");
            }
        }
    }

    /**
     * Create a brands-only PhotoTag for brands without object relationships
     */
    private function createBrandsOnlyTag(Photo $photo, array $brands): void
    {
        if (empty($brands)) {
            return;
        }

        // Extract brand keys for logging
        $brandKeys = array_map(fn($b) => $b['key'] ?? 'unknown', $brands);

        Log::info("Creating brands-only tag for unmatched brands", [
            'photo_id' => $photo->id,
            'brands' => $brandKeys,
            'count' => count($brands)
        ]);

        $photoTag = PhotoTag::create([
            'photo_id'    => $photo->id,
            'category_id' => Category::where('key', 'brands')->value('id'),
            'quantity'    => array_sum(array_column($brands, 'quantity')),
            'picked_up'   => !$photo->remaining,
        ]);

        $photoTag->attachExtraTags($brands, 'brand', 0);
    }

    private function createCustomOnlyTag(Photo $photo, array $customTags): void
    {
        if (empty($customTags)) {
            return;
        }

        $primary = array_shift($customTags);

        // All custom tags should now have valid IDs from custom_tags_new
        if (!isset($primary['id']) || !$primary['id']) {
            Log::error("Custom tag has no valid ID", [
                'photo_id' => $photo->id,
                'custom_tag' => $primary
            ]);
            return;
        }

        $photoTag = PhotoTag::create([
            'photo_id'              => $photo->id,
            'custom_tag_primary_id' => $primary['id'],
            'quantity'              => $primary['quantity'] ?? 1,
            'picked_up'             => !$photo->remaining,
        ]);

        // Attach any additional custom tags as extra tags
        foreach ($customTags as $idx => $extra) {
            if (isset($extra['id']) && $extra['id']) {
                $photoTag->attachExtraTags([$extra], 'custom_tag', $idx);
            }
        }
    }

    private function attachCustomTagsToLast(Photo $photo, array $customTags): void
    {
        $last = $photo->photoTags()->latest()->first();
        if (!$last) {
            return;
        }
        foreach ($customTags as $idx => $extra) {
            $last->attachExtraTags([$extra], 'custom_tag', $idx);
        }
    }
}
