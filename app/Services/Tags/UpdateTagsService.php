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
                    $parsed = $this->classifyTags->classify($tag);
                    $parsed['quantity'] = $qty;
                    if ($parsed['type'] === 'brand') {
                        $globalBrands[] = $parsed;
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
                    $topLevelCustomTags[] = $parsed;
                } else {
                    Log::warning("Unhandled parsed type: {$parsed['type']} for tag: {$tag}");
                }
            }
        }

        // 2) Custom tags
        foreach ($customTagsOld as $ct) {
            $parsed = $this->classifyTags->classify($ct->tag);
            if ($parsed['type'] === 'brand') {
                $parsed['quantity'] = 1;
                $globalBrands[] = $parsed;
            } else {
                $parsed['quantity'] = 1;
                $topLevelCustomTags[] = $parsed;
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
                $materialCache = $this->classifyTags->getMaterialCache();

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
                            if (isset($materialCache[$materialKey])) {
                                $materialsToAttach[] = [
                                    'id'       => $materialCache[$materialKey],
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

        foreach ($brands as $brand) {
            $brandId = $brand['id'];
            $brandKey = $brand['key'] ?? 'unknown';
            $matched = false;

            // Skip if already attached
            if (in_array($brandId, $attachedBrandIds, true)) {
                continue;
            }

            // Check each created PhotoTag for a pivot relationship
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
                    continue;
                }

                // Check if this brand has a pre-existing relationship
                $hasPivot = DB::table('taggables')
                    ->where('category_litter_object_id', $categoryObject->id)
                    ->where('taggable_type', BrandList::class)
                    ->where('taggable_id', $brandId)
                    ->exists();

                if ($hasPivot) {
                    Log::info("✓ Brand attached via existing pivot", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'object' => $objectKey
                    ]);

                    $tagData['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                    $attachedBrandIds[] = $brandId;
                    $matched = true;
                    break; // Move to next brand
                }
            }

            // If no pivot match found, collect for brands-only tag
            if (!$matched) {
                Log::info("✗ No pivot relationship found for brand", [
                    'photo_id' => $photo->id,
                    'brand' => $brandKey
                ]);

                $unmatchedBrands[] = $brand;
            }
        }

        // Create brands-only tag for unmatched brands
        if (!empty($unmatchedBrands)) {
            $this->createBrandsOnlyTag($photo, $unmatchedBrands);
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
        $primary = array_shift($customTags);
        $photoTag = PhotoTag::create([
            'photo_id'              => $photo->id,
            'custom_tag_primary_id' => $primary['id'],
            'quantity'              => $primary['quantity'],
            'picked_up'             => !$photo->remaining,
        ]);
        foreach ($customTags as $idx => $extra) {
            $photoTag->attachExtraTags([$extra], 'custom_tag', $idx);
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
