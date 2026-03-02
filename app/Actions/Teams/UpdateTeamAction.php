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

        if (array_key_exists('participant_sessions_enabled', $data)) {
            $updateData['participant_sessions_enabled'] = $data['participant_sessions_enabled'];
        }

        $team->update($updateData);

        return $team;
    }
}
