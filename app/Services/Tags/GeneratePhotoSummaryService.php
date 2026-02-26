<?php

namespace App\Services\Tags;

use App\Enums\Dimension;
use App\Enums\XpScore;
use App\Models\Photo;

/**
 * Generates a structured summary of photo tags in flat array format.
 *
 * OUTPUT FORMAT:
 * {
 *   "tags": [
 *     {
 *       "clo_id": 42,
 *       "category_id": 1,
 *       "object_id": 5,
 *       "type_id": 3,
 *       "quantity": 2,
 *       "picked_up": true,
 *       "materials": [1, 4],
 *       "brands": {"7": 1},
 *       "custom_tags": [45]
 *     }
 *   ],
 *   "totals": {
 *     "litter": 3,
 *     "materials": 3,
 *     "brands": 1,
 *     "custom_tags": 0
 *   },
 *   "keys": {
 *     "categories": {"1": "alcohol"},
 *     "objects": {"5": "bottle"},
 *     "types": {"3": "beer"},
 *     "materials": {"1": "glass"},
 *     "brands": {"7": "heineken"}
 *   }
 * }
 *
 * Each entry in tags[] maps 1:1 to a photo_tags row.
 * Materials are ID arrays (set membership, qty=1).
 * Brands are {id: qty} maps (independent quantities).
 */
class GeneratePhotoSummaryService
{
    public function run(Photo $photo): Photo
    {
        $photoTags = $photo->photoTags()
            ->with(['category', 'object', 'type', 'extraTags.extraTag'])
            ->get();

        $tags = [];
        $totalLitter = 0;
        $totalMaterials = 0;
        $totalBrands = 0;
        $totalCustomTags = 0;

        $keyMap = [
            'categories' => [],
            'objects' => [],
            'types' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        // XP tracking
        $xpTags = [
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];
        $objectIdToKey = [];

        foreach ($photoTags as $pt) {
            $qty = $pt->quantity;
            $categoryId = $pt->category_id ?: 0;
            $objectId = $pt->litter_object_id ?: 0;
            $cloId = $pt->category_litter_object_id;
            $typeId = $pt->litter_object_type_id;

            $totalLitter += $qty;

            // Track keys
            if ($categoryId > 0 && $pt->category) {
                $keyMap['categories'][$categoryId] = $pt->category->key;
            }
            if ($objectId > 0 && $pt->object) {
                $keyMap['objects'][$objectId] = $pt->object->key;
                $objectIdToKey[$objectId] = $pt->object->key;
            }
            if ($typeId && $pt->type) {
                $keyMap['types'][$typeId] = $pt->type->key;
            }

            // XP: objects
            if ($objectId > 0) {
                $xpTags['objects'][$objectId] = ($xpTags['objects'][$objectId] ?? 0) + $qty;
            }

            // Process extras
            $materials = [];
            $brands = [];
            $customTags = [];

            foreach ($pt->extraTags as $extra) {
                $extraId = $extra->tag_type_id;

                // Track key if available
                if ($extraId && $extra->extraTag) {
                    $mapKey = match($extra->tag_type) {
                        Dimension::MATERIAL->value => 'materials',
                        Dimension::BRAND->value => 'brands',
                        Dimension::CUSTOM_TAG->value => 'custom_tags',
                        default => null,
                    };
                    if ($mapKey) {
                        $keyMap[$mapKey][$extraId] = $extra->extraTag->key;
                    }
                }

                switch ($extra->tag_type) {
                    case Dimension::MATERIAL->value:
                        $materials[] = $extraId;
                        // Materials weighted by parent tag quantity
                        $totalMaterials += $qty;
                        $xpTags['materials'][$extraId] = ($xpTags['materials'][$extraId] ?? 0) + $qty;
                        break;

                    case Dimension::BRAND->value:
                        $brands[(string) $extraId] = $extra->quantity;
                        // Brands have independent quantities
                        $totalBrands += $extra->quantity;
                        $xpTags['brands'][$extraId] = ($xpTags['brands'][$extraId] ?? 0) + $extra->quantity;
                        break;

                    case Dimension::CUSTOM_TAG->value:
                        $customTags[] = $extraId;
                        // Custom tags weighted by parent tag quantity
                        $totalCustomTags += $qty;
                        $xpTags['custom_tags'][$extraId] = ($xpTags['custom_tags'][$extraId] ?? 0) + $qty;
                        break;
                }
            }

            $tags[] = [
                'clo_id' => $cloId,
                'category_id' => $categoryId,
                'object_id' => $objectId,
                'type_id' => $typeId,
                'quantity' => $qty,
                'picked_up' => (bool) $pt->picked_up,
                'materials' => $materials,
                'brands' => (object) $brands,
                'custom_tags' => $customTags,
            ];
        }

        $totals = [
            'litter' => $totalLitter,
            'materials' => $totalMaterials,
            'brands' => $totalBrands,
            'custom_tags' => $totalCustomTags,
        ];

        // Remove empty key maps
        $keyMap = array_filter($keyMap, fn($map) => ! empty($map));

        $summary = [
            'tags' => $tags,
            'totals' => $totals,
        ];

        if (! empty($keyMap)) {
            $summary['keys'] = $keyMap;
        }

        // Calculate XP
        $xp = XpCalculator::calculateFromTags($xpTags, $objectIdToKey);

        if (! $photo->remaining) {
            $xp += XpScore::PickedUp->xp();
        }

        // Generate result_string for map display
        $resultString = '';
        foreach ($tags as $tagEntry) {
            $catKey = $keyMap['categories'][$tagEntry['category_id']] ?? 'other';
            $objKey = $keyMap['objects'][$tagEntry['object_id']] ?? 'unknown';
            $resultString .= $catKey . '.' . $objKey . ' ' . $tagEntry['quantity'] . ',';
        }

        $photo->update([
            'summary' => $summary,
            'xp' => $xp,
            'total_tags' => $totalLitter,
            'total_brands' => $totalBrands,
            'result_string' => $resultString,
        ]);

        return $photo;
    }
}
