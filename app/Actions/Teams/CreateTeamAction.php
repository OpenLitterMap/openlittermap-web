<?php

namespace App\Actions\Teams;

use App\Events\TeamCreated;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateTeamAction
{
    /**
     * @return Team|array Team on success, error array on failure.
     */
    public function run(User $user, array $data, ?UploadedFile $logo = null): Team|array
    {
        if ($user->remaining_teams <= 0) {
            return ['success' => false, 'msg' => 'max-created'];
        }

        $isSchool = $this->isSchoolType($data['teamType'] ?? null);

        $logoPath = null;
        if ($isSchool && $logo) {
            $logoPath = $logo->store('school-logos', 'logos');
        }

        $team = Team::create([
            'name' => $data['name'],
            'identifier' => $data['identifier'],
            'type_id' => $data['teamType'],
            'type_name' => TeamType::find($data['teamType'])?->team ?? '',
            'leader' => $user->id,
            'created_by' => $user->id,

            // School-specific fields
            'safeguarding' => $isSchool,
            'contact_email' => $isSchool ? ($data['contact_email'] ?? null) : null,
            'county' => $isSchool ? ($data['county'] ?? null) : null,
            'academic_year' => $isSchool ? ($data['academic_year'] ?? null) : null,
            'class_group' => $isSchool ? ($data['class_group'] ?? null) : null,
            'logo' => $logoPath,
            'max_participants' => $isSchool ? ($data['max_participants'] ?? null) : null,
            'participant_sessions_enabled' => $isSchool ? (bool) ($data['participant_sessions_enabled'] ?? false) : false,
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
