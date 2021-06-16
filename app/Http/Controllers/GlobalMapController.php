<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\FilterPhotosByGeoHashTrait;

class GlobalMapController extends Controller
{
    use FilterPhotosByGeoHashTrait;

    /**
     * Get photos point data at zoom levels 16 or above
     *
     * Todo - Load unverified images + change image to grey/unverified when verification !== 2
     *
     * @return array
     */
    public function index (): array
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
                    'result_string' => $photo->verified >= 2 ? $photo->result_string : null,
                    'filename' => $photo->verified >= 2 ? $photo->filename : '/assets/images/waiting.png',
                    'datetime' => $photo->datetime,
                    'cluster'  => false,
                    'verified' => $photo->verified
                ]
            ];

            array_push($features, $feature);
        }

        $geojson['features'] = $features;

        return $geojson;
    }
}
