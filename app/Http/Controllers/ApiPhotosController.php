<?php

namespace App\Http\Controllers;

use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\MakeImageAction;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Events\ImageDeleted;
use App\Exceptions\PhotoAlreadyUploaded;
use App\Http\Requests\Api\AddTagsRequest;
use App\Http\Requests\Api\UploadPhotoWithTagsRequest;
use App\Models\Photo;
use App\Models\User\User;
use GeoHash;
use Carbon\Carbon;

use App\Jobs\Api\AddTags;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;

use App\Helpers\Post\UploadHelper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiPhotosController extends Controller
{
    protected $userId;

    /** @var UploadHelper */
    protected $uploadHelper;
    /** @var UploadPhotoAction */
    private $uploadPhotoAction;
    /** @var DeletePhotoAction */
    private $deletePhotoAction;
    /** @var MakeImageAction */
    private $makeImageAction;

    /**
     * ApiPhotosController constructor
     * Apply middleware to all of these routes
     *
     * @param UploadHelper $uploadHelper
     * @param UploadPhotoAction $uploadPhotoAction
     * @param DeletePhotoAction $deletePhotoAction
     * @param MakeImageAction $makeImageAction
     */
    public function __construct (
        UploadHelper $uploadHelper,
        UploadPhotoAction $uploadPhotoAction,
        DeletePhotoAction $deletePhotoAction,
        MakeImageAction $makeImageAction
    )
    {
        $this->uploadHelper = $uploadHelper;
        $this->uploadPhotoAction = $uploadPhotoAction;
        $this->deletePhotoAction = $deletePhotoAction;
        $this->makeImageAction = $makeImageAction;

        $this->middleware('auth:api');
    }

    /**
     * Upload Photo
     *
     * @param Request $request
     *
     * array (
        'lat' => '55.455525',
        'lon' => '-5.713071670000001',
        'date' => '2021:06:04 15:50:55',
        'presence' => 'true',
        'model' => 'iPhone 12',
        'photo' =>
            Illuminate\Http\UploadedFile::__set_state(array(
                'test' => false,
                'originalName' => 'IMG_2624.JPG',
                'mimeType' => 'image/jpeg',
                'error' => 0,
                'hashName' => NULL
            ))
        );
     *
     * @return array
     */
    public function store (Request $request) :array
    {
        $request->validate([
            'photo' => 'required|mimes:jpg,png,jpeg,heic,heif',
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
            'date' => 'required'
        ]);

        $file = $request->file('photo');

        if ($file->getError() === 3)
        {
            return ['success' => false, 'msg' => 'error-3'];
        }

        try {
            $photo = $this->storePhoto($request);
        } catch (PhotoAlreadyUploaded $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true, 'photo_id' => $photo->id];
    }

    /**
     * Stores a photo
     * This is to handle all APIs from mobile app versions
     *
     * @param Request $request
     * @return Photo
     * @throws PhotoAlreadyUploaded
     */
    protected function storePhoto(Request $request): Photo
    {
        $file = $request->file('photo');
        /** @var User $user */
        $user = auth()->user();

        if (!$user->has_uploaded) $user->has_uploaded = 1;

        Log::channel('photos')->info([
            'app_upload' => $request->all(),
            'user_id' => $user['id']
        ]);

        $model = $request->filled('model')
            ? $request->model
            : 'Mobile app v2';

        $image = $this->makeImageAction->run($file)['image'];

        $lat = $request['lat'];
        $lon = $request['lon'];

        $date = str_contains($request['date'], ':')
            ? $request['date']
            : (int)$request['date'];

        $date = Carbon::parse($date);

        // The user with id=1 needs to upload duplicate images for testing
        if (app()->environment() === "production" && $user->id != 1) {
            if (Photo::where(['user_id' => $user->id, 'datetime' => $date])->exists()) {
                throw new PhotoAlreadyUploaded();
            }
        }

        // Upload images to both 's3' and 'bbox' disks, resized for 'bbox'
        $imageName = $this->uploadPhotoAction->run(
            $image,
            $date,
            $file->hashName()
        );

        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $date,
            $file->hashName(),
            'bbox'
        );

        $revGeoCode = app(ReverseGeocodeLocationAction::class)->run($lat, $lon);

        // The entire address as a string
        $display_name = $revGeoCode["display_name"];
        // Extract the address array as $key => $value pairs.
        $addressArray = $revGeoCode["address"];
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        $country = $this->uploadHelper->getCountryFromAddressArray($addressArray);
        $state = $this->uploadHelper->getStateFromAddressArray($country, $addressArray);
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray);

        /** @var Photo $photo */
        $photo = $user->photos()->create([
            'filename' => $imageName,
            'datetime' => $date,
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => $display_name,
            'location' => $location,
            'road' => $road,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'city' => $city->city,
            'county' => $state->state,
            'country' => $country->country,
            'country_code' => $country->shortcode,
            'model' => $model,
            'remaining' => !$user->picked_up,
            'platform' => 'mobile',
            'geohash' => GeoHash::encode($lat, $lon),
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => json_encode($addressArray)
        ]);

        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update([
            'xp' => DB::raw('ifnull(xp, 0) + 1'),
            'total_images' => DB::raw('ifnull(total_images, 0) + 1')
        ]);

        $user->refresh();

        /** @var UpdateLeaderboardsForLocationAction $action */
        $action = app(UpdateLeaderboardsForLocationAction::class);
        $action->run($photo, $user->id, 1);

        // Broadcast an event to anyone viewing the Global Map
        event(new ImageUploaded(
            $user,
            $photo,
            $country,
            $state,
            $city
        ));

        // Move this to redis
        event(new IncrementPhotoMonth(
            $country->id,
            $state->id,
            $city->id,
            $date
        ));

        return $photo;
    }

    /**
     * Save litter data to a recently uploaded photo
     *
     * version 2
     *
     * This is used by gallery photos
     */
    public function addTags (AddTagsRequest $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $photo = Photo::find($request->photo_id);

        if ($photo->user_id !== $user->id || $photo->verified > 0)
        {
            abort(403, 'Forbidden');
        }

        Log::channel('tags')->info([
            'add_tags' => 'mobile',
            'request' => $request->all()
        ]);

        dispatch (new AddTags(
            $user->id,
            $photo->id,
            ($request->litter ?? $request->tags) ?? [],
            $request->custom_tags ?? [],
            $request->picked_up
        ));

        return ['success' => true, 'msg' => 'dispatched'];
    }

    /**
     * Upload Photo together with its tags
     *
     * @param UploadPhotoWithTagsRequest $request
     * @return array
     */
    public function uploadWithTags (UploadPhotoWithTagsRequest $request) :array
    {
        $file = $request->file('photo');

        if ($file->getError() === 3)
        {
            return ['success' => false, 'msg' => 'error-3'];
        }

        try {
            $photo = $this->storePhoto($request);
        } catch (PhotoAlreadyUploaded $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        dispatch (new AddTags(
            auth()->id(),
            $photo->id,
            $request->tags,
            $request->custom_tags,
            $request->picked_up
        ));

        return ['success' => true, 'photo_id' => $photo->id];
    }

    /**
     * Check if the user has any available photos that are uploaded, but not tagged
     */
    public function check ()
    {
        /** @var User $user */
        $user = auth()->user();

        $photos = $user->photos()
            ->where('verified', 0)
            ->where('verification', 0)
            ->select('id', 'filename')
            ->get();

        return ['photos' => $photos];
    }

    /**
     * Delete an image
     */
    public function deleteImage(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var Photo $photo */
        $photo = Photo::findOrFail($request->photoId);

        if ($user->id !== $photo->user_id) {
            abort(403);
        }

        $this->deletePhotoAction->run($photo);

        $photo->delete();

        $user->xp = $user->xp > 0 ? $user->xp - 1 : 0;
        $user->total_images = $user->total_images > 0 ? $user->total_images - 1 : 0;
        $user->save();

        /** @var UpdateLeaderboardsForLocationAction $action */
        $action = app(UpdateLeaderboardsForLocationAction::class);
        $action->run($photo, $user->id, -1);

        event(new ImageDeleted(
            $user,
            $photo->country_id,
            $photo->state_id,
            $photo->city_id,
            $photo->team_id
        ));

        return ['success' => true];
    }
}
