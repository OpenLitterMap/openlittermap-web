<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Check if there are any tags at all for debugging
        $totalTags = DB::table('photo_tags')
            ->whereIn('photo_id', $photoIds)
            ->count();

        if ($totalTags === 0) {
            Log::debug("ObjectAggregator: No photo_tags found for " . $photoIds->count() . " photos");
            return [];
        }

        // FIXED: Added whereNotNull to explicitly exclude NULL litter_object_id
        // This makes the query intent clearer and can use indexes better
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

        if ($results->isEmpty()) {
            Log::debug("ObjectAggregator: {$totalTags} tags found but no objects matched. Check if litter_object_id values exist in litter_objects table.");
        }

        return $results->map(function($row) {
            return (object)[
                'key' => $row->key,
                'qty' => (int)$row->qty,
            ];
        })->toArray();
    }
}
