<?php

namespace App\Services\Tags;

use App\Models\Photo;

class TagSummaryService
{
    public function generateTagSummary(Photo $photo): void
    {
        $summary = [];

        $categories = [
            // smoking => [ 'butts' => 1, 'brands' => ['marlboro' => 1], 'materials' => ['plastic' => 1], 'custom_tags' => ['custom_tag_id' => 1]],
            // alcohol => [ 'beerBottle' => 1, 'brands' => ['budweiser' => 1], 'materials' => ['glass' => 1]]
        ];

        $totals = [
            'objects' => 0,
            'materials' => 0,
            'brands' => 0,
            'custom_tags' => 0,
            // category_key => 0,
        ];

        $photoTags = $photo->photoTags()->with('category', 'object', 'extraTags')->get();

        foreach ($photoTags as $photoTag)
        {
            $categoryKey = $photoTag->category?->key ?? 'uncategorized';
            $objectKey = $photoTag->object?->key ?? 'unknown';
            $quantity = $photoTag->quantity ?? 1;

            $categories[$categoryKey] = true;
            $totals['tags'] += $quantity;
            $totals['objects'] += $quantity;
            $totals[$categoryKey] = ($totals[$categoryKey] ?? 0) + $quantity;

            $entry = [$objectKey => [
                'quantity' => $quantity,
                'brands' => [],
                'materials' => [],
            ]];

            foreach ($photoTag->extraTags as $extra) {
                $type = $extra->tag_type;
                $tagId = $extra->tag_type_id;
                $qty = $extra->quantity ?? 1;

                // Avoid duplicate values in lists
                $typePlural = $type === 'custom' ? 'custom_tags' : $type . 's';
                $entry[$objectKey][$typePlural][] = $tagId;

                $entry[$objectKey][$typePlural] = array_unique($entry[$objectKey][$typePlural]);

                $totals[$typePlural] += $qty;
            }

            $summary[$categoryKey][] = $entry;
        }

        // Handle custom-only tags
        $customOnly = $photo->photoTags()
            ->whereNull('litter_object_id')
            ->with('extraTags')
            ->get()
            ->flatMap->extraTags
            ->filter(fn($tag) => $tag->tag_type === 'custom');

        foreach ($customOnly as $custom) {
            $qty = $custom->quantity ?? 1;
            $tagId = $custom->tag_type_id;
            $summary['custom_tags'][] = [$tagId => $qty];
            $totals['custom_tags'] += $qty;
        }

        $totals['categories'] = count($categories);

        // Attach final metadata
        $summary['totals'] = $totals;
        $summary['metadata'] = [
            'photo_id' => $photo->id,
            'user_id' => $photo->user_id,
            'datetime' => $photo->datetime?->toIso8601String(),
            'picked_up' => !$photo->remaining,
        ];

        $summary['summary_version'] = 1;
        $photo->summary = $summary;

        $photo->save();
    }
}
