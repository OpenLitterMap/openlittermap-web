<?php

namespace App\Services\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Materials;
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
     *  2) Create PhotoTag and PhotoTagExtraTag rows.
     *  3) Re‑calculate the total_tags and compute the new summary per photo.
     */
    public function updateTags(Photo $photo): void
    {
        [$originalTags, $customTagsOld] = $this->getTags($photo);

        if (empty($originalTags) && empty($customTagsOld)) {
            Log::info("No tags to migrate for photo ID: {$photo->id}");
            return;
        }

        // Step 1: Parse all the photos tags into the new format.
        // We don't know the relationship between them yet
        $parsedTags = $this->parseTags($originalTags, $customTagsOld);

        // Step 2: Create legacy photo_tags + photo_tag_extras
        $this->createPhotoTags($photo, $parsedTags);

        // Step 3: Compute metadata
        $photo->calculateTotalTags();
    }

    protected function getTags(Photo $photo): array
    {
        // [["smoking" => ["butts" => 1], "alcohol" => ["beerBottle" => 1], "brands" => ["pepsi" => 1, "marlboro" => 1]]
        $tags = $photo->tags() ?? [];

        $originalTags = $this->mergeSingleObjectAndBrand($tags);

        // ['tag1', 'brand=x', 'object:thing=2', 'material:plastic']
        $customTagsOld = $photo->customTags ?? [];

        return [$originalTags, $customTagsOld];
    }

    /**
     * If the legacy payload is exactly:
     *     [ <category> => [ <object>=qty ], 'brands' => [ <brand>=qty ] ]
     * move the brand into that category block so later code
     * sees one object + one brand in the same group.
     */
    private function mergeSingleObjectAndBrand(array $tags): array
    {
        if (
            count($tags) === 2 &&
            isset($tags['brands']) &&
            count($tags['brands']) === 1
        ) {
            $keys = array_keys($tags); // eg ['smoking', 'brands']
            $otherKey = $keys[0] === 'brands' ? $keys[1] : $keys[0];

            if ($otherKey !== null && count($tags[$otherKey]) === 1) {
                Log::info("Auto‑merging single brand into '{$otherKey}' block.");

                // merge brand array into the category array
                $tags[$otherKey] = array_merge(
                    $tags[$otherKey],
                    $tags['brands']
                );

                unset($tags['brands']);
            }
        }

        return $tags;
    }

    protected function parseTags(array $originalTags, Collection $customTagsOld): array
    {
        $result = [];
        $globalBrands  = [];

        foreach ($originalTags as $categoryKey => $items)
        {
            /* -----------------------------------------------------------
             * 1. Handle the special top‑level "brands" block
             * ----------------------------------------------------------- */
            if ($categoryKey === 'brands') {
                foreach ($items as $tag => $qty) {
                    $parsed             = $this->classifyTags->classify($tag);
                    $parsed['quantity'] = (int) $qty ?: 1;
                    if ($parsed['type'] === 'brand') {
                        $globalBrands[] = $parsed;
                    }
                }
                continue;  // skip the normal category logic
            }

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

        /* ---------------------------------------------------------------
         * 2.  Sprinkle those brands into every group that has objects
         * --------------------------------------------------------------- */
        if ($globalBrands) {
            foreach ($result as &$group) {
                $group['brands'] = array_merge($group['brands'], $globalBrands);
            }
            unset($group);
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

    protected function createPhotoTags(Photo $photo, array $parsed): void
    {
        $hasObjects = false;

        foreach ($parsed as $group)
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
                        ->filter(fn ($pair) => $pair['object']['id'] === $object['id'])
                        ->map(fn (array $pair) => $pair['brand'])
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
