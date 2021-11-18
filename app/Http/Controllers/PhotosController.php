<?php

namespace App\Http\Controllers;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\UpdateLeaderboardsFromPhotoAction;
use App\Events\ImageDeleted;
use App\Http\Requests\AddTagsRequest;
use App\Jobs\Photos\StorePhoto;
use Carbon\Carbon;

use App\Models\Photo;
use Illuminate\Http\Request;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Support\Facades\Auth;

class PhotosController extends Controller
{
    /** @var AddTagsToPhotoAction */
    private $addTagsAction;
    /** @var UpdateLeaderboardsFromPhotoAction */
    private $updateLeaderboardsAction;
    /** @var UploadPhotoAction */
    private $uploadPhotoAction;
    /** @var DeletePhotoAction */
    private $deletePhotoAction;
    /** @var MakeImageAction */
    private $makeImageAction;

    /**
     * PhotosController constructor
     * Apply middleware to all of these routes
     *
     * @param AddTagsToPhotoAction $addTagsAction
     * @param UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction
     * @param UploadPhotoAction $uploadPhotoAction
     * @param DeletePhotoAction $deletePhotoAction
     * @param MakeImageAction $makeImageAction
     */
    public function __construct(
        AddTagsToPhotoAction $addTagsAction,
        UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction,
        UploadPhotoAction $uploadPhotoAction,
        DeletePhotoAction $deletePhotoAction,
        MakeImageAction $makeImageAction
    )
    {
        $this->addTagsAction = $addTagsAction;
        $this->updateLeaderboardsAction = $updateLeaderboardsAction;
        $this->uploadPhotoAction = $uploadPhotoAction;
        $this->deletePhotoAction = $deletePhotoAction;
        $this->makeImageAction = $makeImageAction;

        $this->middleware('auth');
    }

    /**
     * The user wants to upload a photo
     *
     * Check for GPS co-ordinates or abort
     * Get/Create Country, State, and City for the lat/lon
     *
     * Move photo to AWS S3 in production || local in development
     * then persist new record to photos table
     *
     * @param Request $request
     * @return bool[]
     */
    public function store (Request $request)
    {
        $request->validate([
           'file' => 'required|mimes:jpg,png,jpeg,heif,heic'
        ]);

        $user = Auth::user();

        \Log::channel('photos')->info([
            'web_upload' => $request->all(),
            'user_id' => $user->id
        ]);

        $file = $request->file('file'); // /tmp/php7S8v..

        $imageAndExifData = $this->makeImageAction->run($file);
        $image = $imageAndExifData['image'];
        $exif = $imageAndExifData['exif'];

        if (is_null($exif))
        {
            abort(500, "Sorry, no GPS on this one. Code=1");
        }

        // Check if the EXIF has GPS data
        // todo - make this error appear on the frontend dropzone without clicking the "X"
        // todo - translate the error
        if (!array_key_exists("GPSLatitudeRef", $exif))
        {
            abort(500, "Sorry, no GPS on this one. Code=2");
        }

        $dateTime = '';

        // Some devices store the timestamp key in a different format and using a different key.
        if (array_key_exists('DateTimeOriginal', $exif))
        {
            $dateTime = $exif["DateTimeOriginal"];
        }
        if (!$dateTime)
        {
            if (array_key_exists('DateTime', $exif))
            {
              $dateTime = $exif["DateTime"];
            }
        }
        if (!$dateTime)
        {
            if (array_key_exists('FileDateTime', $exif))
            {
                $dateTime = $exif["FileDateTime"];
                $dateTime = Carbon::createFromTimestamp($dateTime);
            }
        }

        // convert to YYYY-MM-DD hh:mm:ss format
        $dateTime = Carbon::parse($dateTime);

        // Check if the user has already uploaded this image
        // todo - load error automatically without clicking it
        // todo - translate
        if (app()->environment() === "production")
        {
            if (Photo::where(['user_id' => $user->id, 'datetime' => $dateTime])->first())
            {
                abort(500, "You have already uploaded this file!");
            }
        }

        // Upload images to both 's3' and 'bbox' disks, resized for 'bbox'
        $imageName = $this->uploadPhotoAction->run(
            $image,
            $dateTime,
            $file->hashName()
        );

        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $dateTime,
            $file->hashName(),
            'bbox'
        );

        dispatch(new StorePhoto(
            $dateTime,
            $user->id,
            $exif,
            $imageName,
            $bboxImageName
        ));

        return ['success' => true];
    }

    /**
     * Delete an image
     */
    public function deleteImage(Request $request)
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

        event(new ImageDeleted(
            $user,
            $photo->country_id,
            $photo->state_id,
            $photo->city_id,
            $photo->team_id
        ));

        return ['message' => 'Photo deleted successfully!'];
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
    public function addTags (AddTagsRequest $request)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photo_id);

        if ($photo->user_id !== $user->id || $photo->verified > 0)
        {
            abort(403, 'Forbidden');
        }

        $litterTotals = $this->addTagsAction->run($photo, $request['tags']);

        $user->xp += $litterTotals['all'];
        $user->save();

        $this->updateLeaderboardsAction->run($user, $photo);

        $photo->remaining = $request->presence;
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

        return ['msg' => 'success'];
    }

    /**
     * Get unverified photos for tagging
     */
    public function unverified ()
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

        return [
            'photos' => $photos,
            'remaining' => $remaining,
            'total' => $total
        ];
    }
}
