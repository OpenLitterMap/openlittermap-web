<?php

namespace App\Services\Tags;

use App\Models\Photo;

class TagSummaryService
{
    public function generateTagSummary(Photo $photo): void
    {
        $totals = [
            'tags' => 0,
            'objects' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'categories' => [],
        ];

        $photoTags = $photo->photoTags()->with('category', 'object', 'extraTags')->get();

        foreach ($photoTags as $photoTag) {
            $categoryKey = $photoTag->category?->key ?? 'uncategorized';
            $objectKey = $photoTag->object?->key ?? 'unknown';
            $quantity = max(1, (int) $photoTag->quantity);

            $totals['tags'] += $quantity;
            $totals['objects'] += $quantity;

            $totals['categories'][$categoryKey] ??= [];
            $totals['categories'][$categoryKey][$objectKey] = ($totals['categories'][$categoryKey][$objectKey] ?? 0) + $quantity;

            foreach ($photoTag->extraTags as $extra) {
                $type = $extra->tag_type;
                $tagId = $extra->tag_type_id;
                $qty = max(1, (int) $extra->quantity);

                $typePlural = $type === 'custom' ? 'custom_tags' : $type . 's';

                $totals[$typePlural] += $qty;
                $totals['categories'][$categoryKey][$typePlural] ??= [];
                $totals['categories'][$categoryKey][$typePlural][$tagId] = ($totals['categories'][$categoryKey][$typePlural][$tagId] ?? 0) + $qty;
            }
        }

        // Handle extra custom tags where no object exists
        $customOnly = $photo->photoTags()
            ->whereNull('litter_object_id')
            ->with('extraTags')
            ->get()
            ->flatMap->extraTags
            ->filter(fn($tag) => $tag->tag_type === 'custom');

        foreach ($customOnly as $custom) {
            $qty = max(1, (int) $custom->quantity);
            $tagId = $custom->tag_type_id;

            $totals['tags'] += $qty;
            $totals['custom_tags'] += $qty;

            $totals['categories']['custom_tags'] ??= [];
            $totals['categories']['custom_tags'][$tagId] = ($totals['categories']['custom_tags'][$tagId] ?? 0) + $qty;
        }

        $summary = [
            'totals' => $totals,
            'metadata' => [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'datetime' => optional($photo->datetime)->toIso8601String(),
                'picked_up' => !$photo->remaining,
            ],
            'summary_version' => 1,
        ];

        $photo->summary = $summary;
        $photo->save();
    }
}
