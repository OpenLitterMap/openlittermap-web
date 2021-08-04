<?php

namespace App\Http\Controllers\GlobalMap;

use App\Models\Cluster;
use App\Traits\FilterClustersByGeohashTrait;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    use FilterClustersByGeohashTrait;

    /**
     * Get clusters for the global map
     *
     * @return array
     */
    public function index ()
    {
        // If the zoom is greater than 5, we want to filter clusters by geohash
        if (request()->zoom > 5)
        {
            $clusters = $this->filterClustersByGeoHash(request()->zoom, request()->bbox)->get();
        }
        else
        {
            // If the zoom is 2,3,4 -> get all clusters for this zoom level
            $clusters = Cluster::where([
                'zoom' => request()->zoom
            ])->get();
        }

        // We need to return geojson object to the frontend
        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => null
        ];

        $features = [];

        // Loop over all clusters and add each feature to the features array
        foreach ($clusters as $cluster)
        {
            $feature = [
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

            array_push($features, $feature);
        }

        $geojson['features'] = $features;

        return $geojson;
    }
}
