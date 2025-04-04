<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Litter\Tags\Taggable;
use App\Models\Photo;
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

        // Step 1: Parse all the photos tags
        // We don't know the relations between the tags yet.
        $parsedTags = $this->parseTags($photo);

        // Step 2: Populate new pivot + morph structure
        $this->createPivotsAndTaggables($photo, $parsedTags);

        // Step 3: Create legacy photo_tags + photo_tag_extras
        $this->createPhotoTags($photo, $parsedTags);
    }

    protected function parseTags(Photo $photo): array
    {
        $result = [];
        $originalTags = $photo->tags ?? [];
        $customTags = $photo->customTags ?? [];

        // E.g. [["smoking" => ["butts" => 1], "alcohol" => ["beerBottle" => 1], "brands" => ["pepsi" => 1, "marlboro" => 1]]
        foreach ($originalTags as $categoryKey => $items)
        {
            $category = $this->classifyTags->getCategory($categoryKey);

            if (!$category) {
                Log::warning("No matching Category for key: {$categoryKey}");
                continue;
            }

            // Store in final array
            $result[$categoryKey] = [
                'category_id'   => $category->id,
                'objects'       => [],
                'brands'        => [],
                'materials'     => [],
                'customTagsNew' => [],
            ];

            foreach ($items as $tag => $count) {
                $parsed = $this->classifyTags->classify($tag);
                $parsed['count'] = (int) $count ?: 1;

                match ($parsed['type']) {
                    'object' => $result[$categoryKey]['objects'][] = $parsed,
                    'brand' => $result[$categoryKey]['brands'][] = $parsed,
                    'material' => $result[$categoryKey]['materials'][] = $parsed,
                    default => Log::info("Unhandled tag type: {$parsed['type']} for tag: {$tag}")
                };
            }
        }

        if (!empty($customTags)) {
            foreach ($customTags as $ctag) {
                $parsed = $this->classifyTags->normalizeCustomTag($ctag);
                $result['custom_tags'][] = [
                    'key'   => $parsed['key'],
                    'id'    => $parsed['id'],
                    'count' => $parsed['count'] ?? 1,
                ];
            }
        }

        return $result;
    }

    /**
     * ---------------------------------------------------------------------
     * STEP 2: Create or update the new pivot + morph structure
     * ---------------------------------------------------------------------
     *
     * For each category => multiple objects, we:
     *   - Create/find CategoryObject pivot row
     *   - Attach brand(s) or material(s) in `taggables` (via ->brands()->sync, etc.)
     *   - If an object has "extra_material_ids," attach them as well
     */
    protected function createPivotsAndTaggables(Photo $photo, array $parsed): void
    {
        foreach ($parsed as $groupKey => $group)
        {
            if (!isset($group['objects']) || empty($group['objects'])) {
                continue;
            }

            foreach ($group['objects'] as $object)
            {
                $pivot = CategoryObject::firstOrCreate([
                    'category_id' => $group['category_id'],
                    'litter_object_id' => $object['id'],
                ]);

                $this->attachTaggables($pivot->id, $group['brands'], BrandList::class);
                $this->attachTaggables($pivot->id, $group['materials'], Materials::class);
                $this->attachTaggables($pivot->id, $group['customTagsNew'], CustomTagNew::class);

                if (!empty($object['materials'] ?? [])) {
                    foreach ($object['materials'] as $matKey) {
                        $material = $this->classifyTags->classifyNewKey($matKey);
                        if ($material['type'] === 'material') {
                            Taggable::firstOrCreate([
                                'category_litter_object_id' => $pivot->id,
                                'taggable_type' => Materials::class,
                                'taggable_id'   => $material['id'],
                            ], ['count' => $object['count']]);
                        }
                    }
                }
            }
        }
    }

    protected function attachTaggables(int $pivotId, array $taggables, string $class): void
    {
        foreach ($taggables as $tag) {
            Taggable::firstOrCreate([
                'category_litter_object_id' => $pivotId,
                'taggable_type'             => $class,
                'taggable_id'               => $tag['id'],
            ], [
                'count' => $tag['count'],
            ]);
        }
    }

    protected function createPhotoTags(Photo $photo, array $parsed): void
    {
        foreach ($parsed as $groupKey => $group) {
            if (!isset($group['objects']) || empty($group['objects'])) {
                continue;
            }

            foreach ($group['objects'] as $object) {
                PhotoTag::create([
                    'photo_id'         => $photo->id,
                    'category_id'      => $group['category_id'],
                    'litter_object_id' => $object['id'],
                    'quantity'         => $object['count'],
                    'picked_up'        => !$photo->remaining,
                ]);
            }
        }

        if (!empty($parsed['custom_tags'] ?? [])) {
            foreach ($parsed['custom_tags'] as $custom) {
                PhotoTag::firstOrCreate([
                    'photo_id'              => $photo->id,
                    'custom_tag_primary_id' => $custom['id'],
                    'quantity'              => $custom['count'],
                    'picked_up'             => !$photo->remaining,
                ]);
            }
        }
    }

}
