<?php

namespace App\Traits;

use GeoHash;
use App\Models\Photo;
use App\Traits\GeohashTrait;

trait FilterPhotosByGeohashTrait
{
    use GeohashTrait;

    /**
     * Query our clusters by geohash
     *
     * For a specific zoom level, we want to return the bounding box of the clusters + neighbours
     *
     * @param $zoom int   -> zoom level of the browser
     * @param $bbox array -> [west|left, south|bottom, east|right, north|top]
     *
     * @return $query
     */
    public function filterPhotosByGeoHash (int $zoom, string $bbox)
    {
        $bbox = json_decode($bbox);

        // get center of the bbox
        $center_lat = ($bbox->top + $bbox->bottom) / 2;
        $center_lon = ($bbox->left + $bbox->right) / 2;

        // zoom level will determine what level of geohash precision to use
        $precision = $this->zoomToGeoHashPrecision[request()->zoom];

        // Get the center of the bounding box, as a geohash
        $center_geohash = GeoHash::encode($center_lat, $center_lon, $precision); // precision 0 will return the full geohash

        $geos = [];
        // get the neighbour geohashes from our center geohash
        $ns = $this->neighbors($center_geohash);
        foreach ($ns as $n) array_push($geos, $n);

        $query = Photo::query();

        // Build cluster query
        $query->where(function ($q) use ($geos)
        {
            foreach ($geos as $geo)
            {
                $q->orWhere([
                    'verified' => 2,
                    ['geohash', 'like', $geo . '%'] // starts with
                ]);
            }
        });

        return $query;
    }
}
