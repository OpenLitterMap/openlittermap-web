<?php

namespace App\Http\Controllers;

use App\Actions\CalculateTagsDifferenceAction;
use App\Actions\Locations\UpdateLeaderboardsXpAction;
use App\Actions\LogAdminVerificationAction;
use App\Actions\Photos\DeleteTagsFromPhotoAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use App\Events\ImageDeleted;
use App\Http\Requests\GetImageForVerificationRequest;
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
            $month = $months[(int) substr($index, 0, 2)];
            $year = substr($index, 2, 5);
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
            $month = $months[(int)$substr = substr($index, 0, 2)];
            $year = substr($index, 2, 5);
            $uupm[$month.$year] = $userUploaded->count(); // Mar-17
        }
        $uupm = json_encode($uupm);

        return view('admin.usercount', compact('users', 'totalUsers', 'upm', 'uupm'));
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

        $this->rewardXpToAdmin();

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        event (new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * The image and the tags are correct
     *
     * Updates Country, State + City table
     */
    public function verifykeepimage (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::findOrFail($request->photoId);
        $photo->verified = 2;
        $photo->verification = 1;
        $photo->save();

        $this->rewardXpToAdmin();

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod());

        event (new TagsVerifiedByAdmin($photo->id));
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

        $tagUpdates = $this->calculateTagsDiffAction->run(
            $photo->tags(),
            [],
            $photo->customTags->pluck('tag')->toArray(),
            []
        );
        $this->deleteTagsAction->run($photo);

        $user = User::find($photo->user_id);
        $user->xp = max(0, $user->xp - $tagUpdates['removedUserXp']);
        $user->count_correctly_verified = 0;
        $user->save();

        $this->updateLeaderboardsAction->run($photo, $user->id, - $tagUpdates['removedUserXp']);

        $this->rewardXpToAdmin();

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        return ['success' => true];
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

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        $this->deleteTagsAction->run($photo);

        $photo->delete();

        $totalXp = $tagUpdates['removedUserXp'] + 1; // 1xp from uploading

        $user->xp = max(0, $user->xp - $totalXp);
        $user->total_images = $user->total_images > 0 ? $user->total_images - 1 : 0;
        $user->save();

        $this->updateLeaderboardsAction->run($photo, $user->id, -$totalXp);

        $this->rewardXpToAdmin();

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

        $this->rewardXpToAdmin(1 + $tagUpdates['rewardedAdminXp']);

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        event(new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Verify the image
     * Keep the image
     * Image was not correctly inputted! LitterCorrectlyCount = 0.
     */
    public function updateTags (Request $request)
    {
        /** @var Photo $photo */
        $photo = Photo::find($request->photoId);
        $photo->verification = 1;
        $photo->verified = 2;
        $photo->total_litter = 0;
        $photo->save();
        $oldTags = $photo->tags();

        $user = User::find($photo->user_id);
        $user->count_correctly_verified = 0; // At 100, the user earns a Littercoin
        $user->save();

        $tagUpdates = $this->addTags($request->tags ?? [], $request->custom_tags ?? [], $request->photoId);

        $this->rewardXpToAdmin(1 + $tagUpdates['rewardedAdminXp']);

        $this->logAdminAction($photo, Route::getCurrentRoute()->getActionMethod(), $tagUpdates);

        event (new TagsVerifiedByAdmin($photo->id));
    }

    /**
     * Get the next image to verify
     *
     * @param GetImageForVerificationRequest $request
     * @return array
     */
    public function getImage (GetImageForVerificationRequest $request): array
    {
        // Photos that are uploaded and tagged come first
        /** @var Photo $photo */
        $photo = $this->filterPhotos()
            ->when($request->skip, function ($q) use ($request) {
                $q->skip((int) $request->skip);
            })
            ->where('verification', 0.1)
            ->first();

        // Load the tags for this photo if it exists
        if ($photo)
        {
            $photo->tags();
        }

        if (!$photo)
        {
            // Photos that have been uploaded, but not tagged or submitted for verification
            /** @var Photo $photo */
            $photo = $this->filterPhotos()
                ->when($request->skip, function ($q) use ($request) {
                    $q->skip($request->skip);
                })
                ->where('verification', 0)
                ->first();
        }

        // Count photos that are uploaded but not tagged
        $photosNotProcessed = $this->filterPhotos()
            ->where('verification', 0)
            ->count();

        // Count photos submitted for verification
        $photosAwaitingVerification = $this->filterPhotos()
            ->where([
                ['verified', '<', 2], // not verified
                ['verification', '>', 0], // submitted for verification
            ])
            ->count();

        return [
            'photo' => $photo,
            'photosNotProcessed' => $photosNotProcessed,
            'photosAwaitingVerification' => $photosAwaitingVerification
        ];
    }

    /**
     * Returns all the countries that have unverified photos
     * and their totals
     */
    public function getCountriesWithPhotos(): Collection
    {
        $totalsQuery = Photo::query()
            ->selectRaw('country_id, count(*) as total')
            ->whereIn('verification', [0, 0.1])
            ->whereNotIn('user_id', $this->usersToSkipVerification())
            ->groupBy('country_id');

        // Using DB to avoid extra appended properties
        return DB::table('countries')
            ->selectRaw('id, country, q.total')
            ->rightJoinSub($totalsQuery, 'q', 'countries.id', '=', 'q.country_id')
            ->get()
            ->keyBy('id');
    }

    /**
     * Generates a query builder with filtered photos
     * @return Builder|mixed
     */
    private function filterPhotos(): Builder
    {
        return Photo::query()
            ->with('customTags')
            ->whereNotIn('user_id', $this->usersToSkipVerification())
            ->when(request('country_id'), function (Builder $q) {
                return $q->whereCountryId(request('country_id'));
            });
    }

    /**
     * These users don't want their images verified
     *
     * @return int[]
     */
    protected function usersToSkipVerification(): array
    {
        return [3233, 5292];
    }

    /**
     * Rewards the admin performing the verification with xp
     * @param int $xp
     * @return void
     */
    private function rewardXpToAdmin(int $xp = 1): void
    {
        auth()->user()->increment('xp', $xp);
        /** @var UpdateLeaderboardsXpAction $updateLeaderboardsAction */
        $updateLeaderboardsAction = app(UpdateLeaderboardsXpAction::class);
        $updateLeaderboardsAction->run(auth()->id(), $xp);
    }

    /**
     * Logs the admin action into the database
     * for storing xp updates on the photo's user
     * @param Photo $photo
     * @param string $action
     * @param array|null $tagsDiff
     * @return void
     */
    private function logAdminAction(Photo $photo, string $action, array $tagsDiff = null): void
    {
        /** @var LogAdminVerificationAction $action */
        $logger = app(LogAdminVerificationAction::class);
        $logger->run(
            auth()->user(),
            $photo,
            $action,
            $tagsDiff['added'] ?? [],
            $tagsDiff['removed'] ?? [],
            $tagsDiff['rewardedAdminXp'] ?? 0,
            $tagsDiff['removedUserXp'] ?? 0
        );
    }
}
