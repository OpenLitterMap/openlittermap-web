<?php

namespace App\Services\Tags;

use App\Enums\Dimension;
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
        $globalMaterials    = [];
        $topLevelCustomTags = [];

        // 1) Category-based blocks
        foreach ($originalTags as $categoryKey => $items) {
            // v4 "material" category → standalone material extra tags in v5
            if ($categoryKey === 'material') {
                foreach ($items as $tag => $qtyRaw) {
                    $qty = (int) $qtyRaw;
                    if ($qty <= 0) {
                        continue;
                    }

                    $materialId = TagKeyCache::idFor(Dimension::MATERIAL->value, $tag);
                    if ($materialId) {
                        $globalMaterials[] = [
                            'id'       => $materialId,
                            'key'      => $tag,
                            'type'     => Dimension::MATERIAL->value,
                            'quantity' => $qty,
                        ];
                    } else {
                        Log::warning("Material not found in materials table during migration", [
                            'material_key' => $tag,
                            'photo_id'     => $photoId,
                        ]);
                    }
                }
                continue;
            }

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
                            'quantity' => $qty,
                        ];
                    } else {
                        // Still add it but without ID (will become brands-only tag)
                        $globalBrands[] = [
                            'id' => null,
                            'key' => $tag,
                            'type' => Dimension::BRAND->value,
                            'quantity' => $qty,
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
            'groups'          => $groups,
            'globalBrands'    => $globalBrands,
            'globalMaterials' => $globalMaterials,
            'customTags'      => $topLevelCustomTags,
        ];
    }

    public function createPhotoTags(Photo $photo, array $parsedTags): void
    {
        $groups             = $parsedTags['groups'] ?? [];
        $globalBrands       = $parsedTags['globalBrands'] ?? [];
        $globalMaterials    = $parsedTags['globalMaterials'] ?? [];
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
                        'photo_tag'    => $photoTag,
                        'category_id'  => $categoryId,
                        'category_key' => $categoryKey,
                        'object_key'   => $object['key'] ?? 'unknown',
                        'object'       => $object,
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

        // Attach brands to objects or create standalone
        if (!empty($globalBrands)) {
            if ($hasObjects) {
                $this->attachBrandsToObjects($photo, $globalBrands, $createdPhotoTags);
            } else {
                $this->createBrandsOnlyTag($photo, $globalBrands);
            }
        }

        // Attach global materials (from v4 "material" category) to last photo tag, or create standalone
        if (!empty($globalMaterials)) {
            $lastPhotoTag = $photo->photoTags()->latest()->first();
            if ($lastPhotoTag) {
                // Attach to existing tag (object, brands-only, or whatever was created)
                $lastPhotoTag->attachExtraTags($globalMaterials, Dimension::MATERIAL->value);
            } else {
                $this->createMaterialsOnlyTag($photo, $globalMaterials);
            }
        }

        // Handle custom tags
        if (!empty($topLevelCustomTags)) {
            $lastPhotoTag = $photo->photoTags()->latest()->first();
            if ($lastPhotoTag) {
                // Attach to existing tag (object, brands-only, materials-only)
                $this->attachCustomTagsToLast($photo, $topLevelCustomTags);
            } else {
                // No tags at all yet — create custom-only
                $this->createCustomOnlyTag($photo, $topLevelCustomTags);
            }
        }
    }

    /**
     * Attach a single brand to a single object PhotoTag.
     *
     * Only migrates brands when there is exactly 1 object and 1 brand.
     * All other combinations (multiple objects, multiple brands, multi-category) are skipped.
     */
    private function attachBrandsToObjects(Photo $photo, array $globalBrands, array $createdPhotoTags): void
    {
        // Only migrate when exactly 1 object and 1 brand
        if (count($createdPhotoTags) !== 1 || count($globalBrands) !== 1) {
            $brandKeys = collect($globalBrands)->pluck('key')->implode(',');
            $objectKeys = collect($createdPhotoTags)->pluck('object_key')->implode(',');
            Log::info("Brand skipped: photo={$photo->id} objects=" . count($createdPhotoTags) . " brands=" . count($globalBrands) . " brand_keys=[{$brandKeys}] object_keys=[{$objectKeys}]");
            return;
        }

        $lastPhotoTag = $createdPhotoTags[0]['photo_tag'];

        $lastPhotoTag->attachExtraTags($globalBrands, Dimension::BRAND->value);

        Log::info("Brand attached: photo={$photo->id} brand={$globalBrands[0]['key']}(qty={$globalBrands[0]['quantity']}) → object={$createdPhotoTags[0]['object_key']} category={$createdPhotoTags[0]['category_key']}");
    }

    /**
     * Create a brands-only PhotoTag for brands without object relationships
     */
    private function createBrandsOnlyTag(Photo $photo, array $brands): void
    {
        if (empty($brands)) {
            return;
        }

        $photoTag = PhotoTag::create([
            'photo_id'                  => $photo->id,
            'quantity'                  => array_sum(array_column($brands, 'quantity')),
            'picked_up'                 => !$photo->remaining,
        ]);

        $photoTag->attachExtraTags($brands, Dimension::BRAND->value);
    }

    /**
     * Create a materials-only PhotoTag for v4 "material" category items without object relationships
     */
    private function createMaterialsOnlyTag(Photo $photo, array $materials): void
    {
        if (empty($materials)) {
            return;
        }

        $photoTag = PhotoTag::create([
            'photo_id'  => $photo->id,
            'quantity'  => array_sum(array_column($materials, 'quantity')),
            'picked_up' => !$photo->remaining,
        ]);

        $photoTag->attachExtraTags($materials, Dimension::MATERIAL->value);
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
            'photo_id'                  => $photo->id,
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

    private function resolveCloId(?int $categoryId, ?int $objectId): ?int
    {
        if ($categoryId && $objectId) {
            return DB::table('category_litter_object')
                ->where('category_id', $categoryId)
                ->where('litter_object_id', $objectId)
                ->value('id');
        }

        return null;
    }
}
