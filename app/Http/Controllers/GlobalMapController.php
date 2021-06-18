<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\FilterPhotosByGeoHashTrait;

class GlobalMapController extends Controller
{
    use FilterPhotosByGeoHashTrait;

    /**
     * Get photos point data at zoom levels 16 or above
     *
     * Todo - Load unverified images + change image to grey/unverified when verification !== 2
     *
     * @return array
     */
    public function index (): array
    {
        $photos = $this->filterPhotosByGeoHash(request()->zoom, request()->bbox)
            ->join('users', function ($query) {
                $query->on('users.id', '=', 'photos.user_id')
                ->where('users.show_name_maps', 1)
                ->orWhere('users.show_username_maps', 1)
                ->select('users.name', 'users.username', 'users.show_username_maps', 'users.show_name_maps');
            })
            ->get();

        // We need to return geojson object to the frontend
        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => null
        ];

        $features = [];

        // Loop over all clusters and add each feature to the features array
        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],
                'properties' => [
                    'result_string' => $photo->verified >= 2 ? $photo->result_string : null,
                    'filename' => $photo->verified >= 2 ? $photo->filename : '/assets/images/waiting.png',
                    'datetime' => $photo->datetime,
                    'cluster'  => false,
                    'verified' => $photo->verified,
                    'username' => $photo->show_username_maps ? $photo->username : null,
                    'name'    => $photo->show_name_maps ? $photo->name : null,
                ]
            ];

            array_push($features, $feature);
        }

        $geojson['features'] = $features;

        return $geojson;
    }
}
