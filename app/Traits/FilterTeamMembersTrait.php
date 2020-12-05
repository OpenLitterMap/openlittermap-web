<?php

namespace App\Traits;

use App\Models\Teams\Team;

trait FilterTeamMembersTrait {

    /**
     * Filter members by team_id
     *
     * todo - add more filters here
     * eg, name, username, total_litter, total_photos, last_upload
     */
    public function filterTeamMembers ($team_id)
    {
         $query = Team::query()->find($team_id);

         return $query;
    }
}
