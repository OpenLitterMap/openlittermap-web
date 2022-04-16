<?php

namespace App\Http\Controllers;

use App\Actions\Photos\AddCustomTagsToPhotoAction;
use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\MakeImageAction;
use App\Actions\Photos\UploadPhotoAction;
use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use App\Events\ImageDeleted;
use App\Http\Requests\AddTagsRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Models\User\User;
use GeoHash;
use Carbon\Carbon;

use App\Models\Photo;
use Illuminate\Http\Request;
use App\Events\ImageUploaded;
use App\Events\TagsVerifiedByAdmin;
use App\Events\Photo\IncrementPhotoMonth;

use App\Helpers\Post\UploadHelper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PhotosController extends Controller
{
    /** @var UploadHelper */
    protected $uploadHelper;
    /** @var AddTagsToPhotoAction */
    private $addTagsAction;
    /** @var UpdateLeaderboardsForLocationAction */
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
     * @param UploadHelper $uploadHelper
     * @param AddTagsToPhotoAction $addTagsAction
     * @param UpdateLeaderboardsForLocationAction $updateLeaderboardsAction
     * @param UploadPhotoAction $uploadPhotoAction
     * @param DeletePhotoAction $deletePhotoAction
     * @param MakeImageAction $makeImageAction
     */
    public function __construct(
        UploadHelper $uploadHelper,
        AddTagsToPhotoAction $addTagsAction,
        UpdateLeaderboardsForLocationAction $updateLeaderboardsAction,
        UploadPhotoAction $uploadPhotoAction,
        DeletePhotoAction $deletePhotoAction,
        MakeImageAction $makeImageAction
    )
    {
        $this->uploadHelper = $uploadHelper;
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
     * @param UploadPhotoRequest $request
     * @return bool[]
     */
    public function store (UploadPhotoRequest $request): array
    {
        /** @var User $user */
        $user = Auth::user();

        \Log::channel('photos')->info([
            'web_upload' => $request->all(),
            'user_id' => $user->id
        ]);

        if (!$user->has_uploaded) $user->has_uploaded = 1;

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

        // Get phone model
        $model = (array_key_exists('Model', $exif) && !empty($exif["Model"]))
            ? $exif["Model"]
            : 'Unknown';

        // Get coordinates
        $lat_ref   = $exif["GPSLatitudeRef"];
        $lat       = $exif["GPSLatitude"];
        $long_ref  = $exif["GPSLongitudeRef"];
        $long      = $exif["GPSLongitude"];

        $latlong = self::dmsToDec($lat, $long, $lat_ref, $long_ref);
        $latitude = $latlong[0];
        $longitude = $latlong[1];

        $revGeoCode = app(ReverseGeocodeLocationAction::class)->run($latitude, $longitude);

        // The entire address as a string
        $display_name = $revGeoCode["display_name"];

        // Extract the address array
        $addressArray = $revGeoCode["address"];
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        // todo- check all locations for "/" and replace with "-"
        $country = $this->uploadHelper->getCountryFromAddressArray($addressArray);
        $state = $this->uploadHelper->getStateFromAddressArray($country, $addressArray);
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray);

        $geohash = GeoHash::encode($latlong[0], $latlong[1]);

        /** @var Photo $photo */
        $photo = $user->photos()->create([
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

        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update([
            'xp' => DB::raw('ifnull(xp, 0) + 1'),
            'total_images' => DB::raw('ifnull(total_images, 0) + 1')
        ]);

        $user->refresh();

        $this->updateLeaderboardsAction->run($photo, $user->id, 1);

        // Broadcast this event to anyone viewing the global map
        // This will also update country, state, and city.total_contributors_redis
        event(new ImageUploaded(
            $user,
            $photo,
            $country,
            $state,
            $city,
        ));

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        event(new IncrementPhotoMonth(
            $country->id,
            $state->id,
            $city->id,
            $dateTime
        ));

        return ['success' => true];
    }

    /**
     * Delete an image
     */
    public function deleteImage(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Photo $photo */
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
    public function addTags (AddTagsRequest $request, AddCustomTagsToPhotoAction $customTagsAction)
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var Photo $photo */
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

        return ['msg' => 'success'];
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
    private function dmsToDec ($lat, $long, $lat_ref, $long_ref)
    {
        $lat[0] = explode("/", $lat[0]);
        $lat[1] = explode("/", $lat[1]);
        $lat[2] = explode("/", $lat[2]);

        $long[0] = explode("/", $long[0]);
        $long[1] = explode("/", $long[1]);
        $long[2] = explode("/", $long[2]);

        $lat[0] = (int)$lat[0][0] / (int)$lat[0][1];
        $long[0] = (int)$long[0][0] / (int)$long[0][1];

        $lat[1] = (int)$lat[1][0] / (int)$lat[1][1];
        $long[1] = (int)$long[1][0] / (int)$long[1][1];

        $lat[2] = (int)$lat[2][0] / (int)$lat[2][1];
        $long[2] = (int)$long[2][0] / (int)$long[2][1];

        $lat = $lat[0]+((($lat[1]*60)+($lat[2]))/3600);
        $long = $long[0]+((($long[1]*60)+($long[2]))/3600);

        if ($lat_ref === "S") $lat = $lat * -1;
        if ($long_ref === "W") $long = $long * -1;

        return [$lat, $long];
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
