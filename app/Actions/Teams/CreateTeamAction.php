<?php

namespace App\Actions\Teams;

use App\Events\TeamCreated;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;

class CreateTeamAction
{
    /**
     * @return Team|array Team on success, error array on failure.
     */
    public function run(User $user, array $data): Team|array
    {
        if ($user->remaining_teams <= 0) {
            return ['success' => false, 'msg' => 'max-created'];
        }

        $isSchool = $this->isSchoolType($data['teamType'] ?? null);

        $team = Team::create([
            'name' => $data['name'],
            'identifier' => $data['identifier'],
            'type_id' => $data['teamType'],
            'type_name' => TeamType::find($data['teamType'])?->team ?? '',
            'leader' => $user->id,
            'created_by' => $user->id,

            // School-specific fields
            'safeguarding' => $isSchool,
            'school_roll_number' => $isSchool ? ($data['school_roll_number'] ?? null) : null,
            'contact_email' => $isSchool ? ($data['contact_email'] ?? null) : null,
            'county' => $isSchool ? ($data['county'] ?? null) : null,
            'academic_year' => $isSchool ? ($data['academic_year'] ?? null) : null,
            'class_group' => $isSchool ? ($data['class_group'] ?? null) : null,
        ]);

        // Leader auto-joins the team
        $user->teams()->attach($team->id);

        // Set as active team
        $user->active_team = $team->id;
        $user->save();

        // Decrement remaining teams
        if ($user->remaining_teams > 0) {
            $user->decrement('remaining_teams');
        }

        $team = $team->fresh();

        event(new TeamCreated($team));

        return $team;
    }

    protected function isSchoolType(?int $typeId): bool
    {
        if (! $typeId) return false;

        return TeamType::where('id', $typeId)->where('team', 'school')->exists();
    }
}
