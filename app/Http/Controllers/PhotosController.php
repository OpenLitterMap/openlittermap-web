<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\User\User;

use App\Events\ImageDeleted;
use App\Events\TagsVerifiedByAdmin;

use App\Helpers\Post\UploadHelper;
use App\Http\Requests\AddTagsRequest;

use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\GetPreviousCustomTagsAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhotosController extends Controller
{
    protected UploadHelper $uploadHelper;
    private AddTagsToPhotoAction $addTagsAction;
    private DeletePhotoAction $deletePhotoAction;
    private UpdateLeaderboardsForLocationAction $updateLeaderboardsAction;

    /**
     * PhotosController constructor
     * Apply middleware to all of these routes
     */
    public function __construct(
        UploadHelper $uploadHelper,
        AddTagsToPhotoAction $addTagsAction,
        UpdateLeaderboardsForLocationAction $updateLeaderboardsAction,
        DeletePhotoAction $deletePhotoAction
    )
    {
        $this->uploadHelper = $uploadHelper;
        $this->addTagsAction = $addTagsAction;
        $this->updateLeaderboardsAction = $updateLeaderboardsAction;
        $this->deletePhotoAction = $deletePhotoAction;

        $this->middleware('auth');
    }

    /**
     * Delete an image
     */
    public function deleteImage (Request $request)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photoid);

        if ($user->id !== $photo->user_id) {
            abort(403);
        }

        $this->deletePhotoAction->run($photo);

        $photo->delete();

        $user->xp = $user->xp > 0 ? $user->xp - 1 : 0;
        $user->total_images = $user->total_images > 0 ? $user->total_images - 1 : 0;
        $user->save();

        $this->updateLeaderboardsAction->run($photo, $user->id, -1);

        event(new ImageDeleted(
            $user,
            $photo->country_id,
            $photo->state_id,
            $photo->city_id,
            $photo->team_id
        ));

        return response()->json(['message' => 'Photo deleted successfully!']);
    }

    /**
     * Dynamically add tags to an image
     *
     * Note! The $column passed through must match the column name on the table.
     * eg 'butts' must be a column on the smoking table.
     *
     * If the user is new, we submit the image for verification.
     * If the user is trusted, we can update OLM.
     */
    public function addTags (AddTagsRequest $request, AddCustomTagsToPhotoAction $customTagsAction): JsonResponse
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photo_id);

        if ($photo->user_id !== $user->id || $photo->verified > 0)
        {
            abort(403, 'Forbidden');
        }

        $customTagsTotal = $customTagsAction->run($photo, $request->custom_tags ?? []);

        $litterTotals = $this->addTagsAction->run($photo, $request->tags ?? []);

        $user->xp += $litterTotals['all'] + $customTagsTotal;
        $user->save();

        $this->updateLeaderboardsAction->run($photo, $user->id, $litterTotals['all'] + $customTagsTotal);

        $photo->remaining = !$request->picked_up;
        $photo->total_litter = $litterTotals['litter'];

        if (!$user->is_trusted)
        {
            // Bring the photo to an initial state of verification
            // 0 for testing, 0.1 for production
            // This value can be +/- 0.1 when users vote True or False
            // When verification reaches 1.0, it verified increases from 0 to 1
            $photo->verification = 0.1;
        }
        else
        {
            // the user is trusted. Dispatch event to update OLM.
            $photo->verification = 1;
            $photo->verified = 2;
            event (new TagsVerifiedByAdmin($photo->id));
        }

        $photo->save();

        return response()->json([
            'success' => true,
            'msg' => 'success'
        ]);
    }

    /**
     * Get unverified photos for tagging
     */
    public function unverified (GetPreviousCustomTagsAction $previousTagsAction): JsonResponse
    {
        $user = Auth::user();

        $query = Photo::where([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ]);

        // we need to get this before the pagination
        $remaining = $query->count();

        $photos = $query
            ->with('team')
            ->select('id', 'filename', 'lat', 'lon', 'model', 'remaining', 'display_name', 'datetime', 'team_id')
            ->simplePaginate(1);

        $total = Photo::where('user_id', $user->id)->count();

        return response()->json([
            'photos' => $photos,
            'remaining' => $remaining,
            'total' => $total,
            'custom_tags' => $previousTagsAction->run($user)
        ]);
    }
}
