<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;

class UpdateTeamAction
{

    public function run(Team $team, array $data): Team
    {
        $team->update([
            'name' => $data['name'],
            'identifier' => $data['identifier']
        ]);

        return $team;
    }
}
