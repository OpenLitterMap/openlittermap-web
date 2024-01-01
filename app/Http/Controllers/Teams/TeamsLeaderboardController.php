<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\ListTeamLeaderboardsAction;
use App\Actions\Teams\ToggleLeaderboardVisibilityAction;
use App\Http\Controllers\Controller;
use App\Models\Teams\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TeamsLeaderboardController extends Controller
{

    /**
     * Load Teams ranked by total litter
     *
     * @return Collection;
     */
    public function index(ListTeamLeaderboardsAction $action)
    {
        return $action->run();
    }

    /**
     * Toggle team leaderboard
     */
    public function toggle(Request $request, ToggleLeaderboardVisibilityAction $action): array
    {
        /** @var Team $team */
        $team = Team::query()->findOrFail($request->team_id);

        if ($team->leader !== auth()->id()) {
            return ['success' => false, 'message' => 'member-not-allowed'];
        }

        $action->run($team);

        return ['success' => true, 'visible' => $team->leaderboards];
    }
}
