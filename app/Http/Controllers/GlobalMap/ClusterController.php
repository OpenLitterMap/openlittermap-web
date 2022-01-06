<?php

namespace App\Http\Controllers\GlobalMap;

use App\Models\Cluster;
use App\Traits\FilterClustersByGeohashTrait;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClusterController extends Controller
{
    use FilterClustersByGeohashTrait;

    /**
     * Get clusters for the global map
     *
     * @return array
     */
    public function index(): array
    {
        $clusters = $this->getClusters();

        $features = $this->getFeatures($clusters);

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * @return Builder[]|Collection
     */
    protected function getClusters()
    {
        // If the zoom is 2,3,4,5 -> get all clusters for this zoom level
        if (request()->zoom <= 5) {
            return Cluster::where(['zoom' => request()->zoom])->get();
        }

        return $this->filterClustersByGeoHash(
            Cluster::query(),
            request()->zoom,
            request()->bbox
        )->get();
    }
}
