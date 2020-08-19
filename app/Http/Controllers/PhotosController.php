<?php

namespace App\Http\Controllers;

use Log;
use Auth;
use Image;
use App\City;
use App\State;
use App\Photo;
use App\Totals;
use App\Country;
use Carbon\Carbon;
use App\CheckLocations;

use App\Litterrata;
use App\LitterGerman;
use App\LitterES;
use App\LitterFrench;
use App\LitterItalian;
use App\LitterMalay;
use App\LitterTurkish;

use Illuminate\Http\Request;
use App\Events\DynamicUpdate;
use App\Events\ImageUploaded;
use App\Events\PhotoVerifiedByAdmin;
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

        // Check if the EXIF has GPS data
        // todo - make this error appear on the frontend dropzone.js without clicking it
        if (! array_key_exists("GPSLatitudeRef", $exif))
        {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: text/plain');
            $msg = "Sorry, no GPS on this one";
            exit ($msg);
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
        if (Photo::where(['user_id' => $user->id, 'datetime' => $dateTime])->first())
        {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: text/plain');
            exit ("You have already uploaded this file!");
        }

        // Create dir/filename and move to AWS S3
        $explode = explode('-', $dateTime);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

        $filename = $file->hashName();
        $filepath = $y.'/'.$m.'/'.$d.'/'.$filename;
        $imageName = '';

        // if (app()->environment('local', 'testing')) {
        //     $file->move($filepath, $filename);
        //     $imageName = $filepath.$filename;
        // }
        // Upload the image to AWS
        if (app()->environment('production'))
        {
            $s3 = \Storage::disk('s3');
            $s3->put($filepath, file_get_contents($file), 'public');
            $imageName = $s3->url($filepath);
        }

        // Get phone model
        if (array_key_exists('Model', $exif))
        {
            $model = $exif["Model"];
        }

        else
        {
            $model = 'Unknown';
        }

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
        $apiKey = env('LOCATE_API_KEY');
        $url =  "https://locationiq.org/v1/reverse.php?format=json&key=".$apiKey."&lat=".$latitude."&lon=".$longitude."&zoom=20";

        // The entire reverse geocoded result
        $revGeoCode = json_decode(file_get_contents($url), true);
        // dd($revGeoCode);
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
            'platform' => 'web'
        ]);

        // $user->images_remaining -= 1;
        $user->xp += 1;
        $totalImages = $user->photos->sum('verified');
        $user->total_images = $totalImages;
        $user->save();

        // Broadcast this event to anyone viewing the global map
        event (new ImageUploaded($this->city, $this->state, $this->country, $imageName));

        // Increment the { Month-Year: int } value for each location
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

        return redirect()->back();
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
     * Dynamically add attributes to an image
     */
    public function dynamicUpdate (Request $request, $id)
    {
        $user = Auth::user();
        $photo = Photo::findOrFail($id);
        if ($photo->verified == 1) return redirect()->back();

        $lang = \App::getLocale();
        // todo - make this dynamic for all languages
        // we can do this by giving all litter an "id" instead of using name string.
        if      ($lang == "de") $jsonDecoded = LitterGerman::INSTANCE()->getDecodedJSON();
        else if ($lang == "en") $jsonDecoded = Litterrata::INSTANCE()->getDecodedJSON();
        else if ($lang == "es") $jsonDecoded = LitterES::INSTANCE()->getDecodedJSON();
        else if ($lang == "fr") $jsonDecoded = LitterFrench::INSTANCE()->getDecodedJSON();
        else if ($lang == "it") $jsonDecoded = LitterItalian::INSTANCE()->getDecodedJSON();
        else if ($lang == "ms") $jsonDecoded = LitterMalay::INSTANCE()->getDecodedJSON();
        else if ($lang == "tk") $jsonDecoded = LitterTurkish::INSTANCE()->getDecodedJSON();

        // return json_encode($jsonDecoded);
        $litterTotal = 0;
        // for each categories as category => values eg. Smoking => { Butts: 3 }
        foreach ($request['categories'] as $category => $values)
        {
            foreach ($values as $item => $quantity) // Butts => 3
            {
                // \Log::info([$category, $item, $quantity]);
                // reference column on the photos table to update eg. smoking_id
                $id     = $jsonDecoded->$category->id;
                // The current dynamic Class as a string
                $clazz  = $jsonDecoded->$category->class;
                // Reference the name of the column we want to edit
                $col    = $jsonDecoded->$category->types->$item->col;
                // Get the Class: App\Smoking
                $dynamicClassName = 'App\\Categories\\'.$clazz;
                // Does the photos table have a reference to the dynamic row id yet?
                if (is_null($photo->$id))
                {
                    $row = $dynamicClassName::create();
                    $photo->$id = $row->id;
                    $photo->save();
                } else {
                    $row = $dynamicClassName::find($photo->$id);
                }
                // Update the quantity on the dynamic table and save
                $row->$col = $quantity;
                $row->save();
                // TODO - Only reward XP on verification.
                $user->xp += $quantity;
                $user->save();
                // Update Leaderboards if user has changed privacy settings
                // todo - create different settings for maps
                if (($user->show_name == 1) || ($user->show_username == 1))
                {
                    $country = Country::find($photo->country_id);
                    $state = State::find($photo->state_id);
                    $city = City::find($photo->city_id);
                    Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
                    Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
                }
                $litterTotal += $quantity;
            } // end foreach item
        } // end foreach categories as category
        $photo->remaining = $request->presence;
        $photo->total_litter = $litterTotal;

        // Check if the User is a trusted user => photos do not require verification.
        if ($user->verification_required == 0)
        {
            $photo->verification = 1;
            $photo->verified = 2;
            event(new PhotoVerifiedByAdmin($photo->id));
        } else {
            // Bring the photo to an initial state of verification
            /* 0 for testing, 0.1 for production */
            $photo->verification = 0.1;
        }
        $photo->save();
    }

    /**
     * Convert Degrees, Minutes and Seconds to Lat, Long
     * Cheers to Hassan for this!
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
}
