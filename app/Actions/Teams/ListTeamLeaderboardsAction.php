<?php

namespace App\Actions\Teams;


use App\Models\Teams\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListTeamLeaderboardsAction
{
    /**
     * Load Teams ranked by total litter
     * Todo - paginate this
     * @return Builder[]|Collection
     */
    public function run()
    {
        return Team::query()
            ->select('name', 'total_litter', 'total_images', 'created_at')
            ->where('leaderboards', true)
            ->orderBy('total_litter', 'desc')
            ->get();
    }
}
