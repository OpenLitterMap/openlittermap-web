<?php

namespace App\Http\Controllers\Uploads;

use Geohash\GeoHash;

use App\Models\Photo;
use App\Helpers\Post\UploadHelper;

use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;

use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;
use GuzzleHttp\Exception\GuzzleException;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UploadPhotoController extends Controller
{
    protected UploadHelper $uploadHelper;
    private MakeImageAction $makeImageAction;
    private UploadPhotoAction $uploadPhotoAction;
    private UpdateLeaderboardsForLocationAction $updateLeaderboardsAction;

    public function __construct (
        MakeImageAction $makeImageAction,
        UploadPhotoAction $uploadPhotoAction,
        UploadHelper $uploadHelper,
        UpdateLeaderboardsForLocationAction $updateLeaderboardsAction
    )
    {
        $this->makeImageAction = $makeImageAction;
        $this->uploadPhotoAction = $uploadPhotoAction;
        $this->uploadHelper = $uploadHelper;
        $this->updateLeaderboardsAction = $updateLeaderboardsAction;
    }

    /**
     * The user wants to upload a photo
     *
     * Validation:
     * 1. Check photo is not already uploaded
     * 2. Check for GPS co-ordinates or fail validation.
     *
     * Steps:
     * Get/Create Country, State, and City for the lat/lon
     *
     * Move photo to AWS S3 in production || local in development
     * then persist new record to photos table
     *
     * @param UploadPhotoRequest $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function __invoke (UploadPhotoRequest $request): JsonResponse
    {
        $user = Auth::user();

        \Log::channel('photos')->info([
            'web_upload' => $request->all(),
            'user_id' => $user->id
        ]);

        $file = $request->file('photo');

        $imageAndExifData = $this->makeImageAction->run($file);
        $image = $imageAndExifData['image'];
        $exif = $imageAndExifData['exif'];

        $dateTime = getDateTimeForPhoto($exif);

        // Step 1: Upload The Image(s) to both 's3' and 'bbox' disks
        $imageName = $this->uploadPhotoAction->run(
            $image,
            $dateTime,
            $file->hashName()
        );

        // We should do this asynchronously after everything else is complete
        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $dateTime,
            $file->hashName(),
            'bbox'
        );

        // Step 2: Get GPS & Check for Locations
        $coordinates = getCoordinatesFromPhoto($exif);

        $latitude = $coordinates[0];
        $longitude = $coordinates[1];

        // Use OpenStreetMap to Reverse Geocode the coordinates into an address.
        $revGeoCode = app(ReverseGeocodeLocationAction::class)->run($latitude, $longitude);

        // The entire address as a string
        $display_name = $revGeoCode["display_name"];

        // Extract the address array
        $addressArray = $revGeoCode["address"];
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        // todo- check all locations for "/" and replace with "-"
        // this should return wasRecentlyCreated. This would enable us to create the photo,
        // and include the photo ID when we dispatch notifications.
        $country = $this->uploadHelper->getCountryFromAddressArray($addressArray);
        $state = $this->uploadHelper->getStateFromAddressArray($country, $addressArray);
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray, $latitude, $longitude);

        // Step 3: Create the Photo
        // prepare data we need to create
        $geohasher = new GeoHash();
        $geohash = $geohasher->encode($latitude, $longitude);

        // Get phone model
        $model = (array_key_exists('Model', $exif) && !empty($exif["Model"]))
            ? $exif["Model"]
            : 'Unknown';

        $photo = Photo::create([
            'user_id' => $user->id,
            'filename' => $imageName,
            'datetime' => $dateTime,
            'remaining' => !$user->picked_up,
            'lat' => $latitude,
            'lon' => $longitude,
            'display_name' => $display_name,
            'location' => $location,
            'road' => $road,
            'city' => $city->city,
            'county' => $state->state,
            'country' => $country->country,
            'country_code' => $country->shortcode,
            'model' => $model,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'platform' => 'web',
            'geohash' => $geohash,
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => json_encode($addressArray)
        ]);

        // Step 4: Reward XP, update resources & Update Leaderboards
        // $user->images_remaining -= 1;

        // move this to redis
        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update(['total_images' => DB::raw('ifnull(total_images, 0) + 1')]);
        $user->refresh();

        // Update the Leaderboards and give xp.
        $this->updateLeaderboardsAction->run($photo, $user->id);

        // Step 5: Dispatch Events & Notifications
        // Broadcast this event to anyone viewing the global map
        // This will also update country, state, and city.total_contributors_redis
        event(new ImageUploaded(
            $user,
            $photo,
            $country,
            $state,
            $city,
        ));

        // Broadcast an event to anyone viewing the Global Map
        // Sends Notification to Twitter & Slack
        if ($country->wasRecentlyCreated) {
            event(new NewCountryAdded($country->country, $country->shortcode, now()));
        }

        if ($state->wasRecentlyCreated) {
            event(new NewStateAdded($state->state, $country->country, now()));
        }

        if ($city->wasRecentlyCreated) {
            event(new NewCityAdded(
                $city->city,
                $state->state,
                $country->country,
                now(),
                $city->id,
                $latitude,
                $longitude,
                $photo->id
            ));
        }

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        // Todo: Capture PhotosPerDay & PhotosPerWeek
        event(new IncrementPhotoMonth(
            $country->id,
            $state->id,
            $city->id,
            $dateTime
        ));

        return response()->json([
            'success' => true
        ]);
    }
}
