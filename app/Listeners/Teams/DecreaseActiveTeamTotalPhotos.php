<?php

namespace App\Listeners\Teams;

use App\Events\ImageDeleted;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class DecreaseActiveTeamTotalPhotos implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param ImageDeleted $event
     * @return void
     */
    public function handle(ImageDeleted $event)
    {
        if (!$event->user->active_team) {
            return;
        }

        $event->user->team->update([
            'total_images' => DB::raw('ifnull(total_images, 0) - 1')
        ]);

        // Update the user's contribution to this team
        $event->user->teams()->updateExistingPivot($event->user->active_team, [
            'total_photos' => DB::raw('ifnull(total_photos, 0) - 1')
        ]);
    }
}
