<?php

namespace App\Services\Tags;

use App\Models\Photo;

/**
 * Generates a structured summary of photo tags with both IDs and keys
 * Now with corrected XP calculation using proper enum values
 */
class GeneratePhotoSummaryService
{
    public function run(Photo $photo): Photo
    {
        $tags = $photo->photoTags()
            ->with(['category', 'object', 'extraTags.extraTag'])
            ->get();

        // Initialize accumulators
        $grouped = [];
        $categoryTotals = [];
        $totalTags = 0;
        $totalObjects = 0;
        $materialCount = 0;
        $brandCount = 0;
        $customTagCount = 0;

        // Track all keys for reference
        $keyMap = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        // Build tag structure for XP calculation
        // Now we'll track object keys separately for special XP calculation
        $xpTags = [
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [], // Include custom tags for XP
        ];

        $objectIdToKey = []; // Map object IDs to keys for XP calculation

        foreach ($tags as $pt) {
            // Use IDs as primary keys, fallback to 0 for uncategorized
            $categoryId = $pt->category_id ?: 0;
            $objectId = $pt->litter_object_id ?: 0;
            $qty = $pt->quantity;

            // Store keys for reference
            if ($categoryId > 0 && $pt->category) {
                $keyMap['categories'][$categoryId] = $pt->category->key;
            }
            if ($objectId > 0 && $pt->object) {
                $keyMap['objects'][$objectId] = $pt->object->key;
                $objectIdToKey[$objectId] = $pt->object->key; // Track for XP calculation
            }

            // Count totals
            $totalTags += $qty;
            if ($objectId > 0) {
                $totalObjects += $qty;
                $xpTags['objects'][$objectId] = ($xpTags['objects'][$objectId] ?? 0) + $qty;
            }

            // Initialize structure
            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [];
            }
            if (!isset($grouped[$categoryId][$objectId])) {
                $grouped[$categoryId][$objectId] = [
                    'quantity' => 0,
                    'materials' => [],
                    'brands' => [],
                    'custom_tags' => [],
                ];
            }

            // Accumulate quantities
            $grouped[$categoryId][$objectId]['quantity'] += $qty;
            $categoryTotals[$categoryId] = ($categoryTotals[$categoryId] ?? 0) + $qty;

            // Process extra tags
            foreach ($pt->extraTags as $extra) {
                $extraId = $extra->tag_type_id;
                $extraQty = $extra->quantity;
                $totalTags += $extraQty;
                $categoryTotals[$categoryId] += $extraQty;

                // Store key if available
                if ($extraId && $extra->extraTag) {
                    $tagType = $extra->tag_type;
                    $mapKey = match($tagType) {
                        'material' => 'materials',
                        'brand' => 'brands',
                        'custom_tag' => 'custom_tags',
                        default => null,
                    };

                    if ($mapKey) {
                        $keyMap[$mapKey][$extraId] = $extra->extraTag->key;
                    }
                }

                // Accumulate by type
                switch ($extra->tag_type) {
                    case 'material':
                        $materialCount += $extraQty;
                        $grouped[$categoryId][$objectId]['materials'][$extraId] =
                            ($grouped[$categoryId][$objectId]['materials'][$extraId] ?? 0) + $extraQty;
                        $xpTags['materials'][$extraId] = ($xpTags['materials'][$extraId] ?? 0) + $extraQty;
                        break;

                    case 'brand':
                        $brandCount += $extraQty;
                        $grouped[$categoryId][$objectId]['brands'][$extraId] =
                            ($grouped[$categoryId][$objectId]['brands'][$extraId] ?? 0) + $extraQty;
                        $xpTags['brands'][$extraId] = ($xpTags['brands'][$extraId] ?? 0) + $extraQty;
                        break;

                    case 'custom_tag':
                        $customTagCount += $extraQty;
                        $grouped[$categoryId][$objectId]['custom_tags'][$extraId] =
                            ($grouped[$categoryId][$objectId]['custom_tags'][$extraId] ?? 0) + $extraQty;
                        $xpTags['custom_tags'][$extraId] = ($xpTags['custom_tags'][$extraId] ?? 0) + $extraQty;
                        break;
                }
            }
        }

        // Handle custom tags that are primary (not extra tags)
        foreach ($tags as $pt) {
            if ($pt->custom_tag_primary_id) {
                $customId = $pt->custom_tag_primary_id;
                $qty = $pt->quantity;

                $customTagCount += $qty;
                $totalTags += $qty;

                // Add to XP calculation
                $xpTags['custom_tags'][$customId] = ($xpTags['custom_tags'][$customId] ?? 0) + $qty;

                // Track the key if we have the relation loaded
                if ($pt->primaryCustomTag) {
                    $keyMap['custom_tags'][$customId] = $pt->primaryCustomTag->key;
                }
            }
        }

        // Sort categories and objects by quantity (descending)
        arsort($categoryTotals);
        $sortedGrouped = [];
        foreach ($categoryTotals as $catId => $catTotal) {
            if (!isset($grouped[$catId])) continue;

            // Sort objects within category
            $objects = $grouped[$catId];
            uasort($objects, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
            $sortedGrouped[$catId] = $objects;
        }

        // Assemble totals
        $totals = [
            'total_tags' => $totalTags,
            'total_objects' => $totalObjects,
            'by_category' => $categoryTotals,
            'materials' => $materialCount,
            'brands' => $brandCount,
            'custom_tags' => $customTagCount,
        ];

        // Build final summary
        $summary = [
            'tags' => $sortedGrouped,
            'totals' => $totals,
            'keys' => $keyMap,
        ];

        // Calculate XP using fixed calculator with object key mapping
        $xp = XpCalculator::calculateFromTags($xpTags, $objectIdToKey);

        // Add picked_up bonus if applicable
        if (!$photo->remaining) {
            $xp += \App\Enums\XpScore::PickedUp->xp(); // +5 XP for picked up
        }

        // Persist summary and XP
        $photo->update([
            'summary' => $summary,
            'xp' => $xp,
            'total_tags' => $totalTags,
            'total_brands' => $brandCount,
        ]);

        return $photo;
    }
}
