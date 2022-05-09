<?php

namespace App\Actions\Teams;


use App\Models\Teams\Team;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class ListTeamMembersAction
{
    /**
     * Load team members ranked by total litter
     * We need to check the privacy settings for each user on the team,
     * and only load the columns that each user has allowed.
     */
    public function run(Team $team): Paginator
    {
        return $team
            ->users()
            ->withPivot('total_photos', 'total_litter', 'updated_at', 'show_name_leaderboards', 'show_username_leaderboards')
            ->orderBy('pivot_total_litter', 'desc')
            ->simplePaginate(10, [
                'users.id',
                DB::raw("if(`team_user`.`show_name_leaderboards` = 1, `name`, '') as name"),
                DB::raw("if(`team_user`.`show_username_leaderboards` = 1, `username`, '') as username"),
                'users.active_team',
                'users.updated_at',
                'total_photos'
            ]);
    }
}
