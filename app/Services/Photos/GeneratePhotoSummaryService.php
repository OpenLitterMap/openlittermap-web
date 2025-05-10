<?php

namespace App\Services\Photos;

use App\Enums\XpScore;
use App\Models\Photo;

/**
 * Build and persist a single‑query JSON summary of this photo's tags + aggregates,
 * grouped first by category key, then by object key (with extra-tags nested),
 * plus flat totals for tags, objects, materials, brands, and custom tags.
 *
 * Final summary format:
 * [
 *   'tags' => [
 *     '<categoryKey>' => [
 *       '<objectKey>' => [
 *         'quantity'    => (int),
 *         'materials'   => [ '<materialKey>' => (int), ... ],
 *         'brands'      => [ '<brandKey>'    => (int), ... ],
 *         'custom_tags' => [ '<customKey>'   => (int), ... ],
 *       ],
 *       ... // more objects per category
 *     ],
 *     ... // more categories
 *   ],
 *   'totals' => [
 *     'total_tags'    => (int),
 *     'total_objects' => (int),
 *     'by_category'   => [ '<categoryKey>' => (int), ... ],
 *     'materials'     => (int),
 *     'brands'        => (int),
 *     'custom_tags'   => (int),
 *   ],
 * ]
 *
 * Categories and objects are ordered descending by their quantities.
 *
 * @return $this
 */
class GeneratePhotoSummaryService
{
    public function run(Photo $photo): Photo
    {
        // Eager‑load all tags and relations
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
        $pickedUpCount = 0;
        $xp = XpScore::Upload->xp();

        foreach ($tags as $pt) {
            $categoryKey = $pt->category?->key ?? 'custom';
            $objectKey = $pt->object?->key ?? 'unknown';
            $qty = $pt->quantity;

            // Flat totals
            $totalTags += $qty;
            if ($pt->litter_object_id) {
                $totalObjects += $qty;
            }

            // Grouping init
            $grouped[$categoryKey][$objectKey]['quantity'] =
                ($grouped[$categoryKey][$objectKey]['quantity'] ?? 0) + $qty;
            foreach (['materials', 'brands', 'custom_tags'] as $typeBucket) {
                $grouped[$categoryKey][$objectKey][$typeBucket] ??= [];
            }
            $categoryTotals[$categoryKey] =
                ($categoryTotals[$categoryKey] ?? 0) + $qty;

            // XP for object tags
            $xp += $qty * XpScore::getObjectXp($objectKey);

            // Handle extra tags
            foreach ($pt->extraTags as $extra) {
                $extraQty = $extra->quantity;
                $totalTags += $extraQty;
                $categoryTotals[$categoryKey] += $extraQty;

                // Increment type-specific counters and buckets
                switch ($extra->tag_type) {
                    case 'material':
                        $materialCount += $extraQty;
                        $bucket = 'materials';
                        break;
                    case 'brand':
                        $brandCount += $extraQty;
                        $bucket = 'brands';
                        break;
                    case 'picked_up':
                        $pickedUpCount += $extraQty;
                        $bucket = 'picked_up';
                        break;
                    case 'custom_tag':
                    default:
                        $customTagCount += $extraQty;
                        $bucket = 'custom_tags';
                        break;
                }

                $tagKey = $extra->extraTag?->key;
                $grouped[$categoryKey][$objectKey][$bucket][$tagKey] =
                    ($grouped[$categoryKey][$objectKey][$bucket][$tagKey] ?? 0) + $extraQty;

                // XP for each extra tag
                $xp += $extraQty * XpScore::getTagXp($extra->tag_type);
            }
        }

        // Sort categories and objects by quantity desc
        foreach ($grouped as &$objects) {
            uasort($objects, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        }
        unset($objects);
        uksort($grouped, fn($a, $b) =>
            ($categoryTotals[$b] ?? 0) <=> ($categoryTotals[$a] ?? 0)
        );

        // Assemble totals
        $totals = [
            'total_tags'    => $totalTags,
            'total_objects' => $totalObjects,
            'by_category'   => $categoryTotals,
            'materials'     => $materialCount,
            'brands'        => $brandCount,
            'custom_tags'   => $customTagCount,
        ];

        // Default summary structure
        $defaultSummary = [
            'tags'   => [],
            'totals' => [
                'total_tags'    => 0,
                'total_objects' => 0,
                'by_category'   => [],
                'materials'     => 0,
                'brands'        => 0,
                'custom_tags'   => 0,
            ],
        ];

        // Merge computed into defaults
        $summary = array_replace_recursive(
            $defaultSummary,
            ['tags' => $grouped, 'totals' => $totals]
        );

        // Persist summary and XP
        $photo->update([
            'summary'    => $summary,
            'xp'         => $xp,
            'total_tags' => $totalTags,
            'total_brands' => $brandCount,
        ]);

        return $photo;
    }
}
