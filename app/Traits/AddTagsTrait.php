<?php

namespace App\Traits;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\ClearTagsOfPhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     */
    public function addTags ($tags, $photo_id)
    {
        $photo = Photo::find($photo_id);
        $user = User::find($photo->user_id);

        /** @var ClearTagsOfPhotoAction $clearTagsAction */
        $clearTagsAction = app(ClearTagsOfPhotoAction::class);
        $totalDeletedTags = $clearTagsAction->run($photo);

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $totalLitter = $addTagsAction->run($photo, $tags);

        $user->xp -= $totalDeletedTags; // Decrement the XP since old tags no longer exist
        $user->xp += $totalLitter; // we are duplicating this if we are updating tags....
        $user->xp = max(0, $user->xp);
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $totalLitter;
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);
    }
}
