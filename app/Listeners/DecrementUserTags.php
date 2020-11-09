<?php

namespace App\Listeners;

use App\Events\ResetTagsCountAdmin;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DecrementUserTags
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
     * @param  ResetTagsCountAdmin  $event
     * @return void
     */
    public function handle (ResetTagsCountAdmin $event)
    {
        $photo = Photo::find($event->photo_id);

        if ($user = User::find($photo->user_id))
        {

        }
    }
}
