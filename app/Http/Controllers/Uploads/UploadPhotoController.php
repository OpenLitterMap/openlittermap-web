<?php

namespace App\Http\Controllers\Uploads;

use Illuminate\Support\Facades\Log;
use App\Exceptions\InvalidCoordinates;
use Geohash\GeoHash;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Helpers\Post\UploadHelper;
use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Http\Requests\UploadPhotoRequest;
use App\Actions\Locations\ReverseGeocodeLocationAction;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UploadPhotoController extends Controller
{
    /** @var MakeImageAction */
    private $makeImageAction;

    /** @var UploadPhotoAction */
    private $uploadPhotoAction;

    /** @var UploadHelper */
    protected $uploadHelper;

    /** @var UpdateLeaderboardsForLocationAction */
    private $updateLeaderboardsAction;

    /**
     * Initialise Helper Actions
     */
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
     * Check for GPS co-ordinates or abort
     * Get/Create Country, State, and City for the lat/lon
     *
     * Move photo to AWS S3 in production || local in development
     * then persist new record to photos table
     */
    public function __invoke (UploadPhotoRequest $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        Log::channel('photos')->info([
            'web_upload' => $request->all(),
            'user_id' => $user->id
        ]);

        if (!$user->has_uploaded) {
            $user->has_uploaded = 1;
        }

        $file = $request->file('file'); // /tmp/php7S8v..

        $imageAndExifData = $this->makeImageAction->run($file);
        $image = $imageAndExifData['image'];
        $exif = $imageAndExifData['exif'];

        // Step 1: Verification
        if (is_null($exif))
        {
            abort(500, "Sorry, no GPS on this one.");
        }

        // Check if the EXIF has GPS data
        // todo - make this error appear on the frontend dropzone without clicking the "X"
        // todo - translate the error
        if (!array_key_exists("GPSLatitudeRef", $exif))
        {
            abort(500, "Sorry, no GPS on this one.");
        }

        // Check for 0 value
        if ($exif["GPSLatitude"][0] === "0/0" && $exif["GPSLongitude"][0] === "0/0")
        {
            abort(500,
                "Sorry, Your Images have GeoTags, 
                but they have values of Zero. 
                You may have lost the geotags when transferring images across devices."
            );
        }

        $dateTime = '';

        // Some devices store the timestamp key in a different format and using a different key.
        if (array_key_exists('DateTimeOriginal', $exif))
        {
            $dateTime = $exif["DateTimeOriginal"];
        }

        if (!$dateTime && array_key_exists('DateTime', $exif))
        {
            $dateTime = $exif["DateTime"];
        }

        if (!$dateTime && array_key_exists('FileDateTime', $exif)) {
            $dateTime = $exif["FileDateTime"];
            $dateTime = Carbon::createFromTimestamp($dateTime);
        }

        // convert to YYYY-MM-DD hh:mm:ss format
        $dateTime = Carbon::parse($dateTime);

        // Check if the user has already uploaded this image
        // todo - load error automatically without clicking it
        // todo - translate
        if (app()->environment() === "production" && Photo::where(['user_id' => $user->id, 'datetime' => $dateTime])->first())
        {
            abort(500, "You have already uploaded this file!");
        }

        // End Step 1: Verification

        // Step 2: Upload The Image(s)
        // Upload images to both 's3' and 'bbox' disks, resized for 'bbox'
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
        // End Step 2: Upload The Image(s)

        // Step 3: Get GPS & Check for Locations
        // Get coordinates
        $lat_ref   = $exif["GPSLatitudeRef"];
        $lat       = $exif["GPSLatitude"];
        $long_ref  = $exif["GPSLongitudeRef"];
        $lon       = $exif["GPSLongitude"];

        $latlong = $this->dmsToDec($lat, $lon, $lat_ref, $long_ref);

        $latitude = $latlong[0];
        $longitude = $latlong[1];

        if (($latitude === 0 && $longitude === 0) || ($latitude === '0' && $longitude === '0'))
        {
            Log::info("invalid coordinates found for userId $user->id \n");
            abort(500, "Invalid coordinates: lat=0, lon=0");
        }

        // Use OpenStreetMap to Reverse Geocode the coordinates into an Address.
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
        // End Step 3: Get GPS & Check for Locations

        // Step 4: Create the Photo
        // prepare data we need to create
        $geohasher = new GeoHash();
        $geohash = $geohasher->encode($latlong[0], $latlong[1]);

        // Get phone model
        $model = (array_key_exists('Model', $exif) && !empty($exif["Model"]))
            ? $exif["Model"]
            : 'Unknown';

        /** Create the $var Photo $photo */
        $photo = Photo::create([
            'user_id' => $user->id,
            'filename' => $imageName,
            'datetime' => $dateTime,
            'remaining' => !$user->picked_up,
            'lat' => $latlong[0],
            'lon' => $latlong[1],
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
        // $user->images_remaining -= 1;
        // End Step 4: Create the Photo

        // Step 5: Reward XP & Update Leaderboards
        // Reward XP
        // To do - move this to Redis
        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update([
            'xp' => DB::raw('ifnull(xp, 0) + 1'),
            'total_images' => DB::raw('ifnull(total_images, 0) + 1')
        ]);

        $user->refresh();

        // Update the Leaderboards
        $this->updateLeaderboardsAction->run(
            $photo,
            $user->id,
            1
        );
        // End Step 5: Update Leaderboards

        // Step 6: Dispatch Events & Notifications
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

        return [
            'success' => true
        ];
    }

    /**
     * Convert Degrees, Minutes and Seconds to Lat, Long
     * Cheers to Hassan for this!
     *
     *  "GPSLatitude" => array:3 [ might be an array
    0 => "51/1"
    1 => "50/1"
    2 => "888061/1000000"
    ]
     */
    private function dmsToDec ($lat, $lon, $lat_ref, $long_ref)
    {
        $lat[0] = explode("/", (string) $lat[0]);
        $lat[1] = explode("/", (string) $lat[1]);
        $lat[2] = explode("/", (string) $lat[2]);

        $lon[0] = explode("/", (string) $lon[0]);
        $lon[1] = explode("/", (string) $lon[1]);
        $lon[2] = explode("/", (string) $lon[2]);

        $lat[0] = (int)$lat[0][0] / (int)$lat[0][1];
        $lon[0] = (int)$lon[0][0] / (int)$lon[0][1];

        $lat[1] = (int)$lat[1][0] / (int)$lat[1][1];
        $lon[1] = (int)$lon[1][0] / (int)$lon[1][1];

        $lat[2] = (int)$lat[2][0] / (int)$lat[2][1];
        $lon[2] = (int)$lon[2][0] / (int)$lon[2][1];

        $lat = $lat[0]+((($lat[1]*60)+($lat[2]))/3600);
        $lon = $lon[0]+((($lon[1]*60)+($lon[2]))/3600);
        if ($lat_ref === "S") {
            $lat *= -1;
        }

        if ($long_ref === "W") {
            $lon *= -1;
        }

        return [$lat, $lon];
    }
}
