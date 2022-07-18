<?php

namespace App\Http\Controllers\Admin;

use App\Events\Littercoin\LittercoinMined;
use App\Models\Photo;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

class VerifyImageWithTagsController extends Controller
{
    /**
     * Admin
     *
     * Verify the tags of an image are correct
     * - Update Photo as verified
     *
     * - Reward xp to the Admin
     *
     * - Increase user verification count to earn Littercoin and become a trusted user
     *
     * - Emit event that will:
     *   - update location data
     *   - compile photo.result_string
     *   - increment team data
     *   - update user time-series
     *   - update user categories
     *
     * Todo: After Littercoin is sent, email the user, encouraging them to continue, share information about the app
     */
    public function __invoke (Request $request)
    {
        // Update the photo as verified
        $photo = Photo::findOrFail($request->photoId);
        $photo->verified = 2;
        $photo->verification = 1;
        $photo->save();

        // Reward xp to the Admin
        $this->rewardXpToAdmin();

        // Log the action
        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        // Get a count of the user_verification_count and number of photos verified
        $counts = $this->increaseUsersVerificationCount($photo->user_id);

        // Emit an event to update locations, charts, team and other non-essential data that can be queued
        event (new TagsVerifiedByAdmin($photo->id));

        return [
            'success' => true,
            'userVerificationCount' => $counts['verificationCount'],
            'photosVerified' => $counts['photosVerified']
        ];
    }

    /**
     * Increase the users verification count
     *
     * If it reaches 10+, we update the user as verified
     *
     * @param int $userId => uploaded the image that is being verified
     * @return array => verificationCount, photosVerified
     */
    private function increaseUsersVerificationCount (int $userId): array
    {
        $user = User::find($userId);

        $verificationCount = Redis::hincrby("user_verification_count", $user->id, 1);

        if ($verificationCount > 100)
        {
            if ($user->verification_required)
            {
                $user->verification_required = false;
            }

            // Get any photos that can be verified
            $photos = Photo::where([
                'user_id' => $user->id,
                'verification' => 0.1
            ])->get();

            foreach ($photos as $photo)
            {
                $photo->verification = 1;
                $photo->verified = 2;
                $photo->save();

                $verificationCount++;

                if ($verificationCount === 100)
                {
                    // Move this to a new littercoin table
                    // add the photo_id
                    $user->littercoin_allowance += 1;
                    event (new LittercoinMined($user->id, '100-images-verified'));
                }

                event(new TagsVerifiedByAdmin($photo->id));
            }

            $user->save();

            // Since the user is now verified, we can delete their ID from redis.
            Redis::hdel("user_verification_count", $user->id);
        }

        return [
            'verificationCount' => $verificationCount,
            'photosVerified' => $photos->count ?? 0
        ];
    }
}
