<?php

namespace App\Listeners\Teams;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class IncreaseTeamTotalLitter implements ShouldQueue
{
    /**
     * If the user had an active team when they uploaded & tagged the photo,
     * that photo should belong to that team. However, if they're not verified users,
     * it might take some time until the tags are verified by an admin.
     * During this time the user might change their active team,
     * that's why we're using $photo->team and not $user->team to fetch the team
     * this photo belongs to.
     *
     * Increment the total litter for the photo's team.
     */
    public function handle(TagsVerifiedByAdmin $event)
    {
        $photo = Photo::find($event->photo_id);

        if (!$photo->team) {
            return;
        }

        // Update the Team
        $photo->team->total_litter += $event->total_litter_all_categories;
        $photo->team->save();

        DB::table('team_user')
            ->where([
                'team_id' => $photo->team_id,
                'user_id' => $photo->user_id
            ])
            ->update([
                'total_litter' => DB::raw('ifnull(total_litter, 0) + ' . $event->total_litter_all_categories),
                'updated_at' => now()
            ]);
    }
}
