<?php

namespace App\Traits\GeoJson;

trait CreateGeoJsonPoints {
    /**
     * Convert a flat array of rows (each must contain lat & lon) into a
     * GeoJSON FeatureCollection.
     *
     * @param string $name
     * @param $features
     * @return array<string,mixed>
     */
    public function createGeojsonPoints (string $name, $features): array
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
            $f = (array)$feature;

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
