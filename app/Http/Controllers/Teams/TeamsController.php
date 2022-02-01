<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeamAction;
use App\Actions\Teams\DownloadTeamDataAction;
use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Actions\Teams\ListTeamMembersAction;
use App\Actions\Teams\SetActiveTeamAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class TeamsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Change the users currently active team
     *
     * @return array
     */
    public function active (Request $request, SetActiveTeamAction $action)
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->teams()->where('team_id', $request->team_id)->exists()) {
            return ['success' => false];
        }

        $action->run($user, $request->team_id);

        return ['success' => true, 'team' => $team];
    }

    /**
     * Clears the user's active team
     *
     * @return array
     */
    public function inactivateTeam()
    {
        /** @var User $user */
        $user = auth()->user();

        $user->active_team = null;
        $user->save();

        return ['success' => true];
    }

    /**
     * The user wants to create a new team
     *
     * @return array
     */
    public function create (CreateTeamRequest $request, CreateTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->remaining_teams === 0) return ['success' => false, 'msg' => 'max-created'];

        $team = $action->run($user, $request->all());

        return ['success' => true, 'team' => $team];
    }

    /**
     * The user wants to update a team
     *
     * @param UpdateTeamRequest $request
     * @param UpdateTeamAction $action
     * @param Team $team
     * @return array
     */
    public function update (UpdateTeamRequest $request, UpdateTeamAction $action, Team $team)
    {
        if (auth()->id() != $team->leader) {
            abort(403, 'You are not the team leader!');
        }

       $team = $action->run($team, $request->all());

        return ['success' => true, 'team' => $team];
    }

    /**
     * The user wants to download data from a specific team
     */
    public function download (Request $request, DownloadTeamDataAction $action): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::query()->findOrFail($request->team_id);

        if (!$user->teams()->whereTeamId($request->team_id)->exists()) {
            return ['success' => false, 'message' => 'not-a-member'];
        }

        $action->run($user, $team);

        return ['success' => true];
    }

    /**
     * The user wants to join a team
     *
     * @return array
     */
    public function join (JoinTeamRequest $request, JoinTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::whereIdentifier($request->identifier)->first();

        // Check the user is not already in the team
        if ($user->teams()->whereTeamId($team->id)->exists())
        {
            return ['success' => false, 'msg' => 'already-joined'];
        }

       $action->run($user, $team);

        return [
            'success' => true,
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
        $user = auth()->user();
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
            'success' => true,
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ];
    }

    /**
     * Array of teams the user has joined
     */
    public function joined ()
    {
        return Auth::user()->teams;
    }

    /**
     * Get paginated members for a team_id
     */
    public function members (ListTeamMembersAction $action): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::query()->findOrFail(request()->team_id);

        if (!$user->teams()->where('team_id', request()->team_id)->exists()) {
            return ['success' => false, 'message' => 'not-a-member'];
        }

        $totalMembers = $team->users->count();

        $result = $action->run($team);

        return [
            'success' => true,
            'total_members' => $totalMembers,
            'result' => $result
        ];
    }

    /**
     * Return the types of available teams
     */
    public function types (): Collection
    {
        return TeamType::select('id', 'team')->get();
    }
}
