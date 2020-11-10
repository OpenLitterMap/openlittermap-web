<?php

namespace App\Listeners\AddTags;

use App\Models\User\User;
use App\Models\Photo;
use App\Events\PhotoVerifiedByAdmin;
use App\Events\DynamicUpdate;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateUsers
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
     * @param  DynamicUpdate  $event
     * @return void
     */
    public function handle (PhotoVerifiedByAdmin $event)
    {
        $photo = Photo::find($event->photoId);
        $user = User::find($photo->user_id);

        if ($user->count_correctly_verified == 100)
        {
            $user->littercoin_allowance += 1;
            $user->count_correctly_verified = 0;
        }

        else $user->count_correctly_verified += 1;

        // TODO :
        // Update user.total_column_for_each_category_tagged_on_this_photo

        $user->total_verified += 1;
        $user->total_verified_litter += $photo->total_litter;

        $user->save();
    }
}
