<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Models\Photo;
use App\Suburb;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;

use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Dumping;
use App\Models\Litter\Categories\Industrial;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Categories\Drugs;
use App\Models\Litter\Categories\Sanitary;
use App\Models\Litter\Categories\Other;
use App\Models\Litter\Categories\Coastal;
use App\Models\Litter\Categories\Pathway;
use App\Models\Litter\Categories\Art;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\TrashDog;

use JavaScript;
use Illuminate\Http\Request;

class ReportsController extends Controller
{

	// Load the reporting page
    public function get() {

    	$needlephoto = Photo::find(149); // 10

		$lat = (double)$needlephoto->lat;
		$lon = (double)$needlephoto->lon;

		$suburblatlong[0] = $lat;
		$suburblatlong[1] = $lon;

		$suburblitter = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

    	$druglitter = Drugs::find(4); // 2

		$feature = array(
			'type' => 'Feature',
			'geometry' => array(
				'type' => 'Point',
				'coordinates' => array($needlephoto["lon"], $needlephoto["lat"])
			),

			'properties' => array(
			   'photo_id' => $needlephoto["id"],
			   'filename' => $needlephoto["filename"],
				  'model' => $needlephoto["model"],
			   'datetime' => $needlephoto["datetime"],
				    'lat' => $needlephoto["lat"],
				    'lon' => $needlephoto["lon"],
		       'verified' => $needlephoto["verified"],
			  'remaining' => $needlephoto["remaining"],
		   'display_name' => $needlephoto["display_name"],

				// data
			 'drugs' => $druglitter,
			)
		);

		array_push($suburblitter["features"], $feature);

		json_encode($suburblitter, JSON_NUMERIC_CHECK);

		// Todo - reformat these into 1 function
		$citylitter = self::buildCityLitter();
		$citylatlong =  $citylitter["latlong"];
		$citylitter = $citylitter[0];

		$statelitter = self::buildStateLitter();
		$statelatlong = $statelitter["latlong"];
		$statelitter = $statelitter[0];

		$countrylitter = self::buildCountryLitter();
		$countrylatlong = $countrylitter["latlong"];
		$countrylitter = $countrylitter[0];

		$globallitter = self::buildGlobalLitter();
		$globallatlong = $globallitter["latlong"];
		$globallitter = $globallitter[0];

		Javascript::put([
		   'suburblatlong' => $suburblatlong,
	         'citylatlong' => $citylatlong,
            'suburblitter' => $suburblitter,
              'citylitter' => $citylitter,
            'statelatlong' => $statelatlong,
             'statelitter' => $statelitter,
             'countrylatlong' => $countrylatlong,
             'countrylitter' => $countrylitter,
             'hex' => 500,
             'globallatlong' => $globallatlong,
             'globallitter' => $globallitter
		]);

		$countries = Country::where('id', '!=', '9999')->get();
		$states = State::all();
		$cities = City::all();
		$suburbs = Suburb::all();

    	return view('reports.guest', compact('countries', 'states', 'cities', 'suburbs'));
    }


    protected function buildCityLitter() {
		// get data
		$data = Photo::where([
			['verified', '>', 0],
			['city_id', 3] // 1
		])->orderBy('datetime', 'asc')->get();

		$randomPhoto = $data->random();

		$lat = (double)$randomPhoto->lat;
		$lon = (double)$randomPhoto->lon;

		$latlong[0] = $lat;
		$latlong[1] = $lon;

		// create FC object
		$geojson = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

		// Populate geojson object
		foreach($data as $c) {

			if ($c['smoking_id']) {
				$smoking = Smoking::find($c['smoking_id']);
			} else {
				$smoking = 'null';
			}
			if ($c['food_id']) {
				$food = Food::find($c['food_id']);
			} else {
				$food = 'null';
			}
			if ($c['coffee_id']) {
				$coffee = Coffee::find($c['coffee_id']);
			} else {
				$coffee = 'null';
			}
			if ($c['dumping_id']) {
				$dumping = Dumping::find($c['dumping_id']);
			} else {
				$dumping = 'null';
			}
			if ($c['industrial_id']) {
				$industrial = Industrial::find($c['industrial_id']);
			} else {
				$industrial = 'null';
			}
			if ($c['alcohol_id']) {
				$alcohol = Alcohol::find($c['alcohol_id']);
			} else {
				$alcohol = 'null';
			}
			if ($c['softdrinks_id']) {
				$softdrinks = SoftDrinks::find($c['softdrinks_id']);
			} else {
				$softdrinks = 'null';
			}
			if ($c['drugs_id']) {
				$drugs = Drugs::find($c['drugs_id']);
			} else {
				$drugs = 'null';
			}
			if ($c['sanitary_id']) {
				$sanitary = Sanitary::find($c['sanitary_id']);
			} else {
				$sanitary = 'null';
			}
			if ($c['other_id']) {
				$other = Other::find($c['other_id']);
			} else {
				$other = 'null';
			}
			if($c['coastal_id']) {
				$coastal = Coastal::find($c['coastal_id']);
			} else {
				$coastal = 'null';
			}
			if($c['pathways_id']) {
				$pathway = Pathway::find($c['pathways_id']);
			} else {
				$pathway = 'null';
			}

			$litterTotal = $c['total_litter'];

			$feature = array(
				'type' => 'Feature',
				'geometry' => array(
					'type' => 'Point',
					'coordinates' => array($c["lon"], $c["lat"])
				),

				'properties' => array(
					   'photo_id' => $c["id"],
					   'filename' => $c["filename"],
						  'model' => $c["model"],
					   'datetime' => $c["datetime"],
						    'lat' => $c["lat"],
						    'lon' => $c["lon"],
				       'verified' => $c["verified"],
					  'remaining' => $c["remaining"],
				   'display_name' => $c["display_name"],

						// data
						'smoking' => $smoking,
						   'food' => $food,
						 'coffee' => $coffee,
						'dumping' => $dumping,
					 'industrial' => $industrial,
						'alcohol' => $alcohol,
					 'softdrinks' => $softdrinks,
						  'drugs' => $drugs,
					   'sanitary' => $sanitary,
						  'other' => $other,
						'coastal' => $coastal,
						'pathway' => $pathway,
				   'total_litter' => $litterTotal
					)
				);

				if (User::findOrFail($c["user_id"])->show_name == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["fullname"] = $user->name;;
				}
				if (User::findOrFail($c["user_id"])->show_username == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["username"] = $user->username;
				}

				// Add features to feature collection array
				array_push($geojson["features"], $feature);
			}
			// return dd($geojson);
			json_encode($geojson, JSON_NUMERIC_CHECK);

			return [$geojson, 'latlong' => $latlong];
    }


    protected function buildStateLitter() {
		// get data
		$data = Photo::where([
			['verified', '>', 0],
			['state_id', 1] // 6
		])->orderBy('datetime', 'asc')->get();

		$randomPhoto = $data->random();

		$lat = (double)$randomPhoto->lat;
		$lon = (double)$randomPhoto->lon;

		$latlong[0] = $lat;
		$latlong[1] = $lon;

		// create FC object
		$geojson = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

		// Populate geojson object
		foreach($data as $c) {

			if ($c['smoking_id']) {
				$smoking = Smoking::find($c['smoking_id']);
			} else {
				$smoking = 'null';
			}
			if ($c['food_id']) {
				$food = Food::find($c['food_id']);
			} else {
				$food = 'null';
			}
			if ($c['coffee_id']) {
				$coffee = Coffee::find($c['coffee_id']);
			} else {
				$coffee = 'null';
			}
			if ($c['dumping_id']) {
				$dumping = Dumping::find($c['dumping_id']);
			} else {
				$dumping = 'null';
			}
			if ($c['industrial_id']) {
				$industrial = Industrial::find($c['industrial_id']);
			} else {
				$industrial = 'null';
			}
			if ($c['alcohol_id']) {
				$alcohol = Alcohol::find($c['alcohol_id']);
			} else {
				$alcohol = 'null';
			}
			if ($c['softdrinks_id']) {
				$softdrinks = SoftDrinks::find($c['softdrinks_id']);
			} else {
				$softdrinks = 'null';
			}
			if ($c['drugs_id']) {
				$drugs = Drugs::find($c['drugs_id']);
			} else {
				$drugs = 'null';
			}
			if ($c['sanitary_id']) {
				$sanitary = Sanitary::find($c['sanitary_id']);
			} else {
				$sanitary = 'null';
			}
			if ($c['other_id']) {
				$other = Other::find($c['other_id']);
			} else {
				$other = 'null';
			}
			if($c['coastal_id']) {
				$coastal = Coastal::find($c['coastal_id']);
			} else {
				$coastal = 'null';
			}
			if($c['pathways_id']) {
				$pathway = Pathway::find($c['pathways_id']);
			} else {
				$pathway = 'null';
			}

			$litterTotal = $c['total_litter'];

			$feature = array(
				'type' => 'Feature',
				'geometry' => array(
					'type' => 'Point',
					'coordinates' => array($c["lon"], $c["lat"])
				),

				'properties' => array(
					   'photo_id' => $c["id"],
					   'filename' => $c["filename"],
						  'model' => $c["model"],
					   'datetime' => $c["datetime"],
						    'lat' => $c["lat"],
						    'lon' => $c["lon"],
				       'verified' => $c["verified"],
					  'remaining' => $c["remaining"],
				   'display_name' => $c["display_name"],

						// data
						'smoking' => $smoking,
						   'food' => $food,
						 'coffee' => $coffee,
					    'dumping' => $dumping,
					 'industrial' => $industrial,
						'alcohol' => $alcohol,
					 'softdrinks' => $softdrinks,
						  'drugs' => $drugs,
					   'sanitary' => $sanitary,
						  'other' => $other,
						'coastal' => $coastal,
						'pathway' => $pathway,
				   'total_litter' => $litterTotal
					)
				);

				if (User::findOrFail($c["user_id"])->show_name == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["fullname"] = $user->name;;
				}
				if (User::findOrFail($c["user_id"])->show_username == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["username"] = $user->username;
				}

				// Add features to feature collection array
				array_push($geojson["features"], $feature);
			}
			// return dd($geojson);
			json_encode($geojson, JSON_NUMERIC_CHECK);

			return [$geojson, 'latlong' => $latlong];
    }

    protected function buildCountryLitter() {
		// get data
		$data = Photo::where([
			['verified', '>', 0],
			['country_id', 3] // 6
		])->orderBy('datetime', 'asc')->get();

		$randomPhoto = $data->random();

		$lat = (double)$randomPhoto->lat;
		$lon = (double)$randomPhoto->lon;

		$latlong[0] = $lat;
		$latlong[1] = $lon;

		// create FC object
		$geojson = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

		// Populate geojson object
		foreach($data as $c) {

			if ($c['smoking_id']) {
				$smoking = Smoking::find($c['smoking_id']);
			} else {
				$smoking = 'null';
			}
			if ($c['food_id']) {
				$food = Food::find($c['food_id']);
			} else {
				$food = 'null';
			}
			if ($c['coffee_id']) {
				$coffee = Coffee::find($c['coffee_id']);
			} else {
				$coffee = 'null';
			}
			if ($c['dumping_id']) {
				$dumping = Dumping::find($c['dumping_id']);
			} else {
				$dumping = 'null';
			}
			if ($c['industrial_id']) {
				$industrial = Industrial::find($c['industrial_id']);
			} else {
				$industrial = 'null';
			}
			if ($c['alcohol_id']) {
				$alcohol = Alcohol::find($c['alcohol_id']);
			} else {
				$alcohol = 'null';
			}
			if ($c['softdrinks_id']) {
				$softdrinks = SoftDrinks::find($c['softdrinks_id']);
			} else {
				$softdrinks = 'null';
			}
			if ($c['drugs_id']) {
				$drugs = Drugs::find($c['drugs_id']);
			} else {
				$drugs = 'null';
			}
			if ($c['sanitary_id']) {
				$sanitary = Sanitary::find($c['sanitary_id']);
			} else {
				$sanitary = 'null';
			}
			if ($c['other_id']) {
				$other = Other::find($c['other_id']);
			} else {
				$other = 'null';
			}
			if($c['coastal_id']) {
				$coastal = Coastal::find($c['coastal_id']);
			} else {
				$coastal = 'null';
			}
			if($c['pathways_id']) {
				$pathway = Pathway::find($c['pathways_id']);
			} else {
				$pathway = 'null';
			}

			$litterTotal = $c['total_litter'];

			$feature = array(
				'type' => 'Feature',
				'geometry' => array(
					'type' => 'Point',
					'coordinates' => array($c["lon"], $c["lat"])
				),

				'properties' => array(
					   'photo_id' => $c["id"],
					   'filename' => $c["filename"],
						  'model' => $c["model"],
					   'datetime' => $c["datetime"],
						    'lat' => $c["lat"],
						    'lon' => $c["lon"],
				       'verified' => $c["verified"],
					  'remaining' => $c["remaining"],
				   'display_name' => $c["display_name"],

						// data
						'smoking' => $smoking,
						   'food' => $food,
						 'coffee' => $coffee,
						'dumping' => $dumping,
					 'industrial' => $industrial,
						'alcohol' => $alcohol,
					 'softdrinks' => $softdrinks,
						  'drugs' => $drugs,
					   'sanitary' => $sanitary,
						  'other' => $other,
						'coastal' => $coastal,
						'pathway' => $pathway,
				   'total_litter' => $litterTotal
					)
				);

				if (User::findOrFail($c["user_id"])->show_name == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["fullname"] = $user->name;;
				}
				if (User::findOrFail($c["user_id"])->show_username == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["username"] = $user->username;
				}

				// Add features to feature collection array
				array_push($geojson["features"], $feature);
			}
			// return dd($geojson);
			json_encode($geojson, JSON_NUMERIC_CHECK);

			return [$geojson, 'latlong' => $latlong];
    }


    protected function buildGlobalLitter() {
		// get data
		$data = Photo::where([
			['verified', '>', 0],
			['country_id', 3] // 6
		])->orderBy('datetime', 'asc')->get();

		$randomPhoto = $data->random();

		$lat = (double)$randomPhoto->lat;
		$lon = (double)$randomPhoto->lon;

		$latlong[0] = $lat;
		$latlong[1] = $lon;

		// create FC object
		$geojson = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

		// Populate geojson object
		foreach($data as $c) {

			if ($c['smoking_id']) {
				$smoking = Smoking::find($c['smoking_id']);
			} else {
				$smoking = 'null';
			}
			if ($c['food_id']) {
				$food = Food::find($c['food_id']);
			} else {
				$food = 'null';
			}
			if ($c['coffee_id']) {
				$coffee = Coffee::find($c['coffee_id']);
			} else {
				$coffee = 'null';
			}
			if ($c['dumping_id']) {
				$dumping = Dumping::find($c['dumping_id']);
			} else {
				$dumping = 'null';
			}
			if ($c['industrial_id']) {
				$industrial = Industrial::find($c['industrial_id']);
			} else {
				$industrial = 'null';
			}
			if ($c['alcohol_id']) {
				$alcohol = Alcohol::find($c['alcohol_id']);
			} else {
				$alcohol = 'null';
			}
			if ($c['softdrinks_id']) {
				$softdrinks = SoftDrinks::find($c['softdrinks_id']);
			} else {
				$softdrinks = 'null';
			}
			if ($c['drugs_id']) {
				$drugs = Drugs::find($c['drugs_id']);
			} else {
				$drugs = 'null';
			}
			if ($c['sanitary_id']) {
				$sanitary = Sanitary::find($c['sanitary_id']);
			} else {
				$sanitary = 'null';
			}
			if ($c['other_id']) {
				$other = Other::find($c['other_id']);
			} else {
				$other = 'null';
			}
			if($c['coastal_id']) {
				$coastal = Coastal::find($c['coastal_id']);
			} else {
				$coastal = 'null';
			}
			if($c['pathways_id']) {
				$pathway = Pathway::find($c['pathways_id']);
			} else {
				$pathway = 'null';
			}

			$litterTotal = $c['total_litter'];

			$feature = array(
				'type' => 'Feature',
				'geometry' => array(
					'type' => 'Point',
					'coordinates' => array($c["lon"], $c["lat"])
				),

				'properties' => array(
					   'photo_id' => $c["id"],
					   'filename' => $c["filename"],
						  'model' => $c["model"],
					   'datetime' => $c["datetime"],
						    'lat' => $c["lat"],
						    'lon' => $c["lon"],
				       'verified' => $c["verified"],
					  'remaining' => $c["remaining"],
				   'display_name' => $c["display_name"],

						// data
						'smoking' => $smoking,
						   'food' => $food,
						 'coffee' => $coffee,
						'dumping' => $dumping,
					 'industrial' => $industrial,
						'alcohol' => $alcohol,
					 'softdrinks' => $softdrinks,
						  'drugs' => $drugs,
					   'sanitary' => $sanitary,
						  'other' => $other,
						'coastal' => $coastal,
						'pathway' => $pathway,
				   'total_litter' => $litterTotal
					)
				);

				if (User::findOrFail($c["user_id"])->show_name == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["fullname"] = $user->name;;
				}
				if (User::findOrFail($c["user_id"])->show_username == 1) {
					$user = User::findOrFail($c["user_id"]);
					$feature["properties"]["username"] = $user->username;
				}

				// Add features to feature collection array
				array_push($geojson["features"], $feature);
			}
			// return dd($geojson);
			json_encode($geojson, JSON_NUMERIC_CHECK);

			return [$geojson, 'latlong' => $latlong];
    }

}
