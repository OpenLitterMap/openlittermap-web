<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObjectAggregator
{
    /**
     * Aggregate litter objects (base quantities only, no extras)
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        $results = DB::table('photo_tags')
            ->join('litter_objects', 'photo_tags.litter_object_id', '=', 'litter_objects.id')
            ->whereIn('photo_tags.photo_id', $photoIds)
            ->whereNotNull('photo_tags.litter_object_id') // Explicit NULL check
            ->groupBy('litter_objects.id', 'litter_objects.key')
            ->selectRaw('
                litter_objects.key,
                SUM(photo_tags.quantity) as qty
            ')
            ->orderByDesc('qty')
            ->limit(50)
            ->get();

        return $results->map(function($row) {
            return (object)[
                'key' => $row->key,
                'qty' => (int)$row->qty,
            ];
        })->toArray();
    }
}
