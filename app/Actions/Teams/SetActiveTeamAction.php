<?php

namespace App\Actions\Teams;

use App\Models\User\User;

class SetActiveTeamAction
{
    public function run(User $user, int $teamId)
    {
        $user->active_team = $teamId;
        $user->save();
    }
}
