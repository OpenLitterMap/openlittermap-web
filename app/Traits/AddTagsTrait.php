<?php

namespace App\Traits;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     * @param array $tags
     * @param int $photoId
     */
    public function addTags ($tags, $photoId)
    {
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);

        // Delete the old tags
        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deletedTags = $deleteTagsAction->run($photo);

        // Add the new tags
        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $litterTotals = $addTagsAction->run($photo, $tags);

        // Decrement the XP since old tags no longer exist
        $xpDifference = $litterTotals['all'] - $deletedTags['all'];

        $user->xp += $xpDifference;
        $user->xp = max(0, $user->xp);
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $litterTotals['litter'];
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();

        // Update the Leaderboards
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, $xpDifference);
    }
}
