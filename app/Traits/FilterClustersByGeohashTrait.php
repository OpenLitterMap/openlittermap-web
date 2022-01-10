<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

trait FilterClustersByGeohashTrait
{
    use GeohashTrait;

    /**
     * Query our clusters by geohash
     *
     * For a specific zoom level, we want to return the bounding box of the clusters + neighbours
     *
     * @param Builder $query
     * @param $zoom int   -> zoom level of the browser
     * @param $bbox array -> [west|left, south|bottom, east|right, north|top]
     * @return Builder
     */
    public function filterClustersByGeoHash (Builder $query, int $zoom, string $bbox): Builder
    {
        $bbox = json_decode($bbox);

        // get center of the bbox
        $center_lat = ($bbox->top + $bbox->bottom) / 2;
        $center_lon = ($bbox->left + $bbox->right) / 2;

        // zoom level will determine what level of geohash precision to use
        $precision = $this->getGeohashPrecision($zoom);

        // Get the center of the bounding box, as a geohash
        $center_geohash = \GeoHash::encode($center_lat, $center_lon, $precision); // precision 0 will return the full geohash

        // get the neighbour geohashes from our center geohash
        $geos = array_values($this->neighbors($center_geohash));

        return $query
            ->where('zoom', $zoom)
            ->where(function ($q) use ($geos) {
                foreach ($geos as $geo) {
                    $q->orWhere('geohash', 'like', $geo . '%'); // starts with
                }
            });
    }

    /**
     * Converts the clusters into the format required by the map
     *
     * @param Collection $clusters
     * @return array
     */
    protected function getFeatures(Collection $clusters): array
    {
        return $clusters->map(function ($cluster) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$cluster->lon, $cluster->lat]
                ],
                'properties' => [
                    'point_count' => $cluster->point_count,
                    'point_count_abbreviated' => $cluster->point_count_abbreviated,
                    'cluster' => true
                ]
            ];
        })->toArray();
    }
}
