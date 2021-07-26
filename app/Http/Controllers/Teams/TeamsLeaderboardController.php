<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Teams\Team;
use Illuminate\Http\Request;

class TeamsLeaderboardController extends Controller
{
    /**
     * Load Teams ranked by total litter
     * Todo - paginate this
     *
     * @return \Illuminate\Database\Eloquent\Collection;
     */
    public function index ()
    {
        return Team::select('name', 'total_litter', 'total_images', 'created_at')
            ->where('leaderboards', true)
            ->orderBy('total_litter', 'desc')
            ->get();
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
