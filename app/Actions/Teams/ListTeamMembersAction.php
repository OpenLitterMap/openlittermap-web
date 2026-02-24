<?php

namespace App\Actions\Teams;


use App\Models\Teams\Team;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class ListTeamMembersAction
{
    /**
     * Load team members ranked by total litter.
     *
     * Privacy is applied in two layers:
     * - School teams (safeguarding=true): real names returned here;
     *   MasksStudentIdentity::applySafeguarding() handles masking in the controller.
     * - Community teams: per-user show_name/show_username pivot settings applied at query level.
     */
    public function run(Team $team): Paginator
    {
        $query = $team
            ->users()
            ->withPivot('total_photos', 'total_litter', 'updated_at', 'show_name_leaderboards', 'show_username_leaderboards')
            ->orderBy('pivot_total_litter', 'desc');

        if ($team->safeguarding) {
            return $query->simplePaginate(10, [
                'users.id',
                'users.name',
                'users.username',
                'users.active_team',
                'users.updated_at',
                'total_photos',
            ]);
        }

        return $query->simplePaginate(10, [
            'users.id',
            DB::raw("if(`team_user`.`show_name_leaderboards` = 1, `name`, '') as name"),
            DB::raw("if(`team_user`.`show_username_leaderboards` = 1, `username`, '') as username"),
            'users.active_team',
            'users.updated_at',
            'total_photos',
        ]);
    }
}
