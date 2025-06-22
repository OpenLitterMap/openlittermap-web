<?php

namespace App\Services\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Category;
use App\Services\Photos\GeneratePhotoSummaryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

class UpdateTagsService
{
    protected ClassifyTagsService $classifyTags;
    protected GeneratePhotoSummaryService $generatePhotoSummaryService;

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
            Log::info("No tags to migrate for photo ID: {$photo->id}");
            $photo->update(['migrated_at' => now()]);
            return;
        }

        $parsedTags = $this->parseTags($originalTags, $customTagsOld);

        $this->createPhotoTags($photo, $parsedTags);

        $this->generatePhotoSummaryService->run($photo);

        $photo->update(['migrated_at' => now()]);
    }

    protected function getTags(Photo $photo): array
    {
        $tags           = $photo->tags() ?? [];
        $originalTags   = $this->mergeSingleObjectAndBrand($photo->id, $tags);
        $customTagsOld  = $photo->customTags ?? new EloquentCollection();

        return [$originalTags, $customTagsOld];
    }

    protected function parseTags(array $originalTags, EloquentCollection $customTagsOld): array
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

        // 2) Distribute global brands into each group
        if (! empty($globalBrands)) {
            foreach ($groups as &$group) {
                $group['brands'] = array_merge($group['brands'], $globalBrands);
            }
            unset($group);
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

        // Category-based tags
        foreach ($groups as $group) {
            if (! empty($group['objects'])) {
                $hasObjects = true;
                $this->createFromCategoryGroup($photo, $group);
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

    private function createFromCategoryGroup(Photo $photo, array $group): void
    {
        $brandLinks = $this->classifyTags->resolveBrandObjectLinks($photo->id, $group);

        foreach ($group['objects'] as $index => $object) {
            $photoTag = $photo->createTag([
                'category_id'      => $group['category_id'],
                'litter_object_id' => $object['id'],
                'quantity'         => $object['quantity'],
                'picked_up'        => ! $photo->remaining,
            ]);

            $matchedBrands = collect($brandLinks)
                ->filter(fn($pair) => $pair['object']['id'] === $object['id'])
                ->pluck('brand')
                ->unique('id')
                ->values()
                ->all();

            $photoTag->attachExtraTags($matchedBrands, 'brand', $index);

            if (! empty($object['materials'] ?? [])) {

                $materialCache = $this->classifyTags->materialMap();

                $matchedMaterials = array_filter($object['materials'], fn($k) => isset($materialCache[$k]));
                $matchedMaterials = array_map(fn($k) => [
                    'id'       => $materialCache[$k],
                    'key'      => $k,
                    'quantity' => $object['quantity']
                ], $matchedMaterials);
                $photoTag->attachExtraTags($matchedMaterials, 'material', $index);
            }
        }
    }

    private function createBrandsOnlyTag(Photo $photo, array $globalBrands): void
    {
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

    private function mergeSingleObjectAndBrand(int $photoId, array $tags): array
    {
        if (
            count($tags) === 2 &&
            isset($tags['brands']) &&
            count($tags['brands']) === 1
        ) {
            $keys = array_keys($tags);
            $other = $keys[0] === 'brands' ? $keys[1] : $keys[0];

            if ($other && count($tags[$other]) === 1)
            {
                $objectKey   = array_key_first($tags[$other]);
                $brandKey    = array_key_first($tags['brands']);

//                Log::info(
//                    'Merge object and brand',
//                    [
//                        'photo_id' => $photoId,
//                        'object'   => $objectKey,
//                        'brand'    => $brandKey,
//                    ]
//                );
                $tags[$other] = array_merge($tags[$other], $tags['brands']);
                unset($tags['brands']);
            }
        }

        return $tags;
    }
}
