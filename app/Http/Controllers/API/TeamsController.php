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
use App\Models\Users\User;
use App\Traits\MasksStudentIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @deprecated
 */
class TeamsController extends Controller
{
    use MasksStudentIdentity;

    /**
     * Array of teams the user has joined
     *
     * @return array
     */
    public function list()
    {
        /** @var User $user */
        $user = Auth::user();

        $teams = $user->teams()
            ->withPhotoStats()
            ->get()
            ->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'identifier' => $team->identifier,
                'type_name' => $team->type_name,
                'total_members' => $team->members,
                'total_tags' => (int) $team->total_tags,
                'total_photos' => (int) $team->total_photos,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ]);

        return $this->success(['teams' => $teams]);
    }

    /**
     * The user wants to create a new team
     *
     * @param CreateTeamRequest $request
     * @param CreateTeamAction $action
     * @return array
     */
    public function create(CreateTeamRequest $request, CreateTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();

        $result = $action->run($user, $request->all(), $request->file('logo'));

        if (is_array($result)) {
            return $result;
        }

        return $this->success(['team' => $result]);
    }

    /**
     * The user wants to update a team
     *
     * @param UpdateTeamRequest $request
     * @param UpdateTeamAction $action
     * @param Team $team
     * @return array
     */
    public function update(UpdateTeamRequest $request, UpdateTeamAction $action, Team $team)
    {
        if (Auth::id() != $team->leader) {
            return response()->json(['success' => false, 'message' => 'member-not-allowed'], 403);
        }

        $team = $action->run($team, $request->all());

        return $this->success(['team' => $team]);
    }

    /**
     * The user wants to join a team
     *
     * @param JoinTeamRequest $request
     * @param JoinTeamAction $action
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
            return ['success' => false, 'msg' => 'already-joined'];
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
     * @param LeaveTeamRequest $request
     * @param LeaveTeamAction $action
     * @return array
     */
    public function leave(LeaveTeamRequest $request, LeaveTeamAction $action)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Team $team */
        $team = Team::find($request->team_id);

        if (!$user->isMemberOfTeam($request->team_id)) {
            return response()->json(['success' => false, 'message' => 'not-a-member'], 403);
        }

        if ($team->users()->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'you-are-last-member'], 403);
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
        $result = $this->applySafeguarding($result, $team, $user);

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
            'types' => TeamType::select('id', 'team')->orderBy('id', 'desc')->get()
        ]);
    }

    /**
     * Helper to output successful responses
     * @param array $data
     * @return array
     */
    private function success(array $data = []): array
    {
        return array_merge(['success' => true], $data);
    }

    /**
     * Helper to output error responses
     *
     * @param string $message
     * @return array
     */
    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
