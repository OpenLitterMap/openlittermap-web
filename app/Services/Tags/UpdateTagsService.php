<?php

namespace App\Services\Tags;

use App\Models\Photo;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Category;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

class UpdateTagsService
{
    protected ClassifyTagsService $classifyTags;

    public function __construct(ClassifyTagsService $classifyTags)
    {
        $this->classifyTags = $classifyTags;
    }

    /**
     * Main migration entry point.
     */
    public function updateTags(Photo $photo): void
    {
        // Idempotency: skip if already migrated
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

        // Parse all tags into structured payload
        $parsedTags = $this->parseTags($originalTags, $customTagsOld);

        // Create new PhotoTag rows
        $this->createPhotoTags($photo, $parsedTags);

        // Recalculate totals
        $photo->calculateTotalTags();

        // Mark as migrated
        $photo->update(['migrated_at' => now()]);
    }

    /**
     * Fetch and normalize legacy tags.
     *
     * @return array [originalTags, EloquentCollection<CustomTag>]
     */
    protected function getTags(Photo $photo): array
    {
        $tags = $photo->tags() ?? [];
        $originalTags   = $this->mergeSingleObjectAndBrand($tags);
        $customTagsOld  = $photo->customTags ?? new EloquentCollection();
        return [$originalTags, $customTagsOld];
    }

    /**
     * Turn legacy tags + custom tags into structured arrays:
     * - 'groups'             => [<categoryKey> => [category_id, objects, brands, materials]]
     * - 'globalBrands'       => [<brandParsed>...]
     * - 'topLevelCustomTags' => [<customParsed>...]
     */
    protected function parseTags(array $originalTags, EloquentCollection $customTagsOld): array
    {
        $groups             = [];
        $globalBrands       = [];
        $topLevelCustomTags = [];

        // 1) Category-based blocks
        foreach ($originalTags as $categoryKey => $items) {
            if ($categoryKey === 'brands') {
                foreach ($items as $tag => $qty) {
                    $parsed = $this->classifyTags->classify($tag);
                    $parsed['quantity'] = (int)$qty ?: 1;
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

            foreach ($items as $tag => $quantity) {
                $parsed = $this->classifyTags->classify($tag);
                $parsed['quantity'] = (int)$quantity ?: 1;
                switch ($parsed['type']) {
                    case 'object':   $groups[$categoryKey]['objects'][]   = $parsed; break;
                    case 'brand':    $groups[$categoryKey]['brands'][]    = $parsed; break;
                    case 'material': $groups[$categoryKey]['materials'][] = $parsed; break;
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

    /**
     * Given parsedTags, create the appropriate PhotoTag records:
     * - category groups (object tags + extras)
     * - brands-only
     * - custom-only
     * - attach top-level custom tags to last tag
     */
    protected function createPhotoTags(Photo $photo, array $parsedTags): void
    {
        $groups             = $parsedTags['groups'];
        $globalBrands       = $parsedTags['globalBrands'];
        $topLevelCustomTags = $parsedTags['topLevelCustomTags'];

        $hasObjects = false;

        // 1) Category-based tags
        foreach ($groups as $group) {
            if (! empty($group['objects'])) {
                $hasObjects = true;
                $this->createFromCategoryGroup($photo, $group);
            }
        }

        // 2) Brands-only
        if (! $hasObjects && empty($topLevelCustomTags) && ! empty($globalBrands)) {
            $this->createBrandsOnlyTag($photo, $globalBrands);
            return;
        }

        // 3) Custom-only
        if (! $hasObjects && ! empty($topLevelCustomTags)) {
            $this->createCustomOnlyTag($photo, $topLevelCustomTags);
            return;
        }

        // 4) Attach any top-level custom tags to the last created tag
        if ($hasObjects && ! empty($topLevelCustomTags)) {
            $this->attachCustomTagsToLast($photo, $topLevelCustomTags);
        }
    }

    /**
     * Create tags for each object in the group, plus its brand/material extras
     */
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

            // Brands
            $matchedBrands = collect($brandLinks)
                ->filter(fn($pair) => $pair['object']['id'] === $object['id'])
                ->pluck('brand')
                ->unique('id')
                ->values()
                ->all();
            $photoTag->attachExtraTags($matchedBrands, 'brand', $index);

            // Materials
            if (! empty($object['materials'] ?? [])) {
                static $materialCache;
                if (! isset($materialCache)) {
                    $materialCache = $this->classifyTags->materialMap();
                }
                $matchedMaterials = array_filter($object['materials'], fn($k) => isset($materialCache[$k]));
                $matchedMaterials = array_map(fn($k) => [
                    'id'       => $materialCache[$k],
                    'key'      => $k,
                    'quantity' => 1
                ], $matchedMaterials);
                $photoTag->attachExtraTags($matchedMaterials, 'material', $index);
            }
        }
    }

    /** Create a single PhotoTag for global brands only */
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

    /** Create a primary PhotoTag when only custom tags exist */
    private function createCustomOnlyTag(Photo $photo, array $custom ):
    void
    {
        $primary = array_shift($custom);
        $photoTag = PhotoTag::create([
            'photo_id'              => $photo->id,
            'custom_tag_primary_id' => $primary['id'],
            'quantity'              => $primary['quantity'],
            'picked_up'             => ! $photo->remaining,
        ]);
        foreach ($custom as $idx => $extra) {
            $photoTag->attachExtraTags([$extra], 'custom_tag', $idx);
        }
    }

    /** Attach leftover top-level custom tags to the last created PhotoTag */
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

    /** Preserve legacy single-object+single-brand merge logic */
    private function mergeSingleObjectAndBrand(array $tags): array
    {
        if (
            count($tags) === 2 &&
            isset($tags['brands']) &&
            count($tags['brands']) === 1
        ) {
            $keys = array_keys($tags);
            $other = $keys[0] === 'brands' ? $keys[1] : $keys[0];
            if ($other && count($tags[$other]) === 1) {
                Log::info("Auto‑merging single brand into '{$other}' block.");
                $tags[$other] = array_merge($tags[$other], $tags['brands']);
                unset($tags['brands']);
            }
        }

        return $tags;
    }
}
