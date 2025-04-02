<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Litter\Tags\CategoryLitterObject;
use Illuminate\Support\Facades\Log;

class UpdateTagsService
{
    protected ClassifyTagsService $classifyTags;

    public function __construct(ClassifyTagsService $classifyTags)
    {
        $this->classifyTags = $classifyTags;
    }

    /**
     * Main method to handle the migration or update of tags for a photo.
     *
     *  1) Parse $photo->tags (old or new) and classify them (objects, materials, brands, etc.).
     *  2) Populate pivot + morph data (category_litter_object + taggables).
     *  3) Create legacy photo_tags + photo_tag_extras.
     */
    public function updateTags(Photo $photo): void
    {
        // If no tags exist, bail out
        if (!is_array($photo->tags) || empty($photo->tags)) {
            return;
        }

        // Step 1: Parse all tags by category key
        //         E.g. $photo->tags = ["smoking" => ["butts" => 1], "alcohol" => ["beerBottle" => 1]]
        $parsedData = $this->parsePhotoTags($photo->tags);

        // Step 2: Populate new pivot + morph structure
        $this->populatePivotAndTaggables($parsedData);

        // Step 3: Create legacy photo_tags + photo_tag_extras
        $this->createLegacyPhotoTagData($photo, $parsedData);
    }

    /**
     * ---------------------------------------------------------------------
     * STEP 1: Parse & classify each tag, gather them in a structured format
     * ---------------------------------------------------------------------
     *
     * Returns an array like:
     * [
     *   'alcohol' => [
     *       'category_id' => 2,
     *       'objects'     => [
     *           ['key' => 'beer_bottle', 'id' => 99, 'count' => 1, 'extra_materials' => [23, 24], ...],
     *       ],
     *       'brands'      => [],
     *       'materials'   => [],
     *   ],
     *   ...
     * ]
     */
    protected function parsePhotoTags(array $tagsByCategory): array
    {
        $result = [];

        foreach ($tagsByCategory as $categoryKey => $tagList) {
            // Find the Category row in the DB (if it exists)
            $categoryModel = $this->classifyTags->getCategory($categoryKey);
            if (!$categoryModel) {
                Log::warning("No matching Category for key: {$categoryKey}");
                continue; // skip if unknown category
            }

            // We’ll gather objects, brands, materials from each tag
            $objects   = [];
            $brands    = [];
            $materials = [];

            foreach ($tagList as $tag => $qty) {
                $qty = (int) $qty ?: 1;
                // Use the classify() method that now handles old→new transformation
                $classified = $this->classifyTags->classify($tag);

                if ($classified['type'] === 'undefined') {
                    Log::warning("Undefined tag: {$tag}");
                    continue;
                }

                switch ($classified['type']) {
                    case 'object':
                        $objData = [
                            'key'   => $classified['key'],  // e.g. 'beer_bottle'
                            'id'    => $classified['id'],   // LitterObject ID
                            'count' => $qty,
                        ];

                        // If the classifier returned extra materials or brands,
                        // we can look them up & attach as needed
                        $extraMaterialIds = $this->fetchMaterialIds($classified['materials'] ?? []);
                        if (!empty($extraMaterialIds)) {
                            $objData['extra_material_ids'] = $extraMaterialIds;
                        }

                        $objects[] = $objData;
                        break;

                    case 'brand':
                        $brands[] = [
                            'key'   => $classified['key'], // brand key
                            'id'    => $classified['id'],  // brand ID
                            'count' => $qty,
                        ];
                        break;

                    case 'material':
                        $materials[] = [
                            'key'   => $classified['key'],
                            'id'    => $classified['id'],
                            'count' => $qty,
                        ];
                        break;

                    // If a category is found, you might ignore or handle differently
                    case 'category':
                        // Possibly skip? Usually we don't expect a category as a "tag"
                        Log::info("Encountered 'category' as a tag: {$classified['key']}");
                        break;

                    case 'custom':
                        // Do custom logic or skip
                        Log::debug("Custom tag encountered: {$classified['key']}");
                        break;
                }
            }

            // Store in final array
            $result[$categoryKey] = [
                'category_id' => $categoryModel->id,
                'objects'     => $objects,
                'brands'      => $brands,
                'materials'   => $materials,
            ];
        }

        return $result;
    }

    /**
     * Utility to convert an array of material keys into their DB IDs if they exist.
     * e.g. $materials = ['paper', 'cardboard'] => [11, 12].
     * This uses classifyNewKey() or direct lookup of $this->classifyTags->materials to do so.
     */
    protected function fetchMaterialIds(array $materialKeys): array
    {
        $ids = [];
        foreach ($materialKeys as $mKey) {
            // We can reuse classifyNewKey or do direct array checks
            $classRes = $this->classifyTags->classifyNewKey($mKey);

            if ($classRes['type'] === 'material') {
                $ids[] = $classRes['id'];
            } else {
                Log::warning("Extra material not found: {$mKey}");
            }
        }
        return $ids;
    }

    /**
     * ---------------------------------------------------------------------
     * STEP 2: Create or update the new pivot + morph structure
     * ---------------------------------------------------------------------
     *
     * For each category => multiple objects, we:
     *   - Create/find CategoryLitterObject pivot row
     *   - Attach brand(s) or material(s) in `taggables` (via ->brands()->sync, etc.)
     *   - If an object has "extra_material_ids," attach them as well
     */
    protected function populatePivotAndTaggables(array $parsedData): void
    {
        foreach ($parsedData as $categoryKey => $group) {
            $categoryId = $group['category_id'];
            $objects    = $group['objects'];
            $brands     = $group['brands'];
            $materials  = $group['materials'];

            // 1) If no objects, skip
            if (empty($objects)) {
                // You might still have brand or material alone. Decide how to handle that.
                continue;
            }

            // 2) For each object, create pivot row, attach brand(s)/material(s)
            foreach ($objects as $obj) {
                $pivot = CategoryLitterObject::firstOrCreate([
                    'category_id'      => $categoryId,
                    'litter_object_id' => $obj['id'],
                ]);

                // If pivot has a 'count' column, you can store $obj['count'] too:
                // $pivot->update(['count' => $obj['count']]);

                // (A) Attach brand(s)
                // This is naive logic: if we have exactly one brand, attach it.
                // If we have multiple, you might skip or attach all.
                if (count($brands) === 1) {
                    $brand = $brands[0];
                    $pivot->brands()->syncWithoutDetaching([
                        $brand['id'] => ['count' => $brand['count']],
                    ]);
                } elseif (count($brands) > 1) {
                    Log::info("Multiple brands for single object => skipping brand linkage. object={$obj['key']}");
                }

                // (B) Attach material(s) from the top-level + any extras from the object
                // Top-level materials
                $attachMaterialData = [];
                foreach ($materials as $mat) {
                    $attachMaterialData[$mat['id']] = ['count' => $mat['count']];
                }
                // Extra materials specific to this object (from old->new transform)
                if (!empty($obj['extra_material_ids'] ?? [])) {
                    foreach ($obj['extra_material_ids'] as $mId) {
                        // If it's already in $attachMaterialData, we might increment the count.
                        if (isset($attachMaterialData[$mId])) {
                            $attachMaterialData[$mId]['count'] += $obj['count'];
                        } else {
                            $attachMaterialData[$mId] = ['count' => $obj['count']];
                        }
                    }
                }

                if (!empty($attachMaterialData)) {
                    $pivot->materials()->syncWithoutDetaching($attachMaterialData);
                }
            }
        }
    }

    /**
     * ---------------------------------------------------------------------
     * STEP 3: Create or update legacy photo_tags + photo_tag_extras
     * ---------------------------------------------------------------------
     *
     * We do this after we have the new pivot structure. In some cases,
     * you might not need the old tables anymore, but if you do, here’s how:
     */
    protected function createLegacyPhotoTagData(Photo $photo, array $parsedData): void
    {
        foreach ($parsedData as $categoryKey => $group) {
            $categoryId = $group['category_id'];

            // We only create photo_tags for “objects.” If no objects, we skip.
            if (empty($group['objects'])) {
                continue;
            }

            foreach ($group['objects'] as $objIndex => $obj) {
                // A) Create the main photo_tag for this object
                $photoTag = PhotoTag::create([
                    'photo_id'         => $photo->id,
                    'category_id'      => $categoryId,
                    'litter_object_id' => $obj['id'],
                    'quantity'         => $obj['count'],
                    'picked_up'        => !$photo->remaining,
                ]);

                // B) For brand(s), we can create PhotoTagExtraTags with tag_type='brand'
                //    Example naive logic: if exactly one brand => link it
                //    If multiple brands => skip or attach all
                $brands = $group['brands'] ?? [];
                if (count($brands) === 1) {
                    $brand = $brands[0];
                    PhotoTagExtraTags::create([
                        'photo_tag_id' => $photoTag->id,
                        'tag_type'     => 'brand',
                        'tag_type_id'  => $brand['id'],
                        'quantity'     => $brand['count'],
                        'index'        => $objIndex,
                    ]);
                }

                // C) For material(s), also store them as extras
                //    Combine top-level materials + any extra from object
                $materials = $group['materials'] ?? [];
                $attachAllMaterialIds = [];
                foreach ($materials as $mat) {
                    // We'll store them in array to create multiple PhotoTagExtraTags
                    $attachAllMaterialIds[] = [
                        'id'    => $mat['id'],
                        'count' => $mat['count'],
                    ];
                }
                // Add object-level extra materials
                if (!empty($obj['extra_material_ids'] ?? [])) {
                    foreach ($obj['extra_material_ids'] as $mid) {
                        $attachAllMaterialIds[] = [
                            'id'    => $mid,
                            'count' => $obj['count'],
                        ];
                    }
                }

                // Now create PhotoTagExtraTags for each
                foreach ($attachAllMaterialIds as $mData) {
                    PhotoTagExtraTags::create([
                        'photo_tag_id' => $photoTag->id,
                        'tag_type'     => 'material',
                        'tag_type_id'  => $mData['id'],
                        'quantity'     => $mData['count'],
                        'index'        => $objIndex,
                    ]);
                }
            }
        }
    }
}
