<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use Illuminate\Support\Facades\Log;
use App\Models\Litter\Tags\CategoryLitterObject;

class UpdateTagsService
{
    protected ClassifyTagsService $classifyTags;
    protected PhotoTagService $photoTagService;

    public function __construct(
        ClassifyTagsService $classifyTagsService,
        PhotoTagService $photoTagService
    ) {
        $this->classifyTags = $classifyTagsService;
        $this->photoTagService = $photoTagService;
    }

    /**
     * Main entry point.
     *
     * @param Photo $photo
     */
    public function updateTags(Photo $photo): void
    {
        /**
         * Example $photo->tags:
         * [
         *   "smoking" => ["butts" => 1],
         *   "alcohol" => ["beer_bottles" => 1, "bottle_tops" => 2],
         *   "brands" => ["heineken" => 1, "marlboro" => 1],
         * ]
         */

        if (!is_array($photo->tags) || empty($photo->tags)) {
            return;
        }

        // Step 1: Parse all tags into arrays of objects, sub-objects, brands, materials—keyed by category.
        // We also store each “object brand relationship” in memory for later matching.
        $parsedData = $this->parsePhotoTags($photo->tags);

        // Step 2: Using $parsedData, populate the pivot (category_litter_object) and the taggables table.
        // This is the new structure that must exist before we create photo_tags + extras.
        $this->populatePivotAndTaggables($photo, $parsedData);

        // Step 3: After the pivot + taggables are in place, create or update the legacy
        //         photo_tags + photo_tag_extras so the old system still works.
        //         This can rely on the same $parsedData, but can also read from pivot/taggables if you like.
        $this->createLegacyPhotoTagData($photo, $parsedData);
    }

    /**
     * -------------------------------------------------------------------------------------
     * STEP 1: Parse Photo->tags into structured data for each category
     * -------------------------------------------------------------------------------------
     *
     * Each item in $photo->tags is something like:
     *   $photo->tags[$categoryKey][$tag => quantity]
     * We classify the tag => object, brand, material, sub-object, etc.
     *
     * Return structure example:
     * [
     *   $categoryKey => [
     *      'category_id' => X (nullable),
     *      'objects'     => [ [ 'id' => X, 'key' => 'beer_bottles', 'quantity' => 1 ], ... ],
     *      'sub-objects'  => [ ... ],
     *      'brands'      => [ ... ],
     *      'materials'   => [ ... ],
     *   ],
     *   ...
     * ]
     */
    protected function parsePhotoTags(array $tagsByCategory): array
    {
        $parsed = [];

        foreach ($tagsByCategory as $categoryKey => $tagItems) {
            // 1) Resolve category via classification
            $categoryModel = $this->classifyTags->getCategory($categoryKey);
            if (!$categoryModel) {
                Log::warning("Missing category for key: {$categoryKey}");
                continue;
            }

            // Prepare arrays to hold recognized items
            $objects    = [];
            $subObjects = [];
            $brands     = [];
            $materials  = [];

            foreach ($tagItems as $tag => $quantity)
            {
                // Determine if tag is a brand, object, material, etc.
                $result = $this->classifyTags->classify($tag);
                if ($result['type'] === 'undefined') {
                    Log::warning("Unclassified tag: {$tag}");
                    continue;
                }

                $info = [
                    'id'    => $result['id'],
                    'key'   => $tag,
                    'quantity' => (int) $quantity ?: 1,
                ];

                switch ($result['type']) {
                    case 'object':
                        if ($this->checkIfSubObject($tag)) {
                            $subObjects[] = $info;
                        } else {
                            $objects[] = $info;
                        }
                        break;

                    case 'brand':
                        $brands[] = $info;
                        break;

                    case 'material':
                        $materials[] = $info;
                        break;

                    case 'custom':
                        // skip or handle separately
                        break;
                }
            }

            $parsed[$categoryKey] = [
                'category_id' => $categoryModel->id,
                'objects'     => $objects,
                'subObjects'  => $subObjects,
                'brands'      => $brands,
                'materials'   => $materials,
            ];
        }

        return $parsed;
    }

    /**
     * Decide if a tag is likely a “child” object, e.g. “bottle_tops” or “bottle_lids.”
     */
    protected function checkIfSubObject(string $tagKey): bool
    {
        // Simple approach
        // return preg_match('/(top|lid|cap|label)s?$/i', $tagKey);

        // Example:
        // "bottleLid" => bottle
        // "bottleLabel" => bottle
        // "straw" => "juice_pouch", "juice_carton"

        return false;
    }

    /**
     * -------------------------------------------------------------------------------------
     * STEP 2: Create or update the pivot (category_litter_object) + taggables
     * -------------------------------------------------------------------------------------
     *
     * For each category => multiple objects, we:
     *   - Create or find the pivot row (category_litter_object).
     *   - Attach brand(s) and material(s) with `syncWithoutDetaching`.
     *   - If sub-objects are recognized, create separate pivot records for those as well,
     *     or attach them differently—depending on your domain rules.
     *
     * By populating these first, we can reference them later when building PhotoTag(Extra).
     */
    protected function populatePivotAndTaggables(Photo $photo, array $parsedData): void
    {
        foreach ($parsedData as $categoryKey => $group) {
            $categoryId = $group['category_id'];
            $objects    = $group['objects'];
            $subObjects = $group['subObjects'];
            $brands     = $group['brands'];
            $materials  = $group['materials'];

            // No objects => no pivot
            if (empty($objects) && empty($subObjects)) {
                continue;
            }

            // 1) For each main object, create pivot + attach brand/material
            foreach ($objects as $obj) {
                $pivot = CategoryLitterObject::firstOrCreate([
                    'category_id'      => $categoryId,
                    'litter_object_id' => $obj['id'],
                ]);
                // If you want an “object count” on the pivot, do so:
                // $pivot->update([ 'count' => $obj['count'] ]);

                // Attach brand(s)
                $matchedBrands = $this->matchBrandsToObject($obj, $brands);
                if (!empty($matchedBrands)) {
                    $brandAttachData = [];
                    foreach ($matchedBrands as $brand) {
                        // e.g. [brandId => ['count' => brandCount]]
                        $brandAttachData[$brand['id']] = ['count' => $brand['count']];
                    }
                    $pivot->brands()->syncWithoutDetaching($brandAttachData);
                }

                // Attach materials
                $matchedMats = $this->matchMaterialsToObject($obj, $materials);
                if (!empty($matchedMats)) {
                    $materialAttachData = [];
                    foreach ($matchedMats as $mat) {
                        $materialAttachData[$mat['id']] = ['count' => $mat['count']];
                    }
                    $pivot->materials()->syncWithoutDetaching($materialAttachData);
                }
            }

            // 2) If sub-objects exist, decide how to store them. Usually you’d do the same:
            foreach ($subObjects as $sub) {
                $pivot = CategoryLitterObject::firstOrCreate([
                    'category_id'      => $categoryId,
                    'litter_object_id' => $sub['id'],
                ]);
                // $pivot->update([ 'count' => $sub['count'] ]);

                // Possibly attach brand/material to sub-object as well – depends on your logic
                $pivot->brands()->syncWithoutDetaching([]);
                $pivot->materials()->syncWithoutDetaching([]);
            }
        }
    }

    /**
     * Example: match brand to the given object. If there's exactly one brand and exactly one object, we pair them.
     * Otherwise, skip or do more advanced logic. This is where your “mapping between objects, brands, sub-objects”
     * might come in. E.g., you might have a table that says “Heineken brand => beer_bottles object.”
     */
    protected function matchBrandsToObject(array $object, array $brands): array
    {
        // If you have a known brand->object mapping, you might do:
        //   if ($this->brandObjectMap[$brand['key']] === $object['key']) { ... }
        // For simplicity, we do a single brand if counts are both 1.
        if (count($brands) === 1 && count($object) !== 0) {
            return $brands; // attach the single brand
        }
        // More complex logic can go here
        return [];
    }

    protected function matchMaterialsToObject(array $object, array $materials): array
    {
        // Similarly for materials
        if (count($materials) === 1) {
            return $materials;
        }
        return [];
    }

    /**
     * -------------------------------------------------------------------------------------
     * STEP 3: Create or update photo_tags / photo_tag_extras
     * -------------------------------------------------------------------------------------
     *
     * We do this last because sometimes the brand->object or sub-object->object relationships
     * are clearer after we know how many pivot records exist, etc. But for efficiency,
     * we can also do it in parallel if you prefer.
     */
    protected function createLegacyPhotoTagData(Photo $photo, array $parsedData): void
    {
        foreach ($parsedData as $categoryKey => $group) {
            $categoryId = $group['category_id'];
            $objects    = $group['objects'];
            $subObjects = $group['sub-objects'];
            $brands     = $group['brands'];
            $materials  = $group['materials'];

            // If no objects found, you might skip or create fallback
            if (empty($objects) && empty($subObjects)) {
                continue;
            }

            // For each main object, create a photo_tag
            foreach ($objects as $idx => $obj) {
                $photoTag = PhotoTag::create([
                    'photo_id'         => $photo->id,
                    'category_id'      => $categoryId,
                    'litter_object_id' => $obj['id'],
                    'quantity'         => $obj['count'],
                    'picked_up'        => !$photo->remaining,
                ]);

                // photo_tag_extras: sub-objects, brand, material, etc.
                // Subobjects
                foreach ($subObjects as $subIdx => $sub) {
                    if ($this->isSubobjectOf($sub['key'], $obj['key'])) {
                        PhotoTagExtraTags::create([
                            'photo_tag_id' => $photoTag->id,
                            'tag_type'     => 'object',
                            'tag_type_id'  => $sub['id'],
                            'quantity'     => $sub['count'],
                            'index'        => $subIdx,
                        ]);
                    }
                }

                // Brand
                $matchedBrands = $this->matchBrandsToObject($obj, $brands);
                foreach ($matchedBrands as $bIdx => $brand) {
                    PhotoTagExtraTags::create([
                        'photo_tag_id' => $photoTag->id,
                        'tag_type'     => 'brand',
                        'tag_type_id'  => $brand['id'],
                        'quantity'     => $brand['count'],
                        'index'        => $bIdx,
                    ]);
                }

                // Material
                $matchedMats = $this->matchMaterialsToObject($obj, $materials);
                foreach ($matchedMats as $mIdx => $mat) {
                    PhotoTagExtraTags::create([
                        'photo_tag_id' => $photoTag->id,
                        'tag_type'     => 'material',
                        'tag_type_id'  => $mat['id'],
                        'quantity'     => $mat['count'],
                        'index'        => $mIdx,
                    ]);
                }
            }

            // If you want separate photo_tags for sub-objects as well, you can do that here
            // (some designs only store sub-objects in photo_tag_extras, but it's your call).
        }
    }

    /**
     * If sub-object is child of object? Some simple logic or a known data map.
     */
    protected function isSubobjectOf(string $subKey, string $objectKey): bool
    {
        // e.g. “bottle_tops” => sub-object of “beer_bottles”
        return (
            str_contains($subKey, 'top') &&
            str_contains($objectKey, 'bottle')
        );
    }
}
