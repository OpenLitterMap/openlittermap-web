<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use GeoHash;

use Carbon\Carbon;
use App\CheckLocations;

use App\Models\Photo;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;

use App\Models\LitterTags;

use Illuminate\Http\Request;
use App\Events\ImageUploaded;
use App\Events\TagsVerifiedByAdmin;
use App\Events\Photo\IncrementPhotoMonth;

use Illuminate\Support\Facades\Redis;

class PhotosController extends Controller
{
    use CheckLocations;

   /**
    * Apply middleware to all of these routes
    */
    public function __construct ()
    {
    	return $this->middleware('auth');

    	parent::__construct();
    }

    /**
     * Move photo to AWS S3 in production || local in development
     * then persist new record to photos table
     *
     * @param Request $request
     */
    public function store (Request $request)
    {
        $this->validate($request, [
           'file' => 'required|mimes:jpg,png,jpeg'
        ]);

        $user = Auth::user();

        if (!$user->has_uploaded) $user->has_uploaded = 1;

        // we don't need this for now
        //if (!$user->images_remaining)
        //{
        //    // todo - make message show by default
        //    abort(500, "Sorry, your max upload limit has been reached.");
        //}

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

        // Some devices store this key in a different format. We need to check for many key types.
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

        // Create dir/filename and move to AWS S3
        $explode = explode('-', $dateTime);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        $filename = $file->hashName();
        $filepath = $y.'/'.$m.'/'.$d.'/'.$filename;

        // Upload the image to AWS
        if (app()->environment('production'))
        {
            $s3 = Storage::disk('s3');

            $s3->put($filepath, $image->stream(), 'public');

            $imageName = $s3->url($filepath);
        }
        else
        {
            $public_path = public_path('local-uploads/'.$y.'/'.$m.'/'.$d);

            // home/vagrant/Code/openlittermap-web/public/local-uploads/y/m/d
            if (!file_exists($public_path))
            {
                mkdir($public_path, 666, true);
            }

            $image->save($public_path . '/' . $filename);

            $imageName = config('app.url') . '/local-uploads/'.$y.'/'.$m.'/'.$d .'/'.$filename;
        }

        // Get phone model
        $model = (array_key_exists('Model', $exif))
            ? $exif["Model"]
            : 'Unknown';

        // Get coordinates
         $lat_ref = $exif["GPSLatitudeRef"];
             $lat = $exif["GPSLatitude"];
        $long_ref = $exif["GPSLongitudeRef"];
            $long = $exif["GPSLongitude"];

        $latlong = self::dmsToDec($lat, $long, $lat_ref, $long_ref);
        $latitude = $latlong[0];
        $longitude = $latlong[1];

        // todo - let horizon process address details as a Job.
        // Reverse Geocode = 10,000 - 30,000 requests per day
        $apiKey = config('services.location.secret');
        $url =  "https://locationiq.org/v1/reverse.php?format=json&key=".$apiKey."&lat=".$latitude."&lon=".$longitude."&zoom=20";

        // The entire reverse geocoded result
        $revGeoCode = json_decode(file_get_contents($url), true);
        // The entire address as a string
        $display_name = $revGeoCode["display_name"];
        // Extract the address array
        $addressArray = $revGeoCode["address"];
         // \Log::info(['Address', $addressArray]);
        // dd($addressArray);
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

        // todo- check all locations for "/" and replace with "-"
        // todo - return country/state/city without having to check again
        // todo - process this as a job when request is made to get reverse geocoded data
        // todo - check country by shortcode not the full string
        $this->checkCountry($addressArray);
        $this->checkState($addressArray);
        $this->checkDistrict($addressArray);
        $this->checkCity($addressArray);
        $this->checkSuburb($addressArray);

        $countryId = Country::where('country', $this->country)
                    ->orWhere('countrynameb', $this->country)
                    ->orWhere('countrynamec', $this->country)->first()->id;

        $stateId = State::where('state', $this->state)
                  ->orWhere('statenameb', $this->state)->first()->id;

        $cityId = City::where('city', $this->city)->first()->id;

        $geohash = GeoHash::encode($latlong[0], $latlong[1]);

        $user->photos()->create([
            'filename' => $imageName,
            'datetime' => $dateTime,
            'lat' => $latlong[0],
            'lon' => $latlong[1],
            'display_name' => $display_name,
            'location' => $location,
            'road' => $road,
            'suburb' => $this->suburb,
            'city' => $this->city,
            'county' => $this->state,
            'state_district' => $this->district,
            'country' => $this->country,
            'country_code' => $this->countryCode,
            'model' => $model,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'city_id' => $cityId,
            'platform' => 'web',
            'geohash' => $geohash,
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $imageName // new 10th April 2021
        ]);

        // $user->images_remaining -= 1;

        $user->xp++;
        $user->total_images++;
        $user->save();

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        // Broadcast this event to anyone viewing the global map
        // This will also update country, state, and city.total_contributors_redis
        event (new ImageUploaded(
            $this->city,
            $this->state,
            $this->country,
            $this->countryCode,
            $imageName,
            $teamName,
            $user->id,
            $countryId,
            $stateId,
            $cityId
        ));

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        // This should dispatch a job
        event (new IncrementPhotoMonth($countryId, $stateId, $cityId, $dateTime));

        return ['msg' => 'success'];
    }

    /**
      ****** TODO - Configure Delete ONLY when user owns the image
      ** - Need to sort AWS permissions
      * Delete an image
    */
    public function deleteImage (Request $request)
    {
        $user = Auth::user();
        $photo = Photo::find($request->photoid);
        $s3 = Storage::disk('s3');

        try {
            if ($user->id === $photo->user_id)
            {
                if (app()->environment('production'))
                {
                    $path = substr($photo->filename, 42);
                    $s3->delete($path);
                }
                $photo->delete();
            }
        } catch (Exception $e) {
            // could not be deleted
            Log::info(["Photo could not be deleted", $e->getMessage()]);
        }

      	return redirect()->back();
    }

    /**
     * Dynamically add tags to an image
     *
     * Note! The $column passed through must match the column name on the table.
     * eg 'butts' must be a column on the smoking table.
     *
     * We use the $schema json object from LitterTags to get our class references
     *
     * If the user is new, we submit the image for verification.
     * If the user is trusted, we can update OLM.
     */
    public function addTags (Request $request)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($request->photo_id);
        if ($photo->verified > 0) return redirect()->back();

        $schema = LitterTags::INSTANCE()->getDecodedJSON();

        $litterTotal = 0;
        foreach ($request['tags'] as $category => $items)
        {
            foreach ($items as $column => $quantity)
            {
                // Column on photos table to make a relationship with current category eg smoking_id
                $id_table = $schema->$category->id_table;

                // Full class path
                $class = 'App\\Models\\Litter\\Categories\\'.$schema->$category->class;

                // Create reference to category.$id_table on photos if it does not exist
                if (is_null($photo->$id_table))
                {
                    $row = $class::create();
                    $photo->$id_table = $row->id;
                    $photo->save();
                }

                // If it does exist, get it
                else $row = $class::find($photo->$id_table);

                // Update quantity on the category table
                $row->$column = $quantity;
                $row->save();

                // Update Leaderboards if user has public privacy settings
                // todo - save data per leaderboard
                if (($user->show_name) || ($user->show_username))
                {
                    $country = Country::find($photo->country_id);
                    $state = State::find($photo->state_id);
                    $city = City::find($photo->city_id);
                    Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
                }

                $litterTotal += $quantity;
            }
        }

        $user->xp += $litterTotal;
        $user->save();

        $photo->remaining = $request->presence;
        $photo->total_litter = $litterTotal;

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
}
