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
     * @return array
     */
    public function index (): array
    {
        $photos = $this->filterPhotosByGeoHash(
            request()->zoom,
            request()->bbox,
            request()->layers ?: null
        )->get();

        // We need to return geojson object to the frontend
        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => null
        ];

        $features = [];

        // Loop over all clusters and add each feature to the features array
        // Todo - Remove duplication as 1 user may have uploaded many photos
        foreach ($photos as $photo)
        {
            $showName = $showUsername = $teamName = false;

            if ($photo->user)
            {
                $showName = $photo->user->show_name_maps;
                $showUsername = $photo->user->show_username_maps;
            }

            if ($photo->team)
            {
                $teamName = $photo->team->name;
            }

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
                    'name'     => $showName ? $photo->user->name : null,
                    'username' => $showUsername ? $photo->user->username : null,
                    'team'     => $teamName ? $teamName : null
                ]
            ];

            array_push($features, $feature);
        }

        $geojson['features'] = $features;

        return $geojson;
    }
}
