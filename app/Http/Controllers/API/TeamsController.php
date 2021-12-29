<?php

namespace App\Http\Controllers\API;

use App\Actions\Teams\CreateTeamAction;
use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except('types');
    }

    /**
     * Array of teams the user has joined
     *
     * @return array<Team>
     */
    public function list()
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user->teams;
    }

    /**
     * The user wants to create a new team
     *
     * @param CreateTeamRequest $request
     * @param CreateTeamAction $action
     * @return array
     */
    public function create(CreateTeamRequest $request, CreateTeamAction $action): array
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        if ($user->remaining_teams === 0) {
            abort(403, 'You have created your maximum number of teams!');
        }

        $team = $action->run($user, $request->all());

        return ['team' => $team];
    }

    /**
     * The user wants to update a team
     *
     * @param UpdateTeamRequest $request
     * @param UpdateTeamAction $action
     * @param Team $team
     * @return array
     */
    public function update(UpdateTeamRequest $request, UpdateTeamAction $action, Team $team): array
    {
        if (Auth::guard('api')->id() != $team->leader) {
            abort(403, 'You are not the team leader!');
        }

        $team = $action->run($team, $request->all());

        return ['team' => $team];
    }

    /**
     * The user wants to join a team
     *
     * @param JoinTeamRequest $request
     * @param JoinTeamAction $action
     * @return array
     */
    public function join(JoinTeamRequest $request, JoinTeamAction $action): array
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
     * @param LeaveTeamRequest $request
     * @param LeaveTeamAction $action
     * @return array
     */
    public function leave(LeaveTeamRequest $request, LeaveTeamAction $action): array
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

    /**
     * Return the types of available teams
     *
     * @return Collection
     */
    public function types()
    {
        return TeamType::select('id', 'team')->get();
    }
}
