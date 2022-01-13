<?php

namespace App\Http\Controllers\GlobalMap;

use App\Models\Cluster;
use App\Traits\FilterClustersByGeohashTrait;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    use FilterClustersByGeohashTrait;

    /**
     * Get clusters for the global map
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request): array
    {
        $clusters = $this->getClusters($request);

        $features = $this->getFeatures($clusters);

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * @param Request $request
     * @return Builder[]|Collection
     */
    protected function getClusters(Request $request)
    {
        $query = Cluster::query();

        if ($request->year) {
            $query->where('year', $request->year);
        } else {
            $query->whereNull('year');
        }

        // If the zoom is 2,3,4,5 -> get all clusters for this zoom level
        if ($request->zoom <= 5) {
            return $query->where(['zoom' => $request->zoom])->get();
        }

        return $this->filterClustersByGeoHash(
            $query,
            $request->zoom,
            $request->bbox
        )->get();
    }
}
