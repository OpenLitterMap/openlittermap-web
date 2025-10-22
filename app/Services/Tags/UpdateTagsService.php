<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

                // If undefined and plural, try singular fallback
                if ($parsed['type'] === 'undefined' && str_ends_with($tag, 's')) {
                    $singular = substr($tag, 0, -1);
                    $parsed2 = $this->classifyTags->classify($singular);
                    $parsed2['quantity'] = $qty;
                    if ($parsed2['type'] !== 'undefined') {
                        $parsed = $parsed2;
                    }
                }

                switch ($parsed['type']) {
                    case 'object':
                        $groups[$categoryKey]['objects'][] = $parsed;
                        break;
                    case 'brand':
                        $groups[$categoryKey]['brands'][] = $parsed;
                        break;
                    case 'material':
                        $groups[$categoryKey]['materials'][] = $parsed;
                        break;
                    default:
                        Log::info("Skipping tag type: {$parsed['type']} for tag: {$tag}");
                }
            }
        }

        // 2) Log brand parsing status
        if (!empty($globalBrands)) {
            Log::info("Global brands parsed successfully", [
                'photo_id' => $photoId,
                'brand_count' => count($globalBrands),
                'brands' => array_map(fn($b) => ['key' => $b['key'] ?? 'unknown', 'qty' => $b['quantity']], $globalBrands),
            ]);
        }

        // 3) Legacy customTags
        if ($customTagsOld->isNotEmpty()) {
            foreach ($customTagsOld as $old) {
                $parsed = $this->classifyTags->normalizeCustomTag($old->tag);

                if ($parsed['type'] !== 'custom') {
                    continue;
                }

                $topLevelCustomTags[] = [
                    'id'           => $parsed['id'],
                    'key'          => $parsed['key'],
                    'quantity'     => $parsed['quantity'] ?? 1,
                    'category_key' => $parsed['category_key'] ?? null,
                ];
            }
        }

        return [
            'groups'             => $groups,
            'globalBrands'       => $globalBrands,
            'topLevelCustomTags' => $topLevelCustomTags,
        ];
    }

    protected function createPhotoTags(Photo $photo, array $parsedTags): void
    {
        $groups             = $parsedTags['groups'];
        $globalBrands       = $parsedTags['globalBrands'];
        $topLevelCustomTags = $parsedTags['topLevelCustomTags'];

        $hasObjects = false;
        $createdPhotoTags = [];

        // First pass: Create all object PhotoTags
        foreach ($groups as $categoryKey => $group) {
            if (! empty($group['objects'])) {
                $hasObjects = true;

                foreach ($group['objects'] as $index => $object) {
                    $photoTag = $photo->createTag([
                        'category_id'      => $group['category_id'],
                        'litter_object_id' => $object['id'],
                        'quantity'         => $object['quantity'],
                        'picked_up'        => ! $photo->remaining,
                    ]);

                    // Store for brand matching
                    $createdPhotoTags[] = [
                        'photo_tag' => $photoTag,
                        'object' => $object,
                        'category_id' => $group['category_id'],
                        'category_key' => $categoryKey,
                        'index' => $index
                    ];

                    // Handle materials from deprecated tag mappings
                    if (!empty($object['materials'])) {
                        $materialCache = $this->classifyTags->materialMap();
                        $materialsToAttach = [];

                        foreach ($object['materials'] as $materialKey) {
                            if (isset($materialCache[$materialKey])) {
                                $materialsToAttach[] = [
                                    'id'       => $materialCache[$materialKey],
                                    'key'      => $materialKey,
                                    'quantity' => $object['quantity']
                                ];
                            } else {
                                Log::warning("Material '{$materialKey}' not found in cache", [
                                    'photo_id' => $photo->id,
                                    'object' => $object['key'] ?? 'unknown'
                                ]);
                            }
                        }

                        if (!empty($materialsToAttach)) {
                            $photoTag->attachExtraTags($materialsToAttach, 'material', 0);
                        }
                    }
                }
            }
        }

        // Second pass: Match brands with priority-based fallback
        if ($hasObjects && !empty($globalBrands)) {
            $totalObjects = count($createdPhotoTags);
            $totalBrands = count($globalBrands);

            if ($totalObjects === 1 && $totalBrands === 1) {
                // SPECIAL CASE: 1 object + 1 brand = automatic association
                Log::info("Single object + single brand - automatic association", [
                    'photo_id' => $photo->id,
                    'object' => $createdPhotoTags[0]['object']['key'] ?? 'unknown',
                    'brand' => $globalBrands[0]['key'] ?? 'unknown'
                ]);

                $createdPhotoTags[0]['photo_tag']->attachExtraTags($globalBrands, 'brand', 0);

                // Also create the pivot relationship for future use
                $this->createPivotIfMissing(
                    $createdPhotoTags[0]['category_id'],
                    $createdPhotoTags[0]['object']['id'],
                    $globalBrands[0]['id']
                );
            } else {
                // MULTIPLE OBJECTS: Use pivot lookup first, then priority fallback
                $this->attachBrandsWithPriorityFallback($photo, $globalBrands, $createdPhotoTags);
            }
        }

        // Brands-only
        if (! $hasObjects && empty($topLevelCustomTags) && ! empty($globalBrands)) {
            $this->createBrandsOnlyTag($photo, $globalBrands);
            return;
        }

        // Custom-only
        if (! $hasObjects && ! empty($topLevelCustomTags)) {
            $this->createCustomOnlyTag($photo, $topLevelCustomTags);
            return;
        }

        // Attach any top-level custom tags to the last created tag
        if ($hasObjects && ! empty($topLevelCustomTags)) {
            $this->attachCustomTagsToLast($photo, $topLevelCustomTags);
        }
    }

    /**
     * Attach brands using pivot lookup with priority-based fallback
     */
    private function attachBrandsWithPriorityFallback(Photo $photo, array $brands, array $createdPhotoTags): void
    {
        $attachedBrandIds = [];

        foreach ($brands as $brand) {
            $brandId = $brand['id'];
            $brandKey = $brand['key'] ?? 'unknown';
            $brandQty = $brand['quantity'];

            // Skip if already attached
            if (in_array($brandId, $attachedBrandIds, true)) {
                continue;
            }

            $matched = false;
            $pivotMatches = [];
            $allPossibleMatches = [];

            // RULE 1: Check for pivot relationships
            foreach ($createdPhotoTags as $tagData) {
                $objectId = $tagData['object']['id'];
                $objectKey = $tagData['object']['key'] ?? 'unknown';
                $categoryId = $tagData['category_id'] ?? null;

                if (!$categoryId) {
                    continue;
                }

                // Track all objects for fallback
                $allPossibleMatches[] = [
                    'tag_data' => $tagData,
                    'object_key' => $objectKey,
                ];

                // Check if there's a CategoryObject pivot
                $categoryObject = \DB::table('category_litter_object')
                    ->where('category_id', $categoryId)
                    ->where('litter_object_id', $objectId)
                    ->first();

                if (!$categoryObject) {
                    continue;
                }

                // Check if this brand is attached to this category-object combination
                $hasPivot = \DB::table('taggables')
                    ->where('category_litter_object_id', $categoryObject->id)
                    ->where('taggable_type', BrandList::class)
                    ->where('taggable_id', $brandId)
                    ->exists();

                if ($hasPivot) {
                    $pivotMatches[] = [
                        'tag_data' => $tagData,
                        'object_key' => $objectKey,
                    ];
                }
            }

            // If we have pivot matches, use the first one
            if (!empty($pivotMatches)) {
                $chosen = $pivotMatches[0];

                Log::info("✓ PIVOT MATCH - Brand attached via database relationship", [
                    'photo_id' => $photo->id,
                    'brand' => $brandKey,
                    'object' => $chosen['object_key'],
                    'rule' => 'pivot_lookup'
                ]);

                $chosen['tag_data']['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                $attachedBrandIds[] = $brandId;
                $matched = true;
            }

            // RULE 2: If no pivot match, try quantity matching
            if (!$matched) {
                $quantityMatches = [];

                foreach ($createdPhotoTags as $tagData) {
                    if ($tagData['object']['quantity'] === $brandQty) {
                        $quantityMatches[] = [
                            'tag_data' => $tagData,
                            'object_key' => $tagData['object']['key'] ?? 'unknown',
                        ];
                    }
                }

                if (count($quantityMatches) === 1) {
                    // Unique quantity match
                    $match = $quantityMatches[0];
                    Log::info("✓ QUANTITY MATCH - Brand attached via unique quantity", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'object' => $match['object_key'],
                        'qty' => $brandQty,
                        'rule' => 'unique_quantity'
                    ]);

                    $match['tag_data']['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                    $attachedBrandIds[] = $brandId;
                    $matched = true;
                }
            }

            // RULE 3: If still no match, use priority-based fallback
            if (!$matched && !empty($allPossibleMatches)) {
                $chosen = $this->chooseBestByPriority($allPossibleMatches);

                if ($chosen) {
                    Log::info("✓ PRIORITY FALLBACK - Brand attached to highest priority object", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'object' => $chosen['object_key'],
                        'rule' => 'priority_fallback'
                    ]);

                    $chosen['tag_data']['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                    $attachedBrandIds[] = $brandId;
                    $matched = true;

                    // Create pivot for future use
                    $this->createPivotIfMissing(
                        $chosen['tag_data']['category_id'],
                        $chosen['tag_data']['object']['id'],
                        $brandId
                    );
                }
            }

            // If STILL no match (shouldn't happen with fallback), log warning
            if (!$matched) {
                Log::warning("Brand could not be matched even with fallback", [
                    'photo_id' => $photo->id,
                    'brand' => $brandKey,
                    'reason' => 'no_objects_available'
                ]);
            }
        }
    }

    /**
     * Choose the best object match based on priority order
     */
    private function chooseBestByPriority(array $matches): ?array
    {
        if (empty($matches)) {
            return null;
        }

        // Sort matches by priority (lower number = higher priority)
        usort($matches, function($a, $b) {
            $aPriority = self::OBJECT_PRIORITY[$a['object_key']] ?? 999;
            $bPriority = self::OBJECT_PRIORITY[$b['object_key']] ?? 999;
            return $aPriority <=> $bPriority;
        });

        // Return the highest priority match
        return $matches[0];
    }

    private function createPivotIfMissing(int $categoryId, int $objectId, int $brandId): void
    {
        // Get or create CategoryObject
        $categoryObject = \DB::table('category_litter_object')
            ->where('category_id', $categoryId)
            ->where('litter_object_id', $objectId)
            ->first();

        if (!$categoryObject) {
            $categoryObjectId = \DB::table('category_litter_object')->insertGetId([
                'category_id' => $categoryId,
                'litter_object_id' => $objectId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $categoryObjectId = $categoryObject->id;
        }

        // Create taggable relationship if it doesn't exist
        $exists = \DB::table('taggables')
            ->where('category_litter_object_id', $categoryObjectId)
            ->where('taggable_type', BrandList::class)
            ->where('taggable_id', $brandId)
            ->exists();

        if (!$exists) {
            \DB::table('taggables')->insert([
                'category_litter_object_id' => $categoryObjectId,
                'taggable_type' => BrandList::class,
                'taggable_id' => $brandId,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("Created pivot relationship for future use", [
                'category_object_id' => $categoryObjectId,
                'brand_id' => $brandId
            ]);
        }
    }

    private function createBrandsOnlyTag(Photo $photo, array $globalBrands): void
    {
        Log::info("Creating brands-only tag", [
            'photo_id' => $photo->id,
            'brands' => array_map(fn($b) => ['key' => $b['key'] ?? 'unknown', 'qty' => $b['quantity']], $globalBrands)
        ]);

        $photoTag = PhotoTag::create([
            'photo_id'    => $photo->id,
            'category_id' => Category::where('key', 'brands')->value('id'),
            'quantity'    => array_sum(array_column($globalBrands, 'quantity')),
            'picked_up'   => ! $photo->remaining,
        ]);
        $photoTag->attachExtraTags($globalBrands, 'brand', 0);
    }

    private function createCustomOnlyTag(Photo $photo, array $customTags): void
    {
        $primary = array_shift($customTags);
        $photoTag = PhotoTag::create([
            'photo_id'              => $photo->id,
            'custom_tag_primary_id' => $primary['id'],
            'quantity'              => $primary['quantity'],
            'picked_up'             => ! $photo->remaining,
        ]);
        foreach ($customTags as $idx => $extra) {
            $photoTag->attachExtraTags([$extra], 'custom_tag', $idx);
        }
    }

    private function attachCustomTagsToLast(Photo $photo, array $customTags): void
    {
        $last = $photo->photoTags()->latest()->first();
        if (! $last) {
            return;
        }
        foreach ($customTags as $idx => $extra) {
            $last->attachExtraTags([$extra], 'custom_tag', $idx);
        }
    }
}
