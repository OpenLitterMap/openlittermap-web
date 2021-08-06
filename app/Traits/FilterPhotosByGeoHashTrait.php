<?php

namespace App\Traits;

use GeoHash;
use App\Models\Photo;
use App\Traits\GeohashTrait;

trait FilterPhotosByGeoHashTrait
{
    use GeohashTrait;

    /**
     * Query our clusters by geohash
     *
     * For a specific zoom level, we want to return the bounding box of the clusters + neighbours
     *
     * @param $zoom int          -> zoom level of the browser
     * @param string $bbox array -> [west|left, south|bottom, east|right, north|top]
     * @param null layers
     *
     * @return \Illuminate\Database\Eloquent\Builder $query
     */
    public function filterPhotosByGeoHash (int $zoom, string $bbox, $layers = null)
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

        $query = Photo::query()->select(
            'id',
            'verified',
            'user_id',
            'team_id',
            'result_string',
            'filename',
            'geohash',
            'lat',
            'lon',
            'datetime'
        );

        $query->with([
            'user' => function ($query) {
                $query->where('users.show_name_maps', 1)
                    ->orWhere('users.show_username_maps', 1)
                    ->select('users.id', 'users.name', 'users.username', 'users.show_username_maps', 'users.show_name_maps');
            },
            'team' => function ($query) {
                $query->select('teams.id', 'teams.name');
            }
        ]);

        // Build cluster query
        $query->where(function ($q) use ($geos)
        {
            foreach ($geos as $geo)
            {
                $q->orWhere([
                    ['geohash', 'like', $geo . '%'] // starts with
                ]);
            }
        });

        if ($layers)
        {
            $query->where(function ($q) use ($layers)
            {
                foreach ($layers as $index => $layer)
                {
                    ($index === 0)
                        ? $q->where($layer . "_id", '!=', null)
                        : $q->orWhere($layer . "_id", '!=', null);
                }

                return $q;
            });
        }

        return $query;
    }
}
