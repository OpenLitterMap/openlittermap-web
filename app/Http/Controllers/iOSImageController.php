<?php

namespace App\Http\Controllers;

use Auth;
use Image;
use JWTAuth;
use App\Models\User\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class iOSImageController extends Controller
{


    /*
     * Store an image and its attributes from an iOS post request
     */
    public function store(Request $request) {

    	// verify jwt

        try {

            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent'], $e->getStatusCode());

        }

        // the token is valid and we have found the user via the sub claim
        // return response()->json(compact('user'));

    	// check how many images are being sent at once

    	// $arrayOfDicts = $request["myJson"];

    	// return $arrayOfDicts;

    	// $data = file_get_contents($request);

    	// $json = json_decode($data);

    	// var_dump($json);
    	// var_dump($request);


        // $this->validate($request, [
        //     'file' => 'required|mimes:jpg,png,jpeg'
        // ]);]


        $file = $request->file('file');

    	// $json = file_get_contents("php://input");

    	// return $json;


        // Get the Array of Dictionaries from the request
        // each array of dicts represents 1 image
        // the user may upload multiple images
        // this gets passed in as a String
        $arrayOfDictString = $request["myJson"];

        // explode the above string by -> ["
        // eg the string is
        // [["lat": 53.1, "lon": -8.0], ["lat": 52.4, "lon": -8.3]...]
        $phpArray = explode('[', $arrayOfDictString);

        // the array might have some

        $newArray = [];


        // return [gettype($phpArray), $phpArray, sizeof($phpArray)];

        // 					   int,   string
        foreach ($phpArray as $key => $value) {

        	// remove
        	if ($value != "") {
        		$newArray[$key] = $value;
        	}
        }

        // return $newArray;

        $dateTime = "";
        $filename = "";
        $lat = "";
        $long = "";
        $needles = 0;
        $wipes = 0;
        $spoons = 0;
        $packaging = 0;
        $bottles = 0;
        $tinfoil = 0;
        $fullpackage = 0;
        $barrels = 0;
        $bins = 0;
        $tops = 0;

        // return $newArray;  // returns all instances of AoD

        // 					   int     str
        foreach ($newArray as $key => $value) {

        	// 2, "long": "-8.49885500", "lat": "...."....

        	$valueDictionary = explode(", ", $value);

        	// return $valueDictionary[0]; // "long": "-8.11...."

        	// return [sizeof($valueDictionary), $valueDictionary];

        	for ($dictionary = 0; $dictionary < sizeof($valueDictionary); $dictionary++) {

        		// current dict is also a string
        		$current_dictionary = $valueDictionary[$dictionary];

        		// return [gettype($current_dictionary), $current_dictionary, strlen($current_dictionary)];

        		$exploded_dictionary = explode(': ', $current_dictionary);

        		// foreach ($exploded_dictionary as $a => $b) {
        		if (substr($exploded_dictionary[0], 1, -1) == "long") {
        			$long = substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "lat") {
        			$lat = substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "dateTime") {
        			$dateTime = substr($exploded_dictionary[1], 1, -2);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "imageName") {
        			$filename = substr($exploded_dictionary[1], 1, -2);
        		}

				if (substr($exploded_dictionary[0], 1, -1) == "needles") {
        			$needles = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "citricAcidWipes") {
        			$wipes = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "spoons") {
        			$spoons = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "drugPackaging") {
        			$packaging = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "bottles") {
        			$bottles = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "usedTinfoil") {
        			$tinfoil = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "unusedPackaging") {
        			$fullpackage = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "syringeBarrel") {
        			$barrels = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "drugBin") {
        			$bins = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        		if (substr($exploded_dictionary[0], 1, -1) == "needleCaps") {
        			$tops = (int)substr($exploded_dictionary[1], 1, -1);
        		}

        	}
        	// return [$lat, $long, $dateTime, $needles]; // works

	        // // reverse geocode
	        // $apiKey = "052c068e4a9306e34c87";
	        // $url =  "http://locationiq.org/v1/reverse.php?format=json&key=" . $apiKey . "&lat=" . $lat . "&lon=" . $long . "&zoom=20";

	        // // The entire reverse geocoded result
	        // $revGeoCode = json_decode(file_get_contents($url), true);

	        // return $reve

	        // // The entire address as a single string
	        // $display_name = $revGeoCode["display_name"];

	        // // Extract the address array
	        // $addressArray = $revGeoCode["address"];

	        // return $addressArray;


			$user->photos()->create(['filename' => '/uploads/' . $user->id . '/photos/' . $filename . time() . ".png", 'datetime' => $dateTime, 'lat' => $lat, 'lon' => $long, 'display_name' => 'test', 'location' => "test", 'road' => "test", 'suburb' => "test", 'city' => "test", 'county' => "test", 'state_district' => "test", 'country' => "test", 'country_code' => "test", 'model' => 'iPhone', 'country_id' => 999, 'city_id' => 998, 'needles' => $needles, 'wipes' => $wipes, 'tops' => $tops, 'packaging' => $packaging, 'waterbottle' => $bottles, 'spoons' => $spoons, 'usedtinfoil' => $tinfoil, 'fullpackage' => $fullpackage, 'barrels' => $barrels, 'needlebin' => $bins]);

		}

		$user->save();

        return "success";
    }
}
