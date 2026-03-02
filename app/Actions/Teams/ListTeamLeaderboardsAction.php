<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTeamLeaderboardsAction
{
    /**
     * Load Teams ranked by total litter, paginated.
     */
    public function run(int $perPage = 25): LengthAwarePaginator
    {
        $paginator = Team::query()
            ->where('leaderboards', true)
            ->orderByDesc('total_litter')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'type_name' => $team->type_name,
            'total_members' => $team->members,
            'total_tags' => $team->total_litter,
            'total_images' => $team->total_images,
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ]);

        return $paginator;
    }
}
