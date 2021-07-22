<?php

namespace App\Http\Controllers;

use App\Actions\Photos\ClearTagsOfPhotoAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use Exception;
use Illuminate\Support\Facades\Log;

use App\Models\Photo;
use App\Models\User\User;

use App\Traits\AddTagsTrait;

use Carbon\Carbon;

use Illuminate\Http\Request;

use App\Events\TagsVerifiedByAdmin;

class AdminController extends Controller
{
    use AddTagsTrait;

    /** @var ClearTagsOfPhotoAction */
    protected $clearTagsAction;
    /** @var UpdateLeaderboardsFromPhotoAction */
    protected $updateLeaderboardsAction;
    /** @var DeletePhotoAction */
    protected $deletePhotoAction;

    /**
     * Apply IsAdmin middleware to all of these routes
     */
    public function __construct (
        ClearTagsOfPhotoAction $clearTagsAction,
        UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction,
        DeletePhotoAction $deletePhotoAction
    )
    {
        $this->middleware('admin');

        $this->clearTagsAction = $clearTagsAction;
        $this->updateLeaderboardsAction = $updateLeaderboardsAction;
        $this->deletePhotoAction = $deletePhotoAction;
    }

    public function getUserCount ()
    {
        // $users = User::where([
        //     ['verified', 1],
        //     ['has_uploaded', 1]
        // ])->orderBy('xp', 'desc')->get();

        $users = User::where('verified', 1)
            ->orWhere('name', 'default')
            ->get()
            ->sortBy('created_at');

        $totalUsers = $users->count();

        $users = $users->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });;

        $upm = [];
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        // $itr = 0;
        foreach($users as $index => $monthlyUser)
        {
            // return [$index, $monthlyUser->count()];
            $month = $months[(int)$substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $upm[$month.$year] = $monthlyUser->count(); // Mar-17
            // $total_photos += $monthlyUser->count();
        }
        $upm = json_encode($upm);

        $usersUploaded = User::where('has_uploaded', 1)->get();

        $usersUploaded = $usersUploaded->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });;

        $uupm = [];
        foreach($usersUploaded as $index => $userUploaded)
        {
            // return $userUploaded->count();
            // return [$index, $userUploaded->count(), $userUploaded];
            $month = $months[(int)$substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $uupm[$month.$year] = $userUploaded->count(); // Mar-17
            // $total_photos += $monthlyUser->count();
        }
        $uupm = json_encode($uupm);

        return view('admin.usercount', compact('users', 'totalUsers', 'upm', 'uupm'));
    }

    /**
     * Verify an image, delete the image
     ** todo - fix this with correct AWS permissions
     */
    // public function verify(Request $request) {
    //     $photo = Photo::find($request->photoId);
    //     $filepath = $photo->filename;
    //     unlink(public_path($filepath));
    //     $photo->filename = '/assets/verified.jpg';
    //     $photo->verified = 2;
    //     $photo->verification = 1;
    //     $photo->save();
    //     $user = User::find($photo->user_id);
    //     $user->xp += 1;
    //     $user->save();
    //     event(new TagsVerifiedByAdmin($photo->id));
    // }

    /**
     * The image and the tags are correct
     *
     * Updates Country, State + City table
     */
    public function verifykeepimage (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $photo->verified = 2;
        $photo->verification = 1;
        $photo->save();

        // todo - dispatch via horizon
        event(new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Incorrect image - reset verification to 0
     */
    public function incorrect (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::findOrFail($request->photoId);

        $photo->verification = 0;
        $photo->verified = 0;
        $photo->total_litter = 0;
        $photo->result_string = null;
        $photo->save();

        $totalDeletedTags = $this->clearTagsAction->run($photo);

        $user = $photo->user;
        $user->xp = max(0, $user->xp - $totalDeletedTags);
        $user->count_correctly_verified = 0;
        $user->save();

        $this->updateLeaderboardsAction->run($user, $photo);

        return ['success' => true];
    }

    /**
     * Delete an image and its records
     */
    public function destroy (Request $request)
    {
        $photo = Photo::findOrFail($request->photoId);
        $user = User::find($photo->user_id);

        try {
            $this->deletePhotoAction->run($photo);

            $totalDeletedTags = $this->clearTagsAction->run($photo);

            $photo->delete();

            $user->xp = max(0, $user->xp - $totalDeletedTags - 1); // Subtract 1xp for uploading
            $user->total_images = $user->total_images > 0 ? $user->total_images - 1 : 0;
            $user->save();

            $this->updateLeaderboardsAction->run($user, $photo);
        } catch (Exception $e) {
            Log::info(["Admin delete failed", $e]);
        }

        return redirect()->back();
    }

    /**
      * Update the contents of an Image, Delete the image
     */
    public function updateDelete (Request $request)
    {
        $photo = Photo::find($request->photoId);

        $this->deletePhotoAction->run($photo);

        $photo->filename = '/assets/verified.jpg';

        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();

        $this->addTags($request->categories, $photo->id);

        // todo - horizon
        event(new TagsVerifiedByAdmin($photo->id));
    }


    /**
     * Verify the image
     * Keep the image
     * Image was not correctly inputted! LitterCorrectlyCount = 0.
     *
     * We need to Update the Country, State and City model
     * - remove previous tag counts
     * - add new tag counts
     */
    public function updateTags (Request $request)
    {
        $photo = Photo::find($request->photoId);
        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();

        $user = User::find($photo->user_id);
        $user->count_correctly_verified = 0; // At 100, the user earns a Littercoin
        $user->save();

        // this event is needed if the photo is already verified
        // event (new ResetTagsCountAdmin($photo->id));

        $this->addTags($request->tags, $request->photoId);

        // todo - dispatch event via horizon
        event (new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Get the next image to verify
     */
    public function getImage ()
    {
        if ($photo = Photo::where('verification', 0.1)->first())
        {
            $photo->tags();
        }

        else
        {
            $photo = Photo::where([
                'verification' => 0,
                 ['user_id', '!=', 3233] // dont load freds data
            ])->first();
        }

        // Count photos that have been uploaded, but not tagged or submitted for verification
        $photosNotProcessed = Photo::where([
                ['verification', 0],
                ['user_id', '!=', 3233]
        ])->count();

        // Count photos submitted for verification
        $photosAwaitingVerification = Photo::where([
            ['verified', '<', 2], // not verified
            ['verification', '>', 0], // submitted for verification
            ['user_id', '!=', 3233]
        ])->count();

        return [
            'photo' => $photo,
            'photosNotProcessed' => $photosNotProcessed,
            'photosAwaitingVerification' => $photosAwaitingVerification
        ];
    }
}
