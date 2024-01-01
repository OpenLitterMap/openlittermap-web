<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class LeaveTeamAction
{
    public function run(User $user, Team $team)
    {
        $this->assignTeamLeader($user, $team);

        $this->assignActiveTeam($user, $team);

        $user->teams()->detach($team);

        $team->members--;
        $team->save();
    }


    /**
     * If the user is the leader of the team they're leaving
     * assign a new team member as leader
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
