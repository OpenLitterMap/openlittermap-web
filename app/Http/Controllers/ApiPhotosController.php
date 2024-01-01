<?php

namespace App\Http\Controllers;

use GeoHash;
use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;

use App\Jobs\Api\AddTags;

use App\Events\ImageDeleted;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;

use App\Helpers\Post\UploadHelper;

use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use App\Exceptions\InvalidCoordinates;
use App\Exceptions\PhotoAlreadyUploaded;

use App\Http\Requests\Api\AddTagsRequest;
use App\Http\Requests\Api\UploadPhotoWithOrWithoutTagsRequest;

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
     * Stores a photo
     * This is to handle all APIs from mobile app versions
     *
     * @throws InvalidCoordinates
     * @throws PhotoAlreadyUploaded
     */
    protected function storePhoto (Request $request): Photo
    {
        $file = $request->file('photo');

        /** @var User $user */
        $user = auth()->user();

        if (!$user->has_uploaded) {
            $user->has_uploaded = 1;
        }

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

        if (($lat === 0 && $lon === 0) || ($lat === '0' && $lon === '0'))
        {
            Log::info("invalid coordinates found for userId $user->id \n");
            throw new InvalidCoordinates();
        }

        $date = str_contains((string) $request['date'], ':')
            ? $request['date']
            : (int)$request['date'];

        $date = Carbon::parse($date);

        // temp disabling this
        // The user with id = 1 needs to upload duplicate images for testing
//        if (app()->environment() === "production" && !in_array($user->id, $excludedUserIds)) {
//            if (Photo::where(['user_id' => $user->id, 'datetime' => $date])->exists()) {
//                \Log::info(['user_id', $user->id]);
//                \Log::info(['date', $date]);
//                throw new PhotoAlreadyUploaded();
//            }
//        }

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
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray, $lat, $lon);

        $pickedUp = $request->filled('picked_up')
            ? $request->picked_up
            : !$user->items_remaining;

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
            'remaining' => !$pickedUp,
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
    */
    public function store (Request $request): array
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
            return [
                'success' => false,
                'msg' => 'error-3'
            ];
        }

        try
        {
            $photo = $this->storePhoto($request);
        }
        catch (PhotoAlreadyUploaded | InvalidCoordinates $e)
        {
            return [
                'success' => false,
                'msg' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'photo_id' => $photo->id
        ];
    }

    /**
     * Upload Photo
     *
     * May or may not have tags.
     */
    public function uploadWithOrWithoutTags (UploadPhotoWithOrWithoutTagsRequest $request) :array
    {
//        not sure if we need this
//        $file = $request->file('photo');
//
//
//        // The uploaded file was only partially uploaded.
//        // we are not handling this on the app
//        if ($file->getError() === 3)
//        {
//            return [
//                'success' => false,
//                'msg' => 'error-3'
//            ];
//        }

        try
        {
            $photo = $this->storePhoto($request);
        }
        catch (PhotoAlreadyUploaded $e)
        {
            Log::info(['ApiPhotosController@uploadWithOrWithoutTags.1', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'photo-already-uploaded'
            ];
        } catch (InvalidCoordinates $e) {
            Log::info(['ApiPhotosoController@uploadWithOrWithoutTags.2', $e->getMessage()]);

            return [
                'success' => false,
                'msg' => 'invalid-coordinates'
            ];
        }

        // customTags was added 10th March 2023
        if ($request->tags || $request->custom_tags)
        {
            dispatch (new AddTags(
                auth()->id(),
                $photo->id,
                $request->tags,
                $request->custom_tags
            ));
        }

        return [
            'success' => true,
            'photo_id' => $photo->id
        ];
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
