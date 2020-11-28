<?php

namespace App\Http\Controllers;

use Log;
use Auth;
use Image;
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
     */
    public function store (Request $request)
    {
        $this->validate($request, [
           'file' => 'required|mimes:jpg,png,jpeg'
        ]);

        $user = Auth::user();

        if ($user->has_uploaded == 0) $user->has_uploaded = 1;

        if ($user->images_remaining == 0)
        {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: text/plain');
            exit ("Sorry, your max upload limit has been reached."); // todo - make message show by default
        }

        $file = $request->file('file'); // -> /tmp/php7S8v..
        $exif = Image::make($file)->exif();

        \Log::info(['exif', $exif]);

        // Check if the EXIF has GPS data
        // todo - make this error appear on the frontend dropzone.js without clicking it
        if (! array_key_exists("GPSLatitudeRef", $exif))
        {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: text/plain');
            exit ("Sorry, no GPS on this one");
        }

        $dateTime = '';

        if (array_key_exists('DateTimeOriginal', $exif))
        {
            $dateTime = $exif["DateTimeOriginal"];
        }

        if (! $dateTime)
        {
            if (array_key_exists('DateTime', $exif))
            {
              $dateTime = $exif["DateTime"];
            }
        }

        if (! $dateTime)
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
        if (app()->environment() === 'production')
        {
            if (Photo::where(['user_id' => $user->id, 'datetime' => $dateTime])->first())
            {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-type: text/plain');
                exit ("You have already uploaded this file!");
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
            $s3 = \Storage::disk('s3');
            $s3->put($filepath, file_get_contents($file), 'public');
            $imageName = $s3->url($filepath);
        }
        else $imageName = '/assets/verified.jpg';

        // Get phone model
        if (array_key_exists('Model', $exif))
        {
            $model = $exif["Model"];
        }
        else $model = 'Unknown';

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
            'team_id' => $user->active_team
        ]);

        // $user->images_remaining -= 1;

        $user->xp += 1;
//        $totalImages = 0; // $user->photos->sum('verified'); this is failing locally since upgraded from Laravel 5 to 8
//        $user->total_images = $totalImages;
        $user->save();

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        // Broadcast this event to anyone viewing the global map
        event (new ImageUploaded($this->city, $this->state, $this->country, $this->countryCode, $imageName, $teamName));

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        event (new IncrementPhotoMonth($countryId, $stateId, $cityId, $dateTime));

        if ($user->has_uploaded_today == 0)
        {
              $user->has_uploaded_today = 1;
              $user->has_uploaded_counter++;
              if ($user->has_uploaded_counter == 7)
              {
                  $user->littercoin_allowance++;
                  $user->has_uploaded_counter = 0;
              }
              $user->save();
        }

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
        $s3 = \Storage::disk('s3');

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
            \Log::info(["Photo could not be deleted", $e->getMessage()]);
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
            /* Bring the photo to an initial state of verification */
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }

        else // the user is trusted. Dispatch event to update OLM.
        {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new TagsVerifiedByAdmin($photo->id));
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

        $photos = Photo::select('id', 'filename', 'lat', 'lon', 'model', 'remaining', 'display_name', 'datetime')
            ->where([
                'user_id' => $user->id,
                'verified' => 0,
                'verification' => 0
            ])->simplePaginate(1);

        $remaining = Photo::where([
            'user_id' => $user->id,
            'verified' => 0,
            'verification' => 0
        ])->count();

        $total = Photo::where('user_id', $user->id)->count();

        return [
            'photos' => $photos,
            'remaining' => $remaining,
            'total' => $total
        ];
    }
}
