<?php

namespace App\Listeners;

use App\Models\Photo;
use App\Models\User\User;
use App\Events\PhotoVerifiedByAdmin;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateLitterCoin
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
     * @param  PhotoVerifiedByUser  $event
     * @return void
     */
    public function handle(PhotoVerifiedByAdmin $event)
    {
        $photoId = $event->photoId;
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);
        if($user->eth_wallet) {

        }
    }
}
