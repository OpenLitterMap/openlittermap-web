<?php

namespace App\Http\Controllers;

use App\Country;
use App\State;
use App\City;

use App\CheckLocations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Events\ImageUploaded;
use App\Jobs\UploadData;

class ApiPhotosController extends Controller
{
	use CheckLocations;

    /**
     * Save a photo to the database
     */
    public function store (Request $request)
    {
        if ($request->has('model'))
        {
            $model = $request->model;
        }

        else
        {
            $model = 'Mobile app v2';
        }

		$user = Auth::guard('api')->user();

		$file = $request->file('photo'); 
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
        } else {
		    $imageName = 'test';
        }

        // todo - let horizon process address details as a Job.
	    $apiKey = "052c068e4a9306e34c87";
        $url =  "http://locationiq.org/v1/reverse.php?format=json&key=".$apiKey."&lat=".$lat."&lon=".$lon."&zoom=20";

        // The entire reverse geocoded result
        $revGeoCode = json_decode(file_get_contents($url), true);
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
            'remaining' => $request['remaining'],
            'platform' => 'mobile'
		]);

        event (new ImageUploaded($this->city, $this->state, $this->country, $imageName));

        if ($user->has_uploaded_today == 0) {
              $user->has_uploaded_today = 1;
              $user->has_uploaded_counter++;
              if ($user->has_uploaded_counter == 7) {
                $user->littercoin_allowance++;
                $user->has_uploaded_counter = 0;
              }
              $user->save();
        }

		return ['photo_id' => $photo->id];
    }


    /**
     * Save litter data to a recently uploaded photo
     */
    public function dynamicUpdate (Request $request)
    {
		$userId = Auth::guard('api')->user()->id;

        dispatch (new UploadData($request->all(), $userId));

        return ['msg' => 'dispatched'];
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
