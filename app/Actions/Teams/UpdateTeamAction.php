<?php

namespace App\Actions\Teams;

use App\Models\Teams\Team;

class UpdateTeamAction
{

    public function run(Team $team, array $data): Team
    {
        $updateData = [
            'name' => $data['name'],
            'identifier' => $data['identifier'],
        ];

        if (array_key_exists('safeguarding', $data)) {
            $updateData['safeguarding'] = $data['safeguarding'];
        }

        $team->update($updateData);

        return $team;
    }
}
