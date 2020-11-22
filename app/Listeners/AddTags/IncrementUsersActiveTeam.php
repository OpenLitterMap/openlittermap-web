<?php

namespace App\Listeners\AddTags;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IncrementUsersActiveTeam
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $photo = Photo::find($event->photo_id);

        $user = User::find($photo->user_id);

        if ($user->active_team)
        {
            $total_count = 0;

            // this is going to be the same for each location
            // move this to the event and pass in the data per listener
            foreach ($photo->categories() as $category)
            {
                if ($photo->$category)
                {
                    $total_count += $photo->$category->total();
                }
            }

            // update user.team.total_litter
            // increment user.team.total_photos
        }
    }
}
