<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryAggregator
{
    /**
     * Aggregate category breakdown (includes base quantities + materials)
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        // Get base quantities per category
        // FIXED: Added whereNotNull for explicit NULL handling
        $baseQuantities = DB::table('photo_tags')
            ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->whereNotNull('photo_tags.category_id') // Explicit NULL check
            ->groupBy('categories.id', 'categories.key')
            ->selectRaw('
                categories.id,
                categories.key,
                SUM(photo_tags.quantity) as base_qty
            ')
            ->get()
            ->keyBy('id');

        if ($baseQuantities->isEmpty()) {
            return [];
        }

        // Get material quantities per category
        $materialQuantities = DB::table('photo_tags')
            ->join('categories', 'photo_tags.category_id', '=', 'categories.id')
            ->join('photo_tag_extra_tags', function($join) {
                $join->on('photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
                    ->where('photo_tag_extra_tags.tag_type', '=', 'material');
            })
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->whereNotNull('photo_tags.category_id') // Explicit NULL check
            ->groupBy('categories.id')
            ->selectRaw('
                categories.id,
                SUM(photo_tag_extra_tags.quantity) as material_qty
            ')
            ->pluck('material_qty', 'id');

        // Combine base + materials
        $results = $baseQuantities->map(function($category) use ($materialQuantities) {
            $baseQty = (int)$category->base_qty;
            $materialQty = (int)($materialQuantities[$category->id] ?? 0);

            return (object)[
                'key' => $category->key,
                'qty' => $baseQty + $materialQty,
            ];
        });

        return $results->sortByDesc('qty')->values()->toArray();
    }
}
