<?php

namespace App\Http\Controllers\API;

use App\Actions\Teams\CreateTeamAction;
use App\Actions\Teams\DownloadTeamDataAction;
use App\Actions\Teams\JoinTeamAction;
use App\Actions\Teams\LeaveTeamAction;
use App\Actions\Teams\ListTeamMembersAction;
use App\Actions\Teams\SetActiveTeamAction;
use App\Actions\Teams\UpdateTeamAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\CreateTeamRequest;
use App\Http\Requests\Teams\JoinTeamRequest;
use App\Http\Requests\Teams\LeaveTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api')->except('types');
    }

    /**
     * Array of teams the user has joined
     *
     * @return array
     */
    public function list()
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->success(['teams' => $user->teams]);
    }

    /**
     * The user wants to create a new team
     *
     * @return array
     */
    public function create(CreateTeamRequest $request, CreateTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->remaining_teams === 0) {
            return $this->fail('max-teams-created');
        }

        $team = $action->run($user, $request->all());

        return $this->success(['team' => $team]);
    }

    /**
     * The user wants to update a team
     *
     * @return array
     */
    public function update(UpdateTeamRequest $request, UpdateTeamAction $action, Team $team)
    {
        if (Auth::id() != $team->leader) {
            return $this->fail('member-not-allowed');
        }

        $team = $action->run($team, $request->all());

        return $this->success(['team' => $team]);
    }

    /**
     * The user wants to join a team
     *
     * @return array
     */
    public function join(JoinTeamRequest $request, JoinTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::whereIdentifier($request->identifier)->first();

        // Check the user is not already in the team
        if ($user->isMemberOfTeam($team->id)) {
            return $this->fail('already-a-member');
        }

        $action->run($user, $team);

        return $this->success([
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ]);
    }

    /**
     * The user wants to leave a team
     *
     * @return array
     */
    public function leave(LeaveTeamRequest $request, LeaveTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->isMemberOfTeam($request->team_id)) {
            return $this->fail('not-a-member');
        }

        if ($team->users()->count() <= 1) {
            return $this->fail('you-are-last-member');
        }

        $action->run($user, $team);

        return $this->success([
            'team' => $team->fresh(),
            'activeTeam' => $user->fresh()->team()->first()
        ]);
    }

    /**
     * Sets the users currently active team
     */
    public function setActiveTeam(Request $request, SetActiveTeamAction $action): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$team) {
            return $this->fail('team-not-found');
        }

        if (!$user->isMemberOfTeam($request->team_id)) {
            return $this->fail('not-a-member');
        }

        $action->run($user, $request->team_id);

        return $this->success(['team' => $team]);
    }

    /**
     * Clears the user's active team
     */
    public function inactivateTeams(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $user->active_team = null;
        $user->save();

        return $this->success();
    }

    /**
     * Get paginated members for a team_id
     */
    public function members (ListTeamMembersAction $action): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::query()->find(request()->team_id);

        if (!$team) {
            return $this->fail('team-not-found');
        }

        if (!$user->isMemberOfTeam(request()->team_id)) {
            return $this->fail('not-a-member');
        }

        $result = $action->run($team);

        return $this->success(['result' => $result]);
    }

    /**
     * The user wants to download data from a specific team
     */
    public function download (Request $request, DownloadTeamDataAction $action): array
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::query()->find($request->team_id);

        if (!$team) {
            return $this->fail('team-not-found');
        }

        if (!$user->isMemberOfTeam($request->team_id)) {
            return $this->fail('not-a-member');
        }

        $action->run($user, $team);

        return $this->success();
    }

    /**
     * Return the types of available teams
     *
     * @return array
     */
    public function types()
    {
        return $this->success([
            'types' => TeamType::select('id', 'team')->get()
        ]);
    }

    /**
     * Helper to output successful responses
     */
    private function success(array $data = []): array
    {
        return ['success' => true, ...$data];
    }

    /**
     * Helper to output error responses
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
