<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;

class ToggleLeaderboardVisibilityAction
{

    public function run(Team $team): Team
    {
        $team->leaderboards = !$team->leaderboards;
        $team->save();

        return $team;
    }
}
