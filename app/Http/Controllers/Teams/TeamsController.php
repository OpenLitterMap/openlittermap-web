<?php

namespace App\Http\Controllers\Teams;

use App\Models\Teams\Team;
use App\Models\Teams\TeamType;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{
    /**
     * The user wants to create a new team
     */
    public function create (Request $request)
    {
        $request->validate([
            'name' => 'required|min:3|max:100',
            'identifier' => 'required|min:3|max:15|unique:teams',
            'teamType' => 'required'
        ]);

        $user = Auth::user();

        if ($user->remaining_teams === 0) return ['success' => false, 'msg' => 'max-created'];

        $team = Team::create([
            'created_by' => $user->id,
            'name' => $request->name,
            'type_id' => $request->teamType,
            'leader' => $user->id,
            'identifier' => $request->identifier
        ]);

        // Have the user join this team
        $user->teams()->attach($team);

        $user->active_team = $team->id;
        $user->save();

        return ['success' => true];
    }

    /**
     * The user wants to join a new team
     */
    public function join (Request $request)
    {
        $request->validate([
            'identifier' => 'required|min:3|max:100'
        ]);

        $user = Auth::user();

        if ($team = Team::where('identifier', $request->identifier)->first())
        {
            // Check the user is not already in the team
            foreach ($user->teams as $t)
            {
                if ($team->id === $t->id) return ['success' => false, 'msg' => 'already-joined'];
            }

            // Have the user join this team
            $user->teams()->attach($team);

            return ['success' => true];
        }

        return ['success' => false, 'msg' => 'not-found'];
    }

    /**
     * Return the types of available teams
     */
    public function types ()
    {
        return TeamType::select('id', 'team')->get();
    }
}
