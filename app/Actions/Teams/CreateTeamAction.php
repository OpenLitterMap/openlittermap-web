<?php

namespace App\Actions\Teams;

use App\Events\TeamCreated;
use App\Models\Teams\Team;
use App\Models\User\User;

class CreateTeamAction
{
    public function run(User $user, array $data): Team
    {
        /** @var Team $team */
        $team = Team::create([
            'created_by' => $user->id,
            'name' => $data['name'],
            'type_id' => $data['team_type'],
            'leader' => $user->id,
            'identifier' => $data['identifier']
        ]);

        $this->addUserAsTeamMember($user, $team);

        event(new TeamCreated($team->name));

        return $team;
    }

    /**
     * @param User $user
     * @param Team $team
     * @return void
     */
    protected function addUserAsTeamMember(User $user, Team $team): void
    {
        $user->teams()->attach($team);

        $user->active_team = $team->id;
        $user->remaining_teams--;
        $user->save();
    }
}
