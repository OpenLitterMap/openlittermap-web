<?php

namespace App\Services\Points\Aggregators;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContributorAggregator
{
    /**
     * Aggregate top contributors
     */
    public function aggregate(Collection $photoIds): array
    {
        if ($photoIds->isEmpty()) {
            return [];
        }

        $results = DB::table('photos')
            ->join('users', 'photos.user_id', '=', 'users.id')
            ->whereIn('photos.id', $photoIds)
            ->where('users.show_username_maps', true)
            ->groupBy('users.id', 'users.username', 'users.name')
            ->selectRaw('
                users.username,
                users.name,
                COUNT(DISTINCT photos.id) as photo_count,
                COALESCE(SUM(photos.total_litter), 0) as total_litter,
                MIN(photos.datetime) as first_contribution,
                MAX(photos.datetime) as last_contribution
            ')
            ->orderByDesc('total_litter')
            ->limit(10)
            ->get();

        return $results->map(function($user) {
            return (object)[
                'username' => $user->username,
                'name' => $user->name,
                'photo_count' => (int)$user->photo_count,
                'total_litter' => (int)$user->total_litter,
                'first_contribution' => $user->first_contribution,
                'last_contribution' => $user->last_contribution,
            ];
        })->toArray();
    }
}
