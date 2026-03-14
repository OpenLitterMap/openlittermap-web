<?php

namespace App\Actions\Teams;

use App\Models\Photo;
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
            ->where('leaderboards', true)
            ->addSelect([
                'teams.*',
                'team_total_photos' => Photo::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('photos.team_id', 'teams.id'),
                'team_total_tags' => Photo::query()
                    ->selectRaw('COALESCE(SUM(total_tags), 0)')
                    ->whereColumn('photos.team_id', 'teams.id'),
            ])
            ->orderByDesc('team_total_tags')
            ->paginate($perPage);

        $paginator->getCollection()->transform(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'type_name' => $team->type_name,
            'total_members' => $team->members,
            'total_tags' => (int) $team->team_total_tags,
            'total_photos' => (int) $team->team_total_photos,
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
        ]);

        return $paginator;
    }
}
