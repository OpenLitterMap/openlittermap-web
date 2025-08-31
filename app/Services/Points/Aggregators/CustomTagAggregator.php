<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomTagAggregator
{
    /**
     * Aggregate custom tag quantities
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        // Check if custom_tags_new table exists
        if (!Schema::hasTable('custom_tags_new')) {
            return [];
        }

        $results = DB::table('photo_tags')
            ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
            ->join('custom_tags_new', 'photo_tag_extra_tags.tag_type_id', '=', 'custom_tags_new.id')
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->where('photo_tag_extra_tags.tag_type', 'custom_tag')
            ->where('custom_tags_new.approved', true)
            ->groupBy('custom_tags_new.id', 'custom_tags_new.key')
            ->selectRaw('
                custom_tags_new.key,
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
