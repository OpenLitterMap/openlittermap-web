<?php

namespace App\Actions\Teams;

use App\Models\Photo;
use App\Models\Teams\Team;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class ListTeamMembersAction
{
    /**
     * Load team members ranked by total tags (live query).
     *
     * Privacy is applied in two layers:
     * - School teams (safeguarding=true): real names returned here;
     *   MasksStudentIdentity::applySafeguarding() handles masking in the controller.
     * - Community teams: per-user show_name/show_username pivot settings applied at query level.
     */
    public function run(Team $team): Paginator
    {
        $teamId = $team->id;

        $query = $team
            ->users()
            ->withPivot('show_name_leaderboards', 'show_username_leaderboards')
            ->addSelect([
                'member_total_photos' => Photo::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('photos.user_id', 'users.id')
                    ->where('photos.team_id', $teamId),
                'member_total_tags' => Photo::query()
                    ->selectRaw('COALESCE(SUM(total_tags), 0)')
                    ->whereColumn('photos.user_id', 'users.id')
                    ->where('photos.team_id', $teamId),
            ])
            ->orderByDesc('member_total_tags');

        if ($team->safeguarding) {
            return $query->simplePaginate(10, [
                'users.id',
                'users.name',
                'users.username',
                'users.active_team',
                'users.updated_at',
            ]);
        }

        return $query->simplePaginate(10, [
            'users.id',
            DB::raw("if(`team_user`.`show_name_leaderboards` = 1, `name`, '') as name"),
            DB::raw("if(`team_user`.`show_username_leaderboards` = 1, `username`, '') as username"),
            'users.active_team',
            'users.updated_at',
        ]);
    }
}
