<?php

namespace App\Http\Controllers;

use App\Actions\Photos\DeletePhotoAction;
use App\Actions\CalculateTagsDifferenceAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use App\Events\ImageDeleted;
use App\Models\Photo;
use App\Models\User\User;

use App\Traits\AddTagsTrait;

use Carbon\Carbon;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use App\Events\TagsVerifiedByAdmin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class AdminController extends Controller
{
    use AddTagsTrait;

    /** @var DeleteTagsFromPhotoAction */
    protected $deleteTagsAction;

    /** @var UpdateLeaderboardsForLocationAction */
    protected $updateLeaderboardsAction;

    /** @var DeletePhotoAction */
    protected $deletePhotoAction;

    /** @var CalculateTagsDifferenceAction */
    protected $calculateTagsDiffAction;

    /**
     * Apply IsAdmin middleware to all of these routes
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
        $this->deletePhotoAction = $deletePhotoAction;
        $this->calculateTagsDiffAction = $calculateTagsDiffAction;
    }

    /**
     * Get the total number of users who have signed up
     */
    public function getUserCount ()
    {
        $users = User::where('verified', 1)
            ->orWhere('name', 'default')
            ->get()
            ->sortBy('created_at');

        $totalUsers = $users->count();

        $users = $users->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });

        $upm = [];
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach($users as $index => $monthlyUser)
        {
            $month = $months[(int) substr((string) $index, 0, 2)];
            $year = substr((string) $index, 2, 5);
            $upm[$month.$year] = $monthlyUser->count(); // Mar-17
        }

        $upm = json_encode($upm);

        $usersUploaded = User::where('has_uploaded', 1)->get();

        $usersUploaded = $usersUploaded->groupBy(function($val) {
            return Carbon::parse($val->created_at)->format('m-y');
        });;

        $uupm = [];
        foreach($usersUploaded as $index => $userUploaded)
        {
            $month = $months[(int)$substr = substr((string) $index, 0, 2)];
            $year = substr((string) $index, 2, 5);
            $uupm[$month.$year] = $userUploaded->count(); // Mar-17
        }

        $uupm = json_encode($uupm);

        return view('admin.usercount', ['users' => $users, 'totalUsers' => $totalUsers, 'upm' => $upm, 'uupm' => $uupm]);
    }

    /**
     * Verify an image, delete the image
     */
    public function verify (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::findOrFail($request->photoId);

        $this->deletePhotoAction->run($photo);

        $photo->verification = 1;
        $photo->verified = 2;
        $photo->filename = '/assets/verified.jpg';
        $photo->save();

        rewardXpToAdmin();

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        event (new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Delete an image and its records
     */
    public function destroy (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::findOrFail($request->photoId);
        $user = User::find($photo->user_id);

        $this->deletePhotoAction->run($photo);

        $tagUpdates = $this->calculateTagsDiffAction->run(
            $photo->tags(),
            [],
            $photo->customTags->pluck('tag')->toArray(),
            []
        );

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        $this->deleteTagsAction->run($photo);

        $photo->delete();

        $totalXp = $tagUpdates['removedUserXp'] + 1; // 1xp from uploading

        $user->xp = max(0, $user->xp - $totalXp);
        $user->total_images = $user->total_images > 0 ? $user->total_images - 1 : 0;
        $user->save();

        $this->updateLeaderboardsAction->run($photo, $user->id, -$totalXp);

        rewardXpToAdmin();

        event(new ImageDeleted(
            $user,
            $photo->country_id,
            $photo->state_id,
            $photo->city_id,
            $photo->team_id
        ));

        return ['success' => true];
    }

    /**
     * Update the contents of an Image, Delete the image
     */
    public function updateDelete (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::find($request->photoId);

        $this->deletePhotoAction->run($photo);

        $photo->filename = '/assets/verified.jpg';

        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();

        // TODO categories and custom_tags are not provided in the front-end
        $tagUpdates = $this->addTags($request->categories ?? [], $request->custom_tags ?? [], $photo->id);

        rewardXpToAdmin(1 + $tagUpdates['rewardedAdminXp']);

        logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        event(new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Returns all the countries that have unverified photos
     * and their totals
     */
    public function getCountriesWithPhotos(): Collection
    {
        $totalsQuery = Photo::onlyFromUsersThatAllowTagging()
            ->selectRaw('country_id, count(*) as total')
            ->whereIn('verification', [0, 0.1])
            ->groupBy('country_id');

        // Using DB to avoid extra appended properties
        return DB::table('countries')
            ->selectRaw('id, country, q.total')
            ->rightJoinSub($totalsQuery, 'q', 'countries.id', '=', 'q.country_id')
            ->get()
            ->keyBy('id');
    }
}
