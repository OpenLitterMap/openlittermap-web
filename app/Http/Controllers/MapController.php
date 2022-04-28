<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use App\Models\Photo;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\DynamicLoading;

use Log;
use JavaScript;
use Carbon\Carbon;
use App\GlobalLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class MapController extends Controller
{
	// Get Leaderboard and location creator for each location
	use DynamicLoading;

	/**
	 * Load the City data, maybe pass a filtered city request.
	 */
	public function getCity ()
    {
        $country = urldecode(request()->country);
        $state = urldecode(request()->state);
        $city = urldecode(request()->city);

        $minFilt = null;
        $maxFilt = null;
        $hex = 100;

		if (request()->min)
		{
			$minFilt = str_replace('-', ':', request()->min);
			$maxFilt = str_replace('-', ':', request()->max);
			$hex = request()->hex;
		}

		$litterGeojson = self::buildGeojson($city, $minFilt, $maxFilt);

		return [
			  'center_map' => $this->latlong,
				'map_zoom' => 13,
		   'litterGeojson' => $litterGeojson,
				   	 'hex' => $hex
		];
	}

	/**
	 * Dynamically build GeoJSON data for web-mapping
	 */
	private function buildGeojson ($city, $minfilter = null, $maxfilter = null)
	{
		$cityId = City::where('city', $city)->first()->id;

		if ($minfilter)
		{
			$minTime = \DateTime::createFromFormat('d:m:Y', $minfilter)->format('Y-m-d 00:00:00'); // 0018-mm-dd 00:00:00
			$maxTime = \DateTime::createFromFormat('d:m:Y', $maxfilter)->format('Y-m-d 23:59:59');

			$minTime = substr_replace($minTime,'2',0,1); //  2018-mm-dd hh:mm:ss
		    $maxTime = substr_replace($maxTime,'2',0,1);

			$photoData = Photo::with([
				'smoking',
				'food',
				'coffee',
				'alcohol',
				'softdrinks',
				'sanitary',
				'other',
				'coastal',
				'brands',
				'dumping',
				'industrial',
//				 'art',
//				'trashdog',
				'user' => function ($q) {
					$q->where('show_name_maps', true)
                      ->orWhere('show_username_maps', true);
				}])->where([
                    ['city_id', $cityId],
                    ['verified', '>', 0],
                    ['datetime', '>=', $minTime],
                    ['datetime', '<=', $maxTime]
			])->orderBy('datetime', 'asc')->get();

			$this->getInitialPhotoLatLon($photoData[0]);
			$this->photoCount = $photoData->count();

		} else {

			$photoData = Photo::with([
				'smoking',
				'food',
				'coffee',
				'alcohol',
				'softdrinks',
				'sanitary',
				'other',
				'coastal',
				'brands',
				'dumping',
				'industrial',
//				 'art',
//				'trashdog',
				'user' => function ($q) {
					$q->where('show_name_maps', true)->orWhere('show_username_maps', true);
				}])->where([
					['city_id', $cityId],
					['verified', '>', 0]
				])->orderBy('datetime', 'asc')->get();

			$this->getInitialPhotoLatLon($photoData[0]);
			$this->photoCount = $photoData->count();
		}

		$geojson = array(
   			'type'      => 'FeatureCollection',
   			'features'  => array()
		);

		foreach ($photoData as $c)
		{
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
			   'result_string' => $c["result_string"],
			   'picked_up' => $c->picked_up,
                    'social' => $c->user ? $c->user->social_links : null,

					// data
					'smoking' => $c->smoking,
					   'food' => $c->food,
					 'coffee' => $c->coffee,
					'alcohol' => $c->alcohol,
				 'softdrinks' => $c->softdrinks,
					  'drugs' => $c->drugs,
				   'sanitary' => $c->sanitary,
					  'other' => $c->other,
					'coastal' => $c->coastal,
					'pathway' => $c->pathway,
//						'art' => $c->art,
					 'brands' => $c->brands,
					'dumping' => $c->dumping,
				 'industrial' => $c->industrial,
//				   'trashdog' => $c->trashdog,
			   'total_litter' => $c->total_litter
				)
			);

			if ($c->user)
			{
				if ($c->user->show_name_maps) {
					$feature["properties"]["fullname"] = $c->user->name;;
				}
				if ($c->user->show_username_maps) {
					$feature["properties"]["username"] = $c->user->username;;
				}
			}

			// Add features to feature collection array
			array_push($geojson["features"], $feature);
		}

		json_encode($geojson, JSON_NUMERIC_CHECK);

		return $geojson;
	}
}
