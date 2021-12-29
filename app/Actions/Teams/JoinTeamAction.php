<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;
use App\Models\User\User;

class JoinTeamAction
{
    public function run(User $user, Team $team)
    {
        // Have the user join this team and restore their contributions
        $userPhotosOnThisTeam = $user->photos()->whereTeamId($team->id);

        $user->teams()->attach($team, [
            'total_photos' => $userPhotosOnThisTeam->count(),
            'total_litter' => $userPhotosOnThisTeam->sum('total_litter')
        ]);

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
