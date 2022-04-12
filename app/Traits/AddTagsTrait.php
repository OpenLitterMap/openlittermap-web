<?php

namespace App\Traits;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Models\Photo;
use App\Models\User\User;

trait AddTagsTrait
{
    /**
     * Add or Update tags on an image
     * @param array $tags
     * @param array $customTags
     * @param int $photoId
     */
    public function addTags ($tags, $customTags, $photoId)
    {
        /** @var Photo $photo */
        $photo = Photo::find($photoId);
        /** @var User $photo */
        $user = User::find($photo->user_id);

        $tagUpdates = $this->calculateTagsDiffAction->run(
            $photo->compiled_tags,
            $tags,
            $photo->customTags->pluck('tag')->toArray(),
            $customTags
        );

        // Delete the old tags
        /** @var DeleteTagsFromPhotoAction $deleteTagsAction */
        $deleteTagsAction = app(DeleteTagsFromPhotoAction::class);
        $deleteTagsAction->run($photo);

        // Add the new tags
        /** @var AddTagsToPhotoAction $addTagsAction */
        $addTagsAction = app(AddTagsToPhotoAction::class);
        $tagTotals = $addTagsAction->run($photo, convert_tags($tags));

        // Add the new custom tags
        /** @var AddCustomTagsToPhotoAction $addCustomTagsAction */
        $addCustomTagsAction = app(AddCustomTagsToPhotoAction::class);
        $addCustomTagsAction->run($photo, $customTags);

        // Decrement the XP since old tags no longer exist
        $user->xp -= $tagUpdates['removedUserXp'];
        $user->xp = max(0, $user->xp);
        $user->save();

        // photo->verified_by ;
        $photo->total_litter = $tagTotals;
        $photo->result_string = null; // Updated on PhotoVerifiedByAdmin only. Must be reset if we are applying new tags.
        $photo->save();

        // Update the Leaderboards
        $updateLeaderboardsAction = app(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboardsAction->run($photo, $user->id, -$tagUpdates['removedUserXp']);

        return $tagUpdates;
    }
}
