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
            ->where('leaderboards', true)
            ->orderByDesc('total_litter')
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'type_name' => $team->type_name,
                'total_members' => $team->members,
                'total_tags' => $team->total_litter,
                'total_images' => $team->total_images,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ]);
    }
}
