<?php

namespace App\Http\Controllers\Admin;

use App\Events\Littercoin\LittercoinMined;
use App\Mail\Admin\AccountUpgraded;
use App\Models\Littercoin;
use App\Models\Photo;
use App\Events\TagsVerifiedByAdmin;
use App\Http\Controllers\Controller;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
    public function __invoke (Request $request): array
    {
        // Update the photo as verified
        $photo = Photo::findOrFail($request->photoId);
        $photo->verified = 2;
        $photo->verification = 1;
        $photo->save();

        // Reward xp to the Admin
        rewardXpToAdmin();

        // Get a count of the user_verification_count and number of photos verified
        $counts = $this->increaseUsersVerificationCount($photo->user_id);

        // Emit an event to update locations, charts, team and other non-essential data that can be queued
        event (new TagsVerifiedByAdmin($photo->id));

        // Log the action
        logAdminAction($photo, 'verify-tags');

        return [
            'success' => true,
            'userVerificationCount' => $counts['verificationCount'],
            'photosVerified' => $counts['photosVerified']
        ];
    }

    /**
     * Increase the users verification count
     *
     * When the user reaches a score of 100+
     *   - they earn their first littercoin
     *   - we upgrade their account as verification_required = false
     *   - we send an email congratulating them
     *
     * @param int $userId => The user who uploaded the image that is being verified
     * @return array => verificationCount, photosVerified
     */
    private function increaseUsersVerificationCount (int $userId): array
    {
        $user = User::find($userId);

        $verificationCount = Redis::hincrby("user_verification_count", $user->id, 1);

        $photosCount = 0;

        if ($verificationCount >= 100)
        {
            if ($user->verification_required)
            {
                $user->verification_required = false;
                $user->save();

                // First Littercoin notification
                Mail::to($user->email)->send(new AccountUpgraded($user));
            }

            // Verify any remaining photos
            $photos = Photo::where([
                'user_id' => $user->id,
                'verification' => 0.1
            ])->get();

            if ($photos)
            {
                $photosCount = $photos->count();

                foreach ($photos as $photo)
                {
                    $photo->verification = 1;
                    $photo->verified = 2;
                    $photo->save();

                    event(new TagsVerifiedByAdmin($photo->id));
                }

                $user->save();
            }

            // Since the user is now verified, we can delete their ID from redis.
            Redis::hdel("user_verification_count", $user->id);
        }

        return [
            'verificationCount' => $verificationCount,
            'photosVerified' => $photosCount
        ];
    }
}
