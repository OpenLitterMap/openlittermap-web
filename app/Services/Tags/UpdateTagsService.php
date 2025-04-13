<?php

namespace App\Services\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\CategoryObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UpdateTagsService
{
    protected ClassifyTagsService $classifyTags;

    public function __construct(ClassifyTagsService $classifyTags)
    {
        $this->classifyTags = $classifyTags;
    }

    /**
     * Main method to handle the migration.
     *
     *  1) Parse deprecating $photo->tags into new format (objects, materials, brands, etc.).
     *  2) Populate pivot + morph data (category_litter_object + taggables).
     *  3) Create new photo_tags + photo_tag_extras.
     */
    public function updateTags(Photo $photo): void
    {
        // [["smoking" => ["butts" => 1], "alcohol" => ["beerBottle" => 1], "brands" => ["pepsi" => 1, "marlboro" => 1]]
        $originalTags = $photo->tags() ?? [];

        // 1) Merge single brand into single object if that pattern applies
        $originalTags = $this->mergeSingleObjectAndBrand($originalTags);

        // ['tag1', 'brand=x', 'object:thing=2', 'material:plastic']
        $customTagsOld = $photo->customTags ?? [];

        if (empty($originalTags) && empty($customTagsOld)) {
            Log::info("No tags to migrate for photo ID: {$photo->id}");
            return;
        }

        // Step 1: Parse all the photos tags into the new format.
        // We don't know the relationship between them yet
        $parsedTags = $this->parseTags($originalTags, $customTagsOld);

        // Step 2: Populate new pivot + morph structure
        // This will create new relationships between the tags
        // eg softdrinks.energy_can => redbull, monster
        // eg alcohol.beer_can => budweiser, heineken
        $this->createTaggableRelationships($parsedTags);

        // Step 3: Create legacy photo_tags + photo_tag_extras
        $this->createPhotoTags($photo, $parsedTags);

        // Step 4: Compute metadata
        $photo->calculateTotalTags();
    }

    /**
     * If $originalTags has exactly two top-level keys, e.g.:
     *     [ 'alcohol' => ['beerBottle'=>1], 'brands'=>['heineken'=>1] ]
     * and both those sub-arrays each have exactly 1 entry,
     * merge them into a single category array. This lets the code
     * interpret them as "1 object => 1 brand" in the same category block.
     *
     * Otherwise, do nothing and return the original array unchanged.
     */
    private function mergeSingleObjectAndBrand(array $originalTags): array
    {
        $keys = array_keys($originalTags);

        // If we have exactly 2 keys and one is 'brands'
        if (count($keys) === 2 && in_array('brands', $keys, true)) {
            // Copy out the brand sub-array
            $brandData = $originalTags['brands'];
            unset($originalTags['brands']);

            // Now there's 1 other key
            $remainingKeys = array_keys($originalTags);
            $onlyKey = $remainingKeys[0] ?? null;
            if ($onlyKey === null) {
                // Shouldn't happen unless originalTags was weirdly empty
                $originalTags['brands'] = $brandData; // revert
                return $originalTags;
            }

            // Check the sub-arrays
            $objectCount = count($originalTags[$onlyKey]);
            $brandCount  = count($brandData);

            // If both sub-arrays each have exactly 1 entry => merge them
            if ($objectCount === 1 && $brandCount === 1) {
                \Log::info("Merging single brand into single object category '{$onlyKey}'.");
                $originalTags[$onlyKey] = array_merge($originalTags[$onlyKey], $brandData);
            } else {
                \Log::info("Multiple objects or multiple brands exist; skipping direct merge.");
                // restore 'brands' so parseTags sees them separately
                $originalTags['brands'] = $brandData;
            }
        }

        return $originalTags;
    }

    protected function parseTags(array $originalTags, Collection $customTagsOld): array
    {
        $result = [];

        foreach ($originalTags as $categoryKey => $items)
        {
            $category = $this->classifyTags->getCategory($categoryKey);

            if (!$category) {
                Log::warning("No matching Category for key: {$categoryKey}");
                continue;
            }

            $result[$categoryKey] = [
                'category_id' => $category->id,
                'objects'     => [],
                'brands'      => [],
                'materials'   => [],
                'customTags'  => [],
            ];

            foreach ($items as $tag => $quantity) {
                $parsed = $this->classifyTags->classify($tag);
                $parsed['quantity'] = (int) $quantity ?: 1;

                match ($parsed['type']) {
                    'object' => $result[$categoryKey]['objects'][] = $parsed,
                    'brand' => $result[$categoryKey]['brands'][] = $parsed,
                    'material' => $result[$categoryKey]['materials'][] = $parsed,
                    'custom' => $result[$categoryKey]['customTags'][] = $parsed,
                    'undefined' => Log::warning("Undefined tag type: {$parsed['type']} for tag: {$tag}"),
                    default => Log::info("Unhandled tag type: {$parsed['type']} for tag: {$tag}")
                };
            }
        }

        if (!empty($customTagsOld)) {
            foreach ($customTagsOld as $customTagOld) {
                $parsed = $this->classifyTags->normalizeCustomTag($customTagOld->tag);

                $result['custom_tags'][] = [
                    'key'   => $parsed['key'],
                    'id'    => $parsed['id'],
                    'quantity' => $parsed['quantity'] ?? 1,
                    'category_key' => $parsed['category_key'] ?? null,
                ];
            }
        }

        return $result;
    }

    protected function createTaggableRelationships(array $parsed): void
    {
        foreach ($parsed as $groupKey => $group)
        {
            if (empty($group['objects'])) {
                continue;
            }

            foreach ($group['objects'] as $object)
            {
                $catObj = CategoryObject::firstOrCreate([
                    'category_id' => $group['category_id'],
                    'litter_object_id' => $object['id'],
                ]);

                $catObj->attachTaggables($group['brands'], BrandList::class);
                $catObj->attachTaggables($group['materials'], Materials::class);
                $catObj->attachTaggables($group['customTags'], CustomTagNew::class);
            }
        }
    }

    protected function createPhotoTags(Photo $photo, array $parsed): void
    {
        $hasObjects = false;

        foreach ($parsed as $groupKey => $group)
        {
            if (!empty($group['objects']))
            {
                $hasObjects = true;
                $brandLinks = $this->classifyTags->resolveBrandObjectLinks($photo->id, $group);

                foreach ($group['objects'] as $index => $object)
                {
                    $photoTag = $photo->createTag([
                        'category_id' => $group['category_id'],
                        'litter_object_id' => $object['id'],
                        'quantity' => $object['quantity'],
                        'picked_up' => !$photo->remaining,
                    ]);

                    $matchedBrands = collect($brandLinks)
                        ->where('object.id', $object['id'])
                        ->pluck('brand')
                        ->unique('id')
                        ->values()
                        ->all();

                    $matchedMaterials = [];

                    if (!empty($object['materials'])) {
                        // Grab from DB each material whose 'key' is in $object['materials']
                        $matchedMaterials = Materials::whereIn('key', $object['materials'])
                            ->get()
                            ->map(function ($mat) {
                                return [
                                    'id'       => $mat->id,
                                    'key'      => $mat->key,
                                    'quantity' => 1,  // or some other quantity logic
                                ];
                            })
                            ->all();
                    }

                    $photoTag->attachExtraTags($matchedBrands, 'brand', $index);
                    $photoTag->attachExtraTags($matchedMaterials, 'material', $index);
                    $photoTag->attachExtraTags($group['customTags'], 'custom_tag', $index);
                }
            }
        }

        // If no objects exist, create one PhotoTag using the first custom tag as primary
        if (!$hasObjects && !empty($parsed['custom_tags'] ?? []))
        {
            $customTags = $parsed['custom_tags'];
            $primary = array_shift($customTags);

            // Create the primary PhotoTag
            $photoTag = PhotoTag::create([
                'photo_id' => $photo->id,
                'custom_tag_primary_id' => $primary['id'],
                'quantity' => $primary['quantity'],
                'picked_up' => !$photo->remaining,
            ]);

            // Attach any additional custom tags as extras
            foreach ($customTags as $index => $customExtra) {
                $photoTag->attachExtraTags([$customExtra], 'custom_tag', $index);
            }
        }
        elseif ($hasObjects && !empty($parsed['custom_tags'] ?? []))
        {
            $lastPhotoTag = PhotoTag::where('photo_id', $photo->id)->latest()->first();

            foreach ($parsed['custom_tags'] as $index => $custom)
            {
                $lastPhotoTag?->attachExtraTags([$custom], 'custom_tag', $index);
            }
        }
    }
}
