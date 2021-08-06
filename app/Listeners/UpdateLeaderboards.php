<?php

namespace App\Listeners;

use App\Actions\Locations\UpdateLeaderboardsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;
use App\Events\PhotoVerifiedByUser;

class UpdateLeaderboards
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
    public function handle(PhotoVerifiedByUser $event)
    {
        // find the user who uploaded the photo
        $photoId = $event->photoId;
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);
        // get their xp
        $user->xp += 1;
        $user->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);
    }
}
