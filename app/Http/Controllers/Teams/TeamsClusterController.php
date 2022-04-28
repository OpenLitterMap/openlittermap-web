<?php

namespace App\Http\Controllers\Teams;

use App\Models\Photo;
use App\Models\TeamCluster;
use App\Traits\FilterClustersByGeohashTrait;

use App\Http\Controllers\Controller;
use App\Traits\FilterPhotosByGeoHashTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TeamsClusterController extends Controller
{
    use FilterClustersByGeohashTrait;
    use FilterPhotosByGeoHashTrait;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get clusters for the teams map
     *
     * @param Request $request
     * @return array
     */
    public function clusters(Request $request): array
    {
        if (!$request->team) {
            return [
                'type' => 'FeatureCollection',
                'features' => []
            ];
        }

        $clusters = $this->getClusters($request->team);

        $features = $this->getFeatures($clusters);

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * Get photos point data at zoom levels 16 or above
     *
     * @param Request $request
     * @return array
     */
    public function points(Request $request): array
    {
        $query = Photo::query()
            ->select(
                'id',
                'verified',
                'user_id',
                'team_id',
                'result_string',
                'filename',
                'geohash',
                'lat',
                'lon',
                'remaining',
                'datetime'
            )
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
            ])
            ->whereTeamId($request->team);

        $photos = $this->filterPhotosByGeoHash(
            $query,
            $request->bbox,
            $request->layers ?: null
        )->get();

        return $this->photosToGeojson($photos);
    }

    /**
     * @return Builder[]|Collection
     */
    protected function getClusters($teamId)
    {
        $query = TeamCluster::query()->whereTeamId($teamId);

        // If the zoom is 2,3,4,5 -> get all clusters for this zoom level
        if (request()->zoom <= 5) {
            return $query->where('zoom', request()->zoom)->get();
        }

        return $this->filterClustersByGeoHash(
            $query,
            request()->zoom,
            request()->bbox
        )->get();
    }
}
