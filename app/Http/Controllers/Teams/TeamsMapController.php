<?php

namespace App\Http\Controllers\Teams;

use App\Models\Photo;
use Auth;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TeamsMapController extends Controller
{
    /**
     * Get the map data for all teams
     *
     * Todo - single team
     */
    public function index ()
    {
        $user = Auth::user(); // get team_ids

        // filter by teams
        $photos = Photo::where('verified', '>', 0)
            ->where('verified', 2)
            // whereIn team_id
            ->get();

        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => []
        ];

        // Populate geojson object
        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],

                'properties' => [
                    'photo_id' => $photo->id,
                    'img' => $photo->filename,
                    'model' => $photo->model,
                    'datetime' => $photo->datetime,
                    'latlng' => [$photo->lat, $photo->lon],
                    'text' => $photo->result_string
                ]
            ];

            array_push($geojson["features"], $feature);
        }

        json_encode($geojson, JSON_NUMERIC_CHECK);

        return [ 'geojson' => $geojson ];
    }
}
