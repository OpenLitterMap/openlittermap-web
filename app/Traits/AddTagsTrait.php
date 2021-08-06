<?php

namespace App\Traits;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     *
     * @var DeleteTagsFromPhotoAction $deleteTagsAction
     * @var AddTagsToPhotoAction $addTagsAction
     * @var UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction
     */
    public function addTags ($tags, $photoId)
    {
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);

        // Delete the old tags
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo);

        // Add the new tags
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

        // Update the Leaderboards
        $updateLeaderboardsAction = app(UpdateLeaderboardsFromPhotoAction::class);
        $updateLeaderboardsAction->run($user, $photo);
    }
}
