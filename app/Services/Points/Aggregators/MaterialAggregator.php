<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialAggregator
{
    /**
     * Aggregate material quantities
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        $results = DB::table('photo_tags')
            ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
            ->join('materials', 'photo_tag_extra_tags.tag_type_id', '=', 'materials.id')
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->where('photo_tag_extra_tags.tag_type', 'material')
            ->groupBy('materials.id', 'materials.key')
            ->selectRaw('
                materials.key,
                SUM(photo_tag_extra_tags.quantity) as qty
            ')
            ->orderByDesc('qty')
            ->limit(20)
            ->get();

        return $results->map(function($row) {
            return (object)[
                'key' => $row->key,
                'qty' => (int)$row->qty,
            ];
        })->toArray();
    }
}
