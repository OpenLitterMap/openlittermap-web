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
        $stats = DB::table('photos')
            ->whereIn('id', $photoIds)
            ->selectRaw('
                COUNT(*) as photos,
                COUNT(DISTINCT user_id) as users,
                COUNT(DISTINCT team_id) as teams,
                SUM(total_litter) as total_objects,
                SUM(total_tags) as total_tags,
                SUM(CASE WHEN remaining = 0 THEN 1 ELSE 0 END) as picked_up,
                SUM(CASE WHEN remaining = 1 THEN 1 ELSE 0 END) as not_picked_up
            ')
            ->first();

        return [
            'photos' => (int)$stats->photos,
            'users' => (int)$stats->users,
            'teams' => (int)$stats->teams,
            'total_objects' => (int)$stats->total_objects,
            'total_tags' => (int)$stats->total_tags,
            'picked_up' => (int)$stats->picked_up,
            'not_picked_up' => (int)$stats->not_picked_up,
        ];
    }
}
