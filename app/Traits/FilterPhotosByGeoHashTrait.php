<?php

namespace App\Traits;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;

trait FilterPhotosByGeoHashTrait
{
    use GeohashTrait;

    /**
     * Query our clusters by geohash
     *
     * For a specific zoom level, we want to return the bounding box of the clusters + neighbours
     *
     * @param Builder $query
     * @param string $bbox array -> [west|left, south|bottom, east|right, north|top]
     * @param null layers
     *
     * @return Builder $query
     */
    public function filterPhotosByGeoHash(Builder $query, string $bbox, $layers = null): Builder
    {
        $bbox = json_decode($bbox);

        // get center of the bbox
        $center_lat = ($bbox->top + $bbox->bottom) / 2;
        $center_lon = ($bbox->left + $bbox->right) / 2;

        // zoom level will determine what level of geohash precision to use
        $precision = $this->getGeohashPrecision(request()->zoom);

        // Get the center of the bounding box, as a geohash
        $center_geohash = \GeoHash::encode($center_lat, $center_lon, $precision); // precision 0 will return the full geohash

        // get the neighbour geohashes from our center geohash
        $geos = array_values($this->neighbors($center_geohash));

        // Build cluster query
        $query->where(function ($q) use ($geos) {
            foreach ($geos as $geo) {
                $q->orWhere('geohash', 'like', $geo . '%');  // starts with
            }
        });

        if ($layers) {
            $query->where(function ($q) use ($layers) {
                foreach ($layers as $index => $layer) {
                    ($index === 0)
                        ? $q->where($layer . "_id", '!=', null)
                        : $q->orWhere($layer . "_id", '!=', null);
                }

                return $q;
            });
        }

        return $query;
    }

    /**
     * Convert our photos object into a geojson array
     *
     * @param $photos
     *
     * @return array
     */
    protected function photosToGeojson($photos): array
    {
        $features = $photos->map(function (Photo $photo) {
            $name = $photo->user->show_name_maps ? $photo->user->name : null;
            $username = $photo->user->show_username_maps ? $photo->user->username : null;
            $team = $photo->team ? $photo->team->name : null;
            $filename = ($photo->user->is_trusted || $photo->verified >= 2) ? $photo->filename : '/assets/images/waiting.png';
            $resultString = $photo->verified >= 2 ? $photo->result_string : null;

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lat, $photo->lon]
                ],
                'properties' => [
                    'photo_id' => $photo->id,
                    'result_string' => $resultString,
                    'filename' => $filename,
                    'datetime' => $photo->datetime,
                    'cluster' => false,
                    'verified' => $photo->verified,
                    'name' => $name,
                    'username' => $username,
                    'team' => $team,
                    'picked_up' => $photo->picked_up,
                    'social' => $photo->user->social_links,
                ]
            ];
        })->toArray();

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

}
