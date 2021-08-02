<?php

namespace App\Traits;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     */
    public function addTags ($tags, $photoId)
    {
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);

        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo);

        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $tags);

        $user->xp -= $deletedTags['all']; // Decrement the XP since old tags no longer exist
        $user->xp += $litterTotals['all'];
        $user->xp = max(0, $user->xp);
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $litterTotals['litter'];
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();

        /** @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);
    }
}
