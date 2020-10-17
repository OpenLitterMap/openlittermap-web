<?php

namespace App\Http\Controllers;

use App\Models\Cluster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    /**
     * Get clusters for the global map
     *
     * @return geojson
     */
    public function index ()
    {
        // todo - bounding box, geohash

        $clusters = Cluster::where([
            'zoom' => request()->zoom
        ])->get();

        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => null
        ];

        $features = [];

        foreach ($clusters as $cluster)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$cluster->lat, $cluster->lon] // backwards?
                ],
                'properties' => [
                    'point_count' => $cluster->point_count,
                    'point_count_abbreviated' => $cluster->point_count_abbreviated,
                    'cluster' => true
                ]
            ];

            array_push($features, $feature);
        }

//        $features = json_encode($features, JSON_NUMERIC_CHECK);

        $geojson['features'] = $features;

        return $geojson;
    }
}
