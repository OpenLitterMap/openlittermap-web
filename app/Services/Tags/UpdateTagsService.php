<?php

namespace App\Services\Tags;

use App\Enums\Dimension;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Used in Migration Script and to convert old Mobile Tags to v5 format
 */
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

        DB::transaction(function () use ($parsedTags, $photo, $customTagsOld) {
            $this->createPhotoTags($photo, $parsedTags);
            $this->generatePhotoSummaryService->run($photo);
            $photo->update(['migrated_at' => now()]);
        });
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
//                        Log::warning("Brand not found in brandslist", [
//                            'brand_key' => $tag,
//                            'photo_id' => $photoId
//                        ]);
                        // Still add it but without ID (will become brands-only tag)
                        $globalBrands[] = [
                            'id' => null,
                            'key' => $tag,
                            'type' => Dimension::BRAND->value,
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
                if ($parsed['type'] === Dimension::BRAND->value) {
                    $globalBrands[] = $parsed;
                } elseif ($parsed['type'] === Dimension::MATERIAL->value) {
                    $groups[$categoryKey]['materials'][] = $parsed;
                } elseif ($parsed['type'] === 'object') {
                    // Check if this is a deprecated tag with material(s)
                    $mapping = ClassifyTagsService::normalizeDeprecatedTag($tag);
                    if ($mapping !== null) {
                        $parsed['object'] = $mapping['object'];
                        $parsed['materials'] = $mapping['materials'] ?? [];
                    }
                    $groups[$categoryKey]['objects'][] = $parsed;
                } elseif ($parsed['type'] === Dimension::CUSTOM_TAG->value) {
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
                    $cloId = $this->resolveCloId($categoryId, $object['id']);

                    $photoTag = PhotoTag::create([
                        'photo_id'                   => $photo->id,
                        'category_litter_object_id'  => $cloId,
                        'category_id'                => $categoryId,
                        'litter_object_id'           => $object['id'],
                        'quantity'                   => $object['quantity'],
                        'picked_up'                  => !$photo->remaining,
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
                            $materialId = TagKeyCache::idFor(Dimension::MATERIAL->value, $materialKey);
                            if ($materialId) {
                                $materialsToAttach[] = [
                                    'id'       => $materialId,
                                    'key'      => $materialKey,
                                    'quantity' => $object['quantity']
                                ];
                            }
                        }

                        if (!empty($materialsToAttach)) {
                            $photoTag->attachExtraTags($materialsToAttach, Dimension::MATERIAL->value);
                        }
                    }
                }
            }
        }

        // Skip brand attachment for now - will be handled in a separate process
        // Just log that we have brands to process later
        if (!empty($globalBrands)) {
            $brandKeys = array_map(fn($b) => $b['key'] ?? 'unknown', $globalBrands);
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
     * Create a brands-only PhotoTag for brands without object relationships
     */
    private function createBrandsOnlyTag(Photo $photo, array $brands): void
    {
        if (empty($brands)) {
            return;
        }

        // Extract brand keys for logging
        $brandKeys = array_map(fn($b) => $b['key'] ?? 'unknown', $brands);

        Log::info("Creating brands-only tag", [
            'photo_id' => $photo->id,
            'brands' => $brandKeys,
            'count' => count($brands)
        ]);

        $unclassifiedOtherCloId = $this->getUnclassifiedOtherCloId();

        $photoTag = PhotoTag::create([
            'photo_id'                  => $photo->id,
            'category_litter_object_id' => $unclassifiedOtherCloId,
            'category_id'               => Category::where('key', 'brands')->value('id'),
            'quantity'                  => array_sum(array_column($brands, 'quantity')),
            'picked_up'                 => !$photo->remaining,
        ]);

        $photoTag->attachExtraTags($brands, Dimension::BRAND->value);
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

        $unclassifiedOtherCloId = $this->getUnclassifiedOtherCloId();

        $photoTag = PhotoTag::create([
            'photo_id'                  => $photo->id,
            'category_litter_object_id' => $unclassifiedOtherCloId,
            'quantity'                  => $primary['quantity'] ?? 1,
            'picked_up'                 => !$photo->remaining,
        ]);

        // Attach primary custom tag as extra tag
        $photoTag->attachExtraTags([['id' => $primary['id']]], Dimension::CUSTOM_TAG->value);

        // Attach any additional custom tags as extra tags
        foreach ($customTags as $extra) {
            if (isset($extra['id']) && $extra['id']) {
                $photoTag->attachExtraTags([$extra], Dimension::CUSTOM_TAG->value);
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
            $last->attachExtraTags([$extra], Dimension::CUSTOM_TAG->value);
        }
    }

    private function resolveCloId(?int $categoryId, ?int $objectId): int
    {
        if ($categoryId && $objectId) {
            $cloId = DB::table('category_litter_object')
                ->where('category_id', $categoryId)
                ->where('litter_object_id', $objectId)
                ->value('id');

            if ($cloId) {
                return $cloId;
            }
        }

        return $this->getUnclassifiedOtherCloId();
    }

    private function getUnclassifiedOtherCloId(): int
    {
        $clo = CategoryObject::query()
            ->whereHas('category', fn($q) => $q->where('key', 'unclassified'))
            ->whereHas('litterObject', fn($q) => $q->where('key', 'other'))
            ->first();

        if (!$clo) {
            throw new \RuntimeException('Missing unclassified.other CLO record');
        }

        return $clo->id;
    }
}
