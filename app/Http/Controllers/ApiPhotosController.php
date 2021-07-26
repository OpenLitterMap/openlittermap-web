<?php

namespace App\Http\Controllers;

use GeoHash;
use Carbon\Carbon;

use App\Jobs\UploadData;
use App\Jobs\Api\AddTags;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;

use App\Helpers\Post\UploadHelper;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;

class ApiPhotosController extends Controller
{
    protected $userId;

    /** @var UploadHelper */
    protected $uploadHelper;

    /**
     * Apply middleware to all of these routes
     *
     * @param UploadHelper $uploadHelper
     */
    public function __construct (UploadHelper $uploadHelper)
    {
        $this->uploadHelper = $uploadHelper;

        $this->middleware('auth:api');
    }

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
                'hashName' => NULL
            )),
        );
     *
     * @return array
     */
    public function store (Request $request) :array
    {
        $file = $request->file('photo');

        if ($file->getError() === 3)
        {
            return ['success' => false, 'msg' => 'error-3'];
        }

        $user = Auth::guard('api')->user();

        \Log::channel('photos')->info([
            'app_upload' => $request->all(),
            'user_id' => $user['id']
        ]);

        $model = ($request->has('model'))
            ? $request->model
            : 'Mobile app v2';

        $image = Image::make($file);

        $image->resize(500, 500);

        $image->resize(500, 500, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = $file->getClientOriginalName();

        $hashname = $file->hashName();

        $lat  = $request['lat'];
		$lon  = $request['lon'];
		$date = $request['date'];

		$explode = explode(':', $date);
        $y = $explode[0];
        $m = $explode[1];
        $d = substr($explode[2], 0, 2);

	    $filepath = $y.'/'.$m.'/'.$d.'/'.$hashname;

        // convert to YYYY-MM-DD hh:mm:ss format
        $date = Carbon::parse($date);

	    if (app()->environment('production'))
	    {
            $s3 = \Storage::disk('s3');

            $s3->put($filepath, file_get_contents($file), 'public');

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

            $image->save($public_path . '/' . $hashname);

            $imageName = config('app.url') . '/local-uploads/'.$y.'/'.$m.'/'.$d .'/'.$hashname;
        }

        /** Reverse Geocode the GPS coordinates from OpenStreetMap to get an array of key-value address pairs */
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

        $country = $this->uploadHelper->getCountryFromAddressArray($addressArray);
        $state = $this->uploadHelper->getStateFromAddressArray($country, $addressArray);
        $city = $this->uploadHelper->getCityFromAddressArray($country, $state, $addressArray);

        try
        {
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
                'remaining' => $request['presence'],
                'platform' => 'mobile',
                'geohash' => GeoHash::encode($lat, $lon),
                'address_array' => json_encode($addressArray)
            ]);
        }
        catch (\Exception $e)
        {
            \Log::info(['ApiPhotosController@store', $e->getMessage()]);
        }

        // Since a user can upload multiple photos at once,
        // we might get old values for xp, so we update the values directly
        // without retrieving them
        $user->update([
            'xp' => DB::raw('xp + 1'),
            'total_images' => DB::raw('total_images + 1')
        ]);

        $teamName = null;
        if ($user->team) $teamName = $user->team->name;

        // Broadcast an event to anyone viewing the Global Map
        event (new ImageUploaded(
            $city->city,
            $state->state,
            $country->country,
            $country->shortcode,
            $imageName,
            $teamName,
            $user['id'],
            $country->id,
            $state->id,
            $city->id
        ));

        // Move this to redis
        event(new IncrementPhotoMonth(
            $country->id,
            $state->id,
            $city->id,
            $date
        ));

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
		$userId = Auth::guard('api')->user()->id;

        \Log::channel('tags')->info([
            'dynamicUpdate' => 'mobile',
            'request' => $request->all()
        ]);

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
        $userId = Auth::guard('api')->user()->id;

        \Log::channel('tags')->info([
            'add_tags' => 'mobile',
            'request' => $request->all()
        ]);

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
