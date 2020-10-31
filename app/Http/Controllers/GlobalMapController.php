<?php

namespace App\Http\Controllers;

use GeoHash;
use App\Models\Photo;

use Illuminate\Http\Request;
use App\Traits\FilterPhotosByGeohashTrait;

class GlobalMapController extends Controller
{
    use FilterPhotosByGeohashTrait;

    /**
     * Get photos point data at zoom levels 16 or above
     *
     * Todo - Load unverified images + change image to grey/unverified when verification !== 2
     */
    public function index ()
    {
        $photos = $this->filterPhotosByGeoHash(request()->zoom, request()->bbox)->get();

        // We need to return geojson object to the frontend
        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => null
        ];

        $features = [];

        // Loop over all clusters and add each feature to the features array
        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],
                'properties' => [
                    'result_string' => $photo->result_string,
                    'filename' => $photo->filename,
                    'datetime' => $photo->datetime,
                    'cluster'  => false
                ]
            ];

            array_push($features, $feature);
        }

        $geojson['features'] = $features;

        return $geojson;
    }
}
