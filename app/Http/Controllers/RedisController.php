<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RedisController extends Controller
{

	/**
	 * On homepage '/'
	 * pass the most recently uploaded items
	 */
    public function mostRecent() {

    	// incr number of vists to homepage
    	$visits = Redis::incr('visits:home');


    	Redis::ltrim('mostrecent', 0, 19);
    	$range = Redis::lrange('mostrecent', 0, 19);

    	// $filepath1 = $range[3];
    	// $filepath2 = $range[7];
    	// $filepath3 = $range[11];
    	// $filepath4 = $range[15];
    	// $filepath5 = $range[19];

    	// $road1 = $range[2];
    	// $road2 = $range[6];
    	// $road3 = $range[10];
    	// $road4 = $range[14];
    	// $road5 = $range[18];

    	// $city1 = $range[1];
    	// $city2 = $range[5];
    	// $city3 = $range[9];
    	// $city4 = $range[13];
    	// $city5 = $range[17];

    	// $country1 = $range[0];
    	// $country2 = $range[4];
    	// $country3 = $range[8];
    	// $country4 = $range[12];
    	// $country5 = $range[16];

        return view('pages.maplist', ['filepath1' => null, 'road1' => null, 'city1' => null, 'country1' => null, 'filepath2' => null, 'road2' => null, 'city2' => null, 'country2' => null, 'filepath3' => null, 'road3' => null, 'city3' => null, 'country3' => null, 'filepath4' => null, 'road4' => null, 'city4' => null, 'country4' => null, 'filepath5' => null, 'road5' => null, 'city5' => null, 'country5' => null]);



    	// return view('pages.maplist', ['filepath1' => $filepath1, 'road1' => $road1, 'city1' => $city1, 'country1' => $country1, 'filepath2' => $filepath2, 'road2' => $road2, 'city2' => $city2, 'country2' => $country2, 'filepath3' => $filepath3, 'road3' => $road3, 'city3' => $city3, 'country3' => $country3, 'filepath4' => $filepath4, 'road4' => $road4, 'city4' => $city4, 'country4' => $country4, 'filepath5' => $filepath5, 'road5' => $road5, 'city5' => $city5, 'country5' => $country5]);
    }


}
