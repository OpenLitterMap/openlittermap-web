<?php

namespace App\Http\Controllers;

use App\Actions\Photos\AddTagsToPhotoAction;
use App\Actions\Photos\DeletePhotoAction;
use App\Actions\Photos\UpdateLeaderboardsFromPhotoAction;
use GeoHash;
use Exception;
use Carbon\Carbon;

use App\Models\Photo;
use Illuminate\Http\Request;
use App\Events\ImageUploaded;
use App\Events\TagsVerifiedByAdmin;
use App\Events\Photo\IncrementPhotoMonth;

use App\Helpers\Post\UploadHelper;

use Intervention\Image\Facades\Image;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotosController extends Controller
{
    /** @var UploadHelper */
    protected $uploadHelper;
    /** @var AddTagsToPhotoAction */
    private $addTagsAction;
    /** @var UpdateLeaderboardsFromPhotoAction */
    private $updateLeaderboardsAction;
    /** @var DeletePhotoAction  */
    private $deletePhotoAction;

    /**
     * Apply middleware to all of these routes
     *
     * @param UploadHelper $uploadHelper
     * @param AddTagsToPhotoAction $addTagsAction
     * @param UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction
     * @param DeletePhotoAction $deletePhotoAction
     */
    public function __construct(
        UploadHelper $uploadHelper,
        AddTagsToPhotoAction $addTagsAction,
        UpdateLeaderboardsFromPhotoAction $updateLeaderboardsAction,
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
     * @throws ValidationException
     */
    public function store (Request $request)
    {
        $this->validate($request, [
           'file' => 'required|mimes:jpg,png,jpeg'
        ]);

        $user = Auth::user();

        \Log::channel('photos')->info([
            'web_upload' => $request->all(),
            'user_id' => $user->id
        ]);

        if (!$user->has_uploaded) $user->has_uploaded = 1;

        $file = $request->file('file'); // /tmp/php7S8v..

        $image = Image::make($file);

        $image->resize(500, 500);

        $image->resize(500, 500, function ($constraint) {
            $constraint->aspectRatio();
        });

        $exif = $image->exif();

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
        if (app()->environment() === "production") {
            if (Photo::where(['user_id' => $user->id, 'datetime' => $dateTime])->first()) {
                abort(500, "You have already uploaded this file!");
            }
        }

        $imageName = $this->uploadImageToS3($file, $image, $dateTime);

        // Get phone model
        $model = (array_key_exists('Model', $exif))
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

        // todo - let horizon process address details as a Job.
        $apiKey = config('services.location.secret');
        $url =  "https://locationiq.org/v1/reverse.php?format=json&key=".$apiKey."&lat=".$latitude."&lon=".$longitude."&zoom=20";

        // The entire reverse geocoded result
        $revGeoCode = json_decode(file_get_contents($url), true);

        // The entire address as a string
        $display_name = $revGeoCode["display_name"];

        // Extract the address array
        $addressArray = $revGeoCode["address"];
         // \Log::info(['Address', $addressArray]);
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        // todo- check all locations for "/" and replace with "-"
        $country = $this->uploadHelper->getCountryFromAddressArray($addressArray);
        $state = $this->uploadHelper->getStateFromAddressArray($country, $addressArray);
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray);

        $geohash = GeoHash::encode($latlong[0], $latlong[1]);

        $user->photos()->create([
            'filename' => $imageName,
            'datetime' => $dateTime,
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
            'five_hundred_square_filepath' => $imageName,
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

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        // Broadcast this event to anyone viewing the global map
        // This will also update country, state, and city.total_contributors_redis
        event(new ImageUploaded(
            $city->city,
            $state->state,
            $country->country,
            $country->shortcode,
            $imageName,
            $teamName,
            $user->id,
            $country->id,
            $state->id,
            $city->id
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
     * TODO - Need to sort AWS permissions
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
    public function addTags (Request $request)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photo_id);
        if ($photo->verified > 0) return redirect()->back();

        $litterTotals = $this->addTagsAction->run($photo, $request['tags']);

        $user->xp += $litterTotals['all'];
        $user->save();

        $this->updateLeaderboardsAction->run($user, $photo);

        $photo->remaining = $request->presence;
        $photo->total_litter = $litterTotals['litter'];

        if ($user->verification_required)
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

        $photos = $query->select('id', 'filename', 'lat', 'lon', 'model', 'remaining', 'display_name', 'datetime')
            ->simplePaginate(1);

        $total = Photo::where('user_id', $user->id)->count();

        return [
            'photos' => $photos,
            'remaining' => $remaining,
            'total' => $total
        ];
    }

    /**
     * @param $file
     * @param \Intervention\Image\Image $image
     * @param Carbon $dateTime
     * @return string
     */
    protected function uploadImageToS3(
        $file,
        \Intervention\Image\Image $image,
        Carbon $dateTime
    ): string
    {
        // Create dir/filename and move to AWS S3
        $explode = explode('-', $dateTime);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        $filename = $file->hashName();
        $filepath = $y . '/' . $m . '/' . $d . '/' . $filename;

        // Upload image to AWS
        $s3 = Storage::disk('s3');

        $s3->put($filepath, $image->stream(), 'public');

        return $s3->url($filepath);
    }
}
