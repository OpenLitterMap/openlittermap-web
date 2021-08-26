<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveTeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(Request $request)
    {
        $request->validate([
            'teamId' => 'required|exists:teams,id'
        ]);

        /** @var User $user */
        $user = auth()->user();
        /** @var Team $team */
        $team = Team::find($request->teamId);

        if (!$user->teams()->whereTeamId($request->teamId)->exists()) {
            abort(403, 'You are not part of this team!');
        }

        if ($team->users()->count() <= 1) {
            abort(403, 'You are the only member of this team!');
        }

        $this->assignTeamLeader($user, $team);

        $this->assignActiveTeam($user, $team);

        $user->teams()->detach($team);

        $team->members--;
        $team->save();

        return ['success' => true, 'team_id' => $team->id];
    }

    /**
     * If the user is the leader of the team they're leaving
     * assign a new team member as leader
     *
     * @param User $user
     * @param Team $team
     */
    protected function assignTeamLeader(User $user, Team $team): void
    {
        if ($team->leader != $user->id) {
            return;
        }

        $nextTeamMember = DB::table('team_user')
            ->where([
                'team_id' => $team->id,
                ['user_id', '<>', $user->id]
            ])
            ->first()
            ->user_id;
        $team->leader = $nextTeamMember;
        $team->save();
    }

    /**
     * If the user is leaving their active team
     * assign a new active team to them
     * if they are part of another team
     *
     * @param User $user
     * @param Team $team
     */
    protected function assignActiveTeam(User $user, Team $team): void
    {
        if ($user->active_team != $team->id) {
            return;
        }

        $nextTeam = DB::table('team_user')
            ->where([
                'user_id' => $user->id,
                ['team_id', '<>', $team->id]
            ])
            ->first();

        $user->active_team = $nextTeam->team_id ?? null;
        $user->save();
    }
}
