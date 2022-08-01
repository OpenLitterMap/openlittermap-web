<?php

namespace App\Http\Controllers\Cleanups;

use App\Http\Controllers\Controller;
use App\Models\Cleanups\Cleanup;
use Illuminate\Http\Request;

class GetCleanupsGeojsonController extends Controller
{
    /**
     * Return geojson array of cleanups
     */
    public function __invoke ()
    {
        $cleanups = Cleanup::all();

        $geojson = $this->createGeoJsonArray($cleanups);

        return [
            'success' => true,
            'geojson' => $geojson
        ];
    }

    /**
     * Helper function to create GeoJson from an array of features.
     *
     * @param $features
     * @return array
     */
    private function createGeoJsonArray ($features) : array
    {
        $geojson = [
            'type' => 'FeatureCollection',
            "name" => "OLM Cleanups",
            "crs" => [
                "type" => "name",
                "properties" => [
                    "name" => "urn:ogc:def:crs:OGC:1.3:CRS84"
                ]
            ],
            'features'  => []
        ];

        foreach ($features as $feature)
        {
            $geojson['features'][] = [
                'type' => 'Feature',
                'properties' => $feature,
                "geometry" => [
                    "type" => "Point",
                    "coordinates" => [$feature['lon'], $feature['lat']]
                ]
            ];
        }

        return $geojson;
    }
}
