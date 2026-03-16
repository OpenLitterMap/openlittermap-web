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

        // Participant sessions are school-only
        if (array_key_exists('participant_sessions_enabled', $data) && $team->isSchool()) {
            $updateData['participant_sessions_enabled'] = $data['participant_sessions_enabled'];
        }

        // School teams must never be trusted (bypasses teacher approval)
        if ($team->isSchool()) {
            $updateData['is_trusted'] = false;
        }

        $team->update($updateData);

        return $team;
    }
}
