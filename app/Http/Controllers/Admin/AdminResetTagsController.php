<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CalculateTagsDifferenceAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

class AdminResetTagsController extends Controller
{
    /**
     * Apply IsAdmin middleware to all of these routes
     *
     * @param DeleteTagsFromPhotoAction $deleteTagsAction
     * @param UpdateLeaderboardsForLocationAction $updateLeaderboardsAction
     * @param DeletePhotoAction $deletePhotoAction
     * @param CalculateTagsDifferenceAction $calculateTagsDiffAction
     */
    public function __construct (
        DeleteTagsFromPhotoAction $deleteTagsAction,
        UpdateLeaderboardsForLocationAction $updateLeaderboardsAction,
        DeletePhotoAction $deletePhotoAction,
        CalculateTagsDifferenceAction $calculateTagsDiffAction
    )
    {
        $this->middleware('admin');

        $this->deleteTagsAction = $deleteTagsAction;
        $this->updateLeaderboardsAction = $updateLeaderboardsAction;
        $this->calculateTagsDiffAction = $calculateTagsDiffAction;
    }

    /**
     * Incorrect image - reset verification to 0
     *
     *  - Reset user_verification_count to 0
     */
    public function __invoke (Request $request)
    {
        $photo = Photo::findOrFail($request->photoId);

        // This function should only be run when the image is not verified already
        // Only superadmins should be able to reset tags on a verified photo
        if ($photo->verification < 2)
        {
            $photo->verification = 0;
            $photo->verified = 0;
            $photo->total_litter = 0;
            $photo->result_string = null;
            $photo->save();

            $user = User::find($photo->user_id);

            if ($photo->tags())
            {
                $tagUpdates = $this->calculateTagsDiffAction->run(
                    $photo->tags(),
                    [],
                    $photo->customTags->pluck('tag')->toArray(),
                    []
                );
                $this->deleteTagsAction->run($photo);

                $user->xp = max(0, $user->xp - $tagUpdates['removedUserXp']);

                $this->updateLeaderboardsAction->run($photo, $user->id, - $tagUpdates['removedUserXp']);

                // Todo - Add test to show xp is decrementing
                if (Redis::hexists("user_verification_count", $user->id))
                {
                    $negativeNumber = ($tagUpdates['removedUserXp'] * -1);

                    Redis::hincrby("user_verification_count", $user->id, $negativeNumber);
                }

                logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);
            }

            // Reset verification count. 100 in a row needed to unlock 1 Littercoin and become verified
            $user->count_correctly_verified = 0;
            $user->save();

            rewardXpToAdmin();
        }

        return [
            'success' => true
        ];
    }
}
