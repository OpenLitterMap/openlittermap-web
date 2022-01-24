<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\ListTeamLeaderboardsAction;
use App\Http\Controllers\Controller;
use App\Models\Teams\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TeamsLeaderboardController extends Controller
{

    /**
     * Load Teams ranked by total litter
     *
     * @param ListTeamLeaderboardsAction $action
     * @return Collection;
     */
    public function index (ListTeamLeaderboardsAction $action)
    {
        return $action->run();
    }

    /**
     * Toggle team leaderboard
     *
     * @return mixed
     */
    public function toggle (Request $request)
    {
        $team = Team::find($request->id);

        if ($team->leader === auth()->user()->id)
        {
            $team->leaderboards = ! $team->leaderboards;

            $team->save();

            return ['success' => true];
        }
    }
}
