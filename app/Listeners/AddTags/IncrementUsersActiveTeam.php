<?php

namespace App\Listeners\AddTags;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class IncrementUsersActiveTeam
{
    /**
     * If the user has an active team,
     *
     * Increment the total values for the users active team.
     *
     * Todo: move this to redis
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        // Update the Team
        if ($user->active_team)
        {
            $user->team->total_litter += $event->total_litter_all_categories;
            $user->team->total_images++;
            $user->team->save();
        }

        // Update the users contribution to this team
        DB::table('team_user')->where([
            'team_id' => $user->active_team,
            'user_id' => $user->id
        ])->increment('total_photos');

        DB::table('team_user')->where([
            'team_id' => $user->active_team,
            'user_id' => $user->id
        ])->increment('total_litter', $event->total_litter_all_categories);
    }
}
