<?php

namespace App\Listeners\Teams;

use App\Events\ImageUploaded;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class IncreaseActiveTeamTotalPhotos implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  ImageUploaded  $event
     * @return void
     */
    public function handle(ImageUploaded $event)
    {
        $user = User::find($event->userId);

        if (!$user->active_team) {
            return;
        }

        $user->team->update([
            'total_images' => DB::raw('ifnull(total_images, 0) + 1')
        ]);

        // Update the user's contribution to this team
        $user->teams()->updateExistingPivot($user->active_team, [
            'total_photos' => DB::raw('ifnull(total_photos, 0) + 1')
        ]);
    }
}
