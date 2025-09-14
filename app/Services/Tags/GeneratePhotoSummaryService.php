<?php

namespace App\Services\Tags;

use App\Enums\XpScore;
use App\Models\Photo;

/**
 * Generates a structured summary of photo tags with both IDs and keys.
 *
 * OUTPUT FORMAT:
 * The summary field contains a JSON structure with three main sections:
 *
 * 1. "tags" - Nested structure of categories > objects > properties
 *    {
 *      "2": {                     // Category ID (e.g., smoking)
 *        "65": {                  // Object ID (e.g., wrapper)
 *          "quantity": 5,         // Number of this object
 *          "materials": {         // Optional: Material IDs with quantities
 *            "16": 3,             // e.g., plastic: 3
 *            "15": 2              // e.g., paper: 2
 *          },
 *          "brands": {            // Optional: Brand IDs with quantities
 *            "12": 3              // e.g., marlboro: 3
 *          },
 *          "custom_tags": {       // Optional: Custom tag IDs with quantities
 *            "45": 1              // e.g., user_defined_tag: 1
 *          }
 *        }
 *      }
 *    }
 *
 * 2. "totals" - Aggregated counts across all tags
 *    {
 *      "total_tags": 15,          // Sum of all items including extras
 *      "total_objects": 5,         // Sum of primary objects only
 *      "by_category": {            // Total items per category
 *        "2": 10,                  // Category ID: total count
 *        "4": 5
 *      },
 *      "materials": 8,             // Total material tags
 *      "brands": 3,                // Total brand tags
 *      "custom_tags": 2            // Total custom tags
 *    }
 *
 * 3. "keys" - Human-readable keys for all IDs (only includes used IDs)
 *    {
 *      "categories": {"2": "smoking", "4": "softdrinks"},
 *      "objects": {"65": "wrapper", "119": "butts"},
 *      "materials": {"16": "plastic", "15": "paper"},
 *      "brands": {"12": "marlboro"},
 *      "custom_tags": {"45": "user_tag"}
 *    }
 *
 * NOTES:
 * - Empty arrays are removed from output (materials, brands, custom_tags)
 * - Categories and objects are sorted by quantity (descending)
 * - Category ID 0 represents uncategorized items
 * - All IDs are strings in JSON for consistency
 * - XP calculation uses the XpCalculator with proper enum values
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
        $xpTags = [
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
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
                $objectIdToKey[$objectId] = $pt->object->key;
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

            // Remove empty arrays before adding to sorted group
            foreach ($objects as $objId => &$objData) {
                if (empty($objData['materials'])) {
                    unset($objData['materials']);
                }
                if (empty($objData['brands'])) {
                    unset($objData['brands']);
                }
                if (empty($objData['custom_tags'])) {
                    unset($objData['custom_tags']);
                }
            }

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

        // Remove empty arrays from keyMap
        foreach ($keyMap as $type => &$map) {
            if (empty($map)) {
                unset($keyMap[$type]);
            }
        }

        // Build final summary (removing empty keys section if all are empty)
        $summary = [
            'tags' => $sortedGrouped,
            'totals' => $totals,
        ];

        // Only add keys if there are any
        if (!empty($keyMap)) {
            $summary['keys'] = $keyMap;
        }

        // Calculate XP using fixed calculator with object key mapping
        $xp = XpCalculator::calculateFromTags($xpTags, $objectIdToKey);

        // Add picked_up bonus if applicable
        if (!$photo->remaining) {
            $xp += XpScore::PickedUp->xp(); // +5 XP for picked up
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
