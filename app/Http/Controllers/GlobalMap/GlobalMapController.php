<?php

namespace App\Http\Controllers\GlobalMap;

use App\Models\Photo;
use App\Traits\FilterPhotosByGeoHashTrait;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;

class GlobalMapController extends Controller
{
    use FilterPhotosByGeoHashTrait;

    /**
     * Return the Art data for the global map
     *
     * @return array points
     */
    public function artData(): array
    {
        $photos = Photo::query()
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
            ->where([
                ['verified', '>=', 2],
                ['art_id', '!=', null]
            ])
            ->get();

        return $this->photosToGeojson($photos);
    }

    /**
     * Get photos point data at zoom levels 16 or above
     *
     * @return array
     */
    public function index(): array
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
                'user' => function ($query) {
                    $query->where('users.show_name_maps', 1)
                        ->orWhere('users.show_username_maps', 1)
                        ->select('users.id', 'users.name', 'users.username', 'users.show_username_maps', 'users.show_name_maps');
                },
                'team' => function ($query) {
                    $query->select('teams.id', 'teams.name');
                }
            ]);

        if (request()->fromDate || request()->toDate) {
            $startDate = request()->fromDate && Carbon::hasFormat(request()->fromDate, 'Y-m-d')
                ? Carbon::createFromFormat('Y-m-d', request()->fromDate)->startOfDay()
                : Carbon::create(2017);
            $endDate = request()->toDate && Carbon::hasFormat(request()->toDate, 'Y-m-d')
                ? Carbon::createFromFormat('Y-m-d', request()->toDate)->endOfDay()
                : now()->addDay();
            $query->whereBetween('datetime', [$startDate, $endDate]);
        } else if (request()->year) {
            $query->whereYear('datetime', request()->year);
        }

        if (request()->username) {
            $query->whereHas('user', function ($q) {
                $q->where([
                    'users.show_username_maps' => 1,
                    'users.username' => request()->username
                ]);
            });
        }

        $photos = $this->filterPhotosByGeoHash(
            $query,
            request()->bbox,
            request()->layers ?: null
        )->get();

        return $this->photosToGeojson($photos);
    }
}
