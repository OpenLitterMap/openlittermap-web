<?php

namespace App\Services\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
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

        $parsedTags = $this->parseTags($originalTags, $customTagsOld, $photo->id);

        $this->createPhotoTags($photo, $parsedTags);

        $this->generatePhotoSummaryService->run($photo);

        $photo->update(['migrated_at' => now()]);
    }

    public function getTags(Photo $photo): array
    {
        $tags           = $photo->tags() ?? [];
        $originalTags   = $this->mergeSingleObjectAndBrand($photo->id, $tags);
        $customTagsOld  = $photo->customTags ?? new EloquentCollection();

        return [$originalTags, $customTagsOld];
    }

    public function parseTags(array $originalTags, EloquentCollection $customTagsOld, int $photoId): array
    {
        $groups             = [];
        $globalBrands       = [];
        $topLevelCustomTags = [];

        Log::info("=== Starting brand matching for photo {$photoId} ===");

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

        // 2) IMPORTANT: Don't distribute brands to groups here!
        // They will be matched globally via pivot lookup in createPhotoTags()
        if (!empty($globalBrands)) {
            Log::info("Global brands will be matched via pivot lookup", [
                'photo_id' => $photoId,
                'brand_count' => count($globalBrands),
                'brands' => array_map(fn($b) => ['key' => $b['key'] ?? 'unknown', 'qty' => $b['quantity']], $globalBrands),
                'note' => 'Brands NOT distributed to groups - will be matched globally'
            ]);
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
        $createdPhotoTags = []; // Track created PhotoTag records for brand attachment

        // First pass: Create all object PhotoTags
        foreach ($groups as $categoryKey => $group) {
            if (! empty($group['objects'])) {
                $hasObjects = true;

                foreach ($group['objects'] as $index => $object) {
                    $photoTag = $photo->createTag([
                        'category_id'      => $group['category_id'],
                        'litter_object_id' => $object['id'],
                        'quantity'         => $object['quantity'],
                        'picked_up'        => ! $photo->remaining,
                    ]);

                    // Store for brand matching
                    $createdPhotoTags[] = [
                        'photo_tag' => $photoTag,
                        'object' => $object,
                        'category_key' => $categoryKey,
                        'index' => $index
                    ];

                    // Handle materials from deprecated tag mappings
                    if (!empty($object['materials'])) {
                        $materialCache = $this->classifyTags->materialMap();
                        $materialsToAttach = [];

                        foreach ($object['materials'] as $materialKey) {
                            if (isset($materialCache[$materialKey])) {
                                $materialsToAttach[] = [
                                    'id'       => $materialCache[$materialKey],
                                    'key'      => $materialKey,
                                    'quantity' => $object['quantity']
                                ];
                            } else {
                                Log::warning("Material '{$materialKey}' not found in cache", [
                                    'photo_id' => $photo->id,
                                    'object' => $object['key'] ?? 'unknown'
                                ]);
                            }
                        }

                        if (!empty($materialsToAttach)) {
                            $photoTag->attachExtraTags($materialsToAttach, 'material', 0);
                        }
                    }
                }
            }
        }

        // Second pass: Match brands globally via pivot lookup
        if ($hasObjects && !empty($globalBrands)) {
            Log::info("=== Matching brands globally via RULE 2 (pivot lookup) ===", [
                'photo_id' => $photo->id,
                'brand_count' => count($globalBrands),
                'object_count' => count($createdPhotoTags)
            ]);

            $this->attachBrandsGlobally($photo, $globalBrands, $createdPhotoTags);
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

    private function attachBrandsGlobally(Photo $photo, array $brands, array $createdPhotoTags): void
    {
        $attachedBrandIds = [];

        foreach ($brands as $brand) {
            $brandId = $brand['id'];
            $brandKey = $brand['key'] ?? 'unknown';
            $brandQty = $brand['quantity'];

            // Skip if already attached
            if (in_array($brandId, $attachedBrandIds, true)) {
                Log::info("→ Skipping already-attached brand", [
                    'photo_id' => $photo->id,
                    'brand' => $brandKey
                ]);
                continue;
            }

            Log::info("Attempting to match brand", [
                'photo_id' => $photo->id,
                'brand' => $brandKey,
                'qty' => $brandQty
            ]);

            $matched = false;

            // RULE 2: Try pivot table lookup FIRST - find FIRST object with pivot relationship
            foreach ($createdPhotoTags as $tagData) {
                $objectId = $tagData['object']['id'];
                $objectKey = $tagData['object']['key'] ?? 'unknown';

                // Check if this brand-object pair exists in pivot table
                $hasPivot = \DB::table('brand_object')
                    ->where('brand_id', $brandId)
                    ->where('litter_object_id', $objectId)
                    ->exists();

                if ($hasPivot) {
                    // Found match! Attach brand to this object
                    $tagData['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                    $attachedBrandIds[] = $brandId;
                    $matched = true;

                    Log::info("✓ RULE 2: Brand attached via database pivot (first match)", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'brand_qty' => $brandQty,
                        'object' => $objectKey,
                        'object_qty' => $tagData['object']['quantity'],
                        'category' => $tagData['category_key'],
                        'rule' => 'database_pivot_match'
                    ]);

                    break; // Stop after FIRST match
                }
            }

            // RULE 3: If no pivot match, try quantity matching
            if (!$matched) {
                $quantityMatches = [];

                // Find all objects with matching quantity
                foreach ($createdPhotoTags as $tagData) {
                    if ($tagData['object']['quantity'] === $brandQty) {
                        $quantityMatches[] = $tagData;
                    }
                }

                if (count($quantityMatches) === 1) {
                    // UNIQUE quantity match found
                    $match = $quantityMatches[0];
                    $match['photo_tag']->attachExtraTags([$brand], 'brand', 0);
                    $attachedBrandIds[] = $brandId;
                    $matched = true;

                    Log::info("✓ RULE 3: Brand attached via unique quantity match", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'brand_qty' => $brandQty,
                        'object' => $match['object']['key'] ?? 'unknown',
                        'object_qty' => $match['object']['quantity'],
                        'category' => $match['category_key'],
                        'rule' => 'unique_quantity_match'
                    ]);
                } elseif (count($quantityMatches) > 1) {
                    // Multiple objects with same quantity - ambiguous
                    Log::warning("⚠ Multiple objects with matching quantity - brand NOT attached", [
                        'photo_id' => $photo->id,
                        'brand' => $brandKey,
                        'brand_qty' => $brandQty,
                        'matching_objects' => array_map(fn($m) => [
                            'object' => $m['object']['key'] ?? 'unknown',
                            'qty' => $m['object']['quantity'],
                            'category' => $m['category_key']
                        ], $quantityMatches),
                        'action' => 'not_attached_ambiguous'
                    ]);
                }
            }

            // NO FALLBACK - If no match found, just log warning
            if (!$matched) {
                Log::warning("⚠ Brand could not be matched - NOT attached", [
                    'photo_id' => $photo->id,
                    'brand' => $brandKey,
                    'brand_qty' => $brandQty,
                    'available_objects' => array_map(fn($t) => [
                        'object' => $t['object']['key'] ?? 'unknown',
                        'qty' => $t['object']['quantity'],
                        'category' => $t['category_key']
                    ], $createdPhotoTags),
                    'reason' => 'no_pivot_match_no_unique_quantity_match',
                    'action' => 'not_attached_needs_review'
                ]);
            }
        }
    }

    private function createBrandsOnlyTag(Photo $photo, array $globalBrands): void
    {
        Log::info("Creating brands-only tag", [
            'photo_id' => $photo->id,
            'brands' => array_map(fn($b) => ['key' => $b['key'] ?? 'unknown', 'qty' => $b['quantity']], $globalBrands)
        ]);

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
                // Merge object & brand into single entry
                $tags[$other] = array_merge($tags[$other], $tags['brands']);
                unset($tags['brands']);
            }
        }

        return $tags;
    }
}
