<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;
use Illuminate\Pagination\LengthAwarePaginator;

class ListTeamLeaderboardsAction
{
    /**
     * Load Teams ranked by total tags (live query), paginated.
     */
    public function run(int $perPage = 25): LengthAwarePaginator
    {
        $paginator = Team::query()
            ->select('teams.*')
            ->withPhotoStats()
            ->where('leaderboards', true)
            ->orderByDesc('total_tags')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'type_name' => $team->type_name,
            'total_members' => $team->members,
            'total_tags' => (int) $team->total_tags,
            'total_photos' => (int) $team->total_photos,
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ]);

        return $paginator;
    }
}
