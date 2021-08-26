<?php

namespace App\Listeners\Teams;

use App\Events\ImageDeleted;
use App\Models\Teams\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class DecreaseTeamTotalPhotos implements ShouldQueue
{
    /**
     * If the user has an active team when they upload a photo,
     * that photo should belong to that team. However, if they change their active team,
     * the effect (decreasing total photos) should happen only on the old active team that the photo belongs to,
     * that's why we're using $event->teamId and not $user->active_team to fetch the team.
     *
     * Decrement the total photos for the photo's team.
     *
     * @param ImageDeleted $event
     * @return void
     */
    public function handle(ImageDeleted $event)
    {
        if (!$event->teamId) {
            return;
        }

        Team::whereId($event->teamId)->update([
            'total_images' => DB::raw('ifnull(total_images, 0) - 1')
        ]);

        // Update the user's contribution to this team
        $event->user->teams()->updateExistingPivot($event->teamId, [
            'total_photos' => DB::raw('ifnull(total_photos, 0) - 1')
        ]);
    }
}
