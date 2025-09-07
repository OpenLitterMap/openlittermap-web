<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MetadataAggregator
{
    /**
     * Aggregate metadata from photo IDs
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [
                'photos' => 0,
                'users' => 0,
                'teams' => 0,
                'total_objects' => 0,
                'total_tags' => 0,
                'picked_up' => 0,
                'not_picked_up' => 0,
            ];
        }

        // Debug: Let's see what photo IDs we're getting
        \Log::info('MetadataAggregator photoIds:', $photoIds->toArray());

        // Get photo-level stats
        $photoStats = DB::table('photos')
            ->whereIn('id', $photoIds)
            ->selectRaw('
                COUNT(*) as photos,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT team_id) as teams,
                SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) as picked_up,
                SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) as not_picked_up
            ')
            ->first();

        // Debug: Check if we have photo_tags for these photos
        $photoTagCount = DB::table('photo_tags')
            ->whereIn('photo_id', $photoIds)
            ->count();
        \Log::info('Found photo_tags:', ['count' => $photoTagCount]);

        // Calculate total_objects from photo_tags quantity sum
        $totalObjects = DB::table('photo_tags')
            ->whereIn('photo_id', $photoIds)
            ->sum('quantity');

        // Debug log
        \Log::info('Total objects sum:', ['total' => $totalObjects]);

        // If null, convert to 0
        $totalObjects = $totalObjects ?? 0;

        // Calculate total_tags (base quantities + extra quantities)
        $totalTags = $totalObjects; // Start with base

        // Add extra tag quantities
        $extraQuantities = DB::table('photo_tag_extra_tags')
            ->whereIn('photo_tag_id', function($query) use ($photoIds) {
                $query->select('id')
                    ->from('photo_tags')
                    ->whereIn('photo_id', $photoIds);
            })
            ->sum('quantity') ?? 0;

        $totalTags += $extraQuantities;

        return [
            'photos' => (int)($photoStats->photos ?? 0),
            'users' => (int)($photoStats->users ?? 0),
            'teams' => (int)($photoStats->teams ?? 0),
            'total_objects' => (int)$totalObjects,
            'total_tags' => (int)$totalTags,
            'picked_up' => (int)($photoStats->picked_up ?? 0),
            'not_picked_up' => (int)($photoStats->not_picked_up ?? 0),
        ];
    }
}
