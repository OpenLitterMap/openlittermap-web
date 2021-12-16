<?php

namespace App\Http\Controllers\API;

use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{

    /**
     * The user wants to join a team
     *
     * @return array
     */
    public function join(JoinTeamRequest $request, JoinTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        /** @var Team $team */
        $team = Team::whereIdentifier($request->identifier)->first();

        // Check the user is not already in the team
        if ($user->teams()->whereTeamId($team->id)->exists()) {
            abort(403, 'You\'re already in this team!');
        }

        $action->run($user, $team);

        return [
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ];
    }


    /**
     * The user wants to leave a team
     *
     * @return array
     */
    public function leave (LeaveTeamRequest $request, LeaveTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->teams()->whereTeamId($request->team_id)->exists()) {
            abort(403, 'You are not part of this team!');
        }

        if ($team->users()->count() <= 1) {
            abort(403, 'You are the only member of this team!');
        }

        $action->run($user, $team);

        return [
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ];
    }

}
