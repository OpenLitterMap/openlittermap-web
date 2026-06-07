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

        // Get photo-level stats
        $photoStats = DB::table('photos')
            ->whereIn('id', $photoIds)
            ->selectRaw('
                COUNT(*) as photos,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT team_id) as teams
            ')
            ->first();

        // Picked-up counts come from each photo's first tag (photo_tags.picked_up),
        // consistent with Photo::picked_up — NOT the deprecated photos.remaining.
        // Untagged photos (and null first-tag photos) count as neither.
        $firstTagIds = DB::table('photo_tags')
            ->whereIn('photo_id', $photoIds)
            ->groupBy('photo_id')
            ->selectRaw('MIN(id) as id')
            ->pluck('id');

        $pickedUp = 0;
        $notPickedUp = 0;
        if ($firstTagIds->isNotEmpty()) {
            $pickedStats = DB::table('photo_tags')
                ->whereIn('id', $firstTagIds)
                ->selectRaw('
                    SUM(CASE WHEN picked_up = 1 THEN 1 ELSE 0 END) as picked_up,
                    SUM(CASE WHEN picked_up = 0 THEN 1 ELSE 0 END) as not_picked_up
                ')
                ->first();
            $pickedUp = (int) ($pickedStats->picked_up ?? 0);
            $notPickedUp = (int) ($pickedStats->not_picked_up ?? 0);
        }

        // Calculate total_objects from photo_tags quantity sum
        $totalObjects = DB::table('photo_tags')
            ->whereIn('photo_id', $photoIds)
            ->sum('quantity');

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
            'picked_up' => $pickedUp,
            'not_picked_up' => $notPickedUp,
        ];
    }
}
