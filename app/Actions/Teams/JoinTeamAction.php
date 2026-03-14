<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;
use App\Models\Users\User;

class JoinTeamAction
{
    public function run(User $user, Team $team)
    {
        $user->teams()->attach($team);

        $this->setAsActiveTeamIfNull($user, $team);

        $team->members++;
        $team->save();
    }

    /**
     * @param User $user
     * @param Team $team
     * @return void
     */
    protected function setAsActiveTeamIfNull(User $user, Team $team): void
    {
        if (is_null($user->active_team)) {
            $user->active_team = $team->id;
            $user->save();
        }
    }
}
