<?php

namespace App\Traits\GeoJson;

trait CreateGeoJsonPoints {
    /**
     *
     */
    public function createGeojsonPoints ($name, $features): array
    {
        $geojson = [
            'type' => 'FeatureCollection',
            "name" => $name,
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
