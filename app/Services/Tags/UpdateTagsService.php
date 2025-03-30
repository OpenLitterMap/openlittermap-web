<?php

namespace App\Services\Tags;

use App\Models\Photo;

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

    public function updateTags(Photo $photo): void
    {
        if (!is_array($photo->tags)) {
            return;
        }

        foreach ($photo->tags as $categoryKey => $tagItems) {
            $category = $this->classifyTags->getCategory($categoryKey);

            $objects = [];
            $brands = [];
            $materials = [];

            foreach ($tagItems as $tag => $quantity) {
                $classified = $this->classifyTags->classify($tag);

                if ($classified['type'] === 'undefined') {
                    \Log::warning("Unclassified tag: {$tag}", ['photo_id' => $photo->id]);
                    continue;
                }

                $baseData = [
                    'photo_id'         => $photo->id,
                    'category_id'      => $category?->id,
                    'quantity'         => $quantity ?? null,
                    'picked_up'        => !$photo->remaining,
                    'litter_object_id' => null,
                    'material_id'      => null,
                    'brand_id'         => null,
                    'custom_tag_primary_id' => null,
                ];

                switch ($classified['type']) {
                    case 'object':
                        $objects[] = array_merge($baseData, [
                            'litter_object_id' => $classified['id'],
                        ]);
                        break;
                    case 'brand':
                        $brands[] = $classified['id'];
                        break;
                    case 'material':
                        $materials[] = $classified['id'];
                        break;
                    case 'custom':
                        // We'll handle custom tags in updateCustomTags() or skip here
                        break;
                }
            }

            // Assign one brand if exactly one object and one brand exist
            if (count($objects) === 1 && count($brands) === 1) {
                $objects[0]['brand_id'] = $brands[0];
            } elseif (count($brands) > 1 && count($objects) > 0) {
                \Log::info("Multiple brands for photo {$photo->id} in category {$categoryKey} - skipping brand linkage");
            }

            // Attach material to each object (optional logic)
            foreach ($objects as &$objectData) {
                if (count($materials) === 1) {
                    $objectData['material_id'] = $materials[0];
                }
            }
        }
    }

    public function updateCustomTags(Photo $photo): void
    {
        $customTags = $photo->custom_tags;

        if (!is_array($customTags)) {
            return;
        }

        $skipPrefixes = ['brand', 'brands', 'bn', 'category', 'cat', 'object', 'objects', 'material', 'materials'];

        $classifiedTags = [];

        foreach ($customTags as $rawTag) {
            $segments = explode(':', $rawTag);

            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (in_array(strtolower($segment), $skipPrefixes)) {
                    continue;
                }

                $classified = $this->classifyTags->classify($segment);

                if ($classified['type'] === 'undefined') {
                    \Log::warning("Unclassified custom tag: {$segment}", ['photo_id' => $photo->id]);
                    continue;
                }

                $classifiedTags[] = $classified;
            }
        }

        // If no tags exist but we have at least one custom tag, assign one as primary
        if (empty($photo->tags) && count($classifiedTags) > 0) {
            $primary = $classifiedTags[0];

            $this->photoTagService->createTag([
                'photo_id' => $photo->id,
                'category_id' => null,
                'custom_tag_primary_id' => $primary['id'],
                'quantity' => 1,
                'picked_up' => !$photo->remaining,
            ]);
        }

        // Also create separate PhotoTag entries for any remaining classified custom tags
        foreach ($classifiedTags as $classified) {
            $data = [
                'photo_id' => $photo->id,
                'quantity' => 1,
                'picked_up' => !$photo->remaining,
            ];

            $data[$classified['type'] . '_id'] = $classified['id'];

            $this->photoTagService->createTag($data);
        }
    }

}
