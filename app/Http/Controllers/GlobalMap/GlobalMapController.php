<?php

namespace App\Http\Controllers\GlobalMap;

use App\Models\Photo;
use App\Traits\FilterPhotosByGeoHashTrait;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
            ])
            ->get();

        return $this->photosToGeojson($photos);
    }

    /**
     * Get photos point data at zoom levels 16 or above
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
            ->where('user_id', '!=', 5292) // temp
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
                'adminVerificationLog.admin' => function ($q) {
                    $q->addSelect('id', 'show_name', 'show_username')
                        ->addSelect(DB::raw('CASE WHEN show_name = 1 THEN name ELSE NULL END as name'))
                        ->addSelect(DB::raw('CASE WHEN show_username = 1 THEN username ELSE NULL END as username'));
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
        } elseif (request()->year) {
            $query->whereYear('datetime', request()->year);
        }

        if (request()->username)
        {
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
