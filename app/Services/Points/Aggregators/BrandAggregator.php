<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrandAggregator
{
    /**
     * Aggregate brand quantities
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        // FIXED: Added explicit NULL checks and better debugging
        $results = DB::table('photo_tags')
            ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
            ->join('brandslist', 'photo_tag_extra_tags.tag_type_id', '=', 'brandslist.id')
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->where('photo_tag_extra_tags.tag_type', 'brand')
            ->whereNotNull('photo_tag_extra_tags.tag_type_id') // Explicit NULL check
            ->groupBy('brandslist.id', 'brandslist.key')
            ->selectRaw('
                brandslist.key,
                SUM(photo_tag_extra_tags.quantity) as qty
            ')
            ->orderByDesc('qty')
            ->limit(30)
            ->get();

        if ($results->isEmpty()) {
            $extraTagsCount = DB::table('photo_tags')
                ->join('photo_tag_extra_tags', 'photo_tags.id', '=', 'photo_tag_extra_tags.photo_tag_id')
                ->whereIn('photo_tags.photo_id', $photoIds)
                ->where('photo_tag_extra_tags.tag_type', 'brand')
                ->count();

            Log::debug("BrandAggregator: {$extraTagsCount} brand extra tags found but none matched brandslist table");
        }

        return $results->map(function($row) {
            return (object)[
                'key' => $row->key,
                'qty' => (int)$row->qty,
            ];
        })->toArray();
    }
}
