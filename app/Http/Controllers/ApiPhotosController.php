<?php

namespace App\Http\Controllers;

use App\Events\Photo\IncrementPhotoMonth;
use GeoHash;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use App\CheckLocations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Events\ImageUploaded;
use App\Jobs\UploadData;
use App\Jobs\Api\AddTags;

class ApiPhotosController extends Controller
{
	use CheckLocations;

    /**
     * Save a photo to the database
     *
     * Todo - Accept the image and data and process it is a job,
     * Then return as quickly as possible.
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
                'hashName' => NULL,
            )),
        );
     */
    public function store (Request $request)
    {
        \Log::info(['app.upload', $request->all()]);

        $file = $request->file('photo');

        if ($file->getError() === 3)
        {
            return ['success' => false, 'msg' => 'error-3'];
        }

        $user = Auth::guard('api')->user();

        $model = ($request->has('model'))
            ? $request->model
            : 'Mobile app v2';

        $filename = $file->getClientOriginalName();

		$lat  = $request['lat'];
		$lon  = $request['lon'];
		$date = $request['date'];

		$explode = explode(':', $date);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

	    $filepath = $y.'/'.$m.'/'.$d.'/'.$filename;

        // convert to YYYY-MM-DD hh:mm:ss format
        $date = Carbon::parse($date);

	    if (app()->environment('production'))
	    {
            $s3 = \Storage::disk('s3');
            $s3->put($filepath, file_get_contents($file), 'public');
            $imageName = $s3->url($filepath);
        }
	    else $imageName = 'test';

        $apiKey = config('services.location.secret');
        $url =  "http://locationiq.org/v1/reverse.php?format=json&key=".$apiKey."&lat=".$lat."&lon=".$lon."&zoom=20";

        // The entire reverse geocoded result
        $revGeoCode = json_decode(file_get_contents($url), true);
        // \Log::info(['revGeoCode', $revGeoCode]);
        // The entire address as a string
        $display_name = $revGeoCode["display_name"];
        // Extract the address array as $key => $value pairs.
        $addressArray = $revGeoCode["address"];
        // \Log::info(["Address", $addressArray]);
        // Get the first 2 because keys are highly dynamic
        $location = array_values($addressArray)[0];
        $road = array_values($addressArray)[1];

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

	    $photo = $user->photos()->create([
			'filename' => $imageName,
			'datetime' => $date,
            'lat' => $lat,
            'lon' => $lon,
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
            'remaining' => $request['presence'],
            'platform' => 'mobile',
            'geohash' => GeoHash::encode($lat, $lon)
        ]);

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        event (new ImageUploaded($this->city, $this->state, $this->country, $this->countryCode, $imageName, $teamName));

        // Increment the { Month-Year: int } value for each location
        // Todo - this needs debugging
        // This should dispatch a job
        // Move this to redis
        event (new IncrementPhotoMonth($countryId, $stateId, $cityId, $date));

//        if ($user->has_uploaded_today === 0)
//        {
//              $user->has_uploaded_today = 1;
//              $user->has_uploaded_counter++;
//
//              if ($user->has_uploaded_counter == 7)
//              {
//                    $user->littercoin_allowance++;
//                    $user->has_uploaded_counter = 0;
//              }
//
//              $user->save();
//        }

		return ['success' => true, 'photo_id' => $photo->id];
    }

    /**
     * Save litter data to a recently uploaded photo
     *
     * version 1
     *
     * This is used to add tags to web images, and session photos
     */
    public function dynamicUpdate (Request $request)
    {
        \Log::info(['dynamicUpdate', $request->all()]);

		$userId = Auth::guard('api')->user()->id;

        dispatch (new UploadData($request->all(), $userId));

        return ['msg' => 'dispatched'];
    }

    /**
     * Save litter data to a recently uploaded photo
     *
     * version 1
     *
     * This is used by gallery photos
     */
    public function addTags (Request $request)
    {
        \Log::info(['addTags', $request->all()]);

        $userId = Auth::guard('api')->user()->id;

        dispatch (new AddTags($request->all(), $userId));

        return ['success' => true, 'msg' => 'dispatched'];
    }


    /**
     *  Check if the user has any available photos that are uploaded, but not tagged
     */
    public function check ()
    {
        $user = Auth::guard('api')->user();

        $photos = $user->photos()->where('verification', 0)->select('id', 'filename')->get();

        if ($photos) return ['photos' => $photos];

        return ['photos' => 'none'];
    }
}
