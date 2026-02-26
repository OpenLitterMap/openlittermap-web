<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Location\City;

class MapController extends Controller
{
    /**
     * Load city-level photo GeoJSON data for the map.
     */
    public function getCity()
    {
        $city = urldecode(request()->city);

        $cityModel = City::where('city', $city)->firstOrFail();

        $query = Photo::query()
            ->where('city_id', $cityModel->id)
            ->where('is_public', true)
            ->where('verified', '>', 0)
            ->with(['user:id,name,username,show_name_maps,show_username_maps,social_links'])
            ->orderBy('datetime', 'asc');

        // Optional date range filter
        if (request()->min) {
            $minTime = \DateTime::createFromFormat('d:m:Y', str_replace('-', ':', request()->min))
                ->format('Y-m-d 00:00:00');
            $maxTime = \DateTime::createFromFormat('d:m:Y', str_replace('-', ':', request()->max))
                ->format('Y-m-d 23:59:59');

            $minTime = substr_replace($minTime, '2', 0, 1);
            $maxTime = substr_replace($maxTime, '2', 0, 1);

            $query->where('datetime', '>=', $minTime)
                  ->where('datetime', '<=', $maxTime);
        }

        $photoData = $query->get();

        if ($photoData->isEmpty()) {
            return [
                'center_map' => [0, 0],
                'map_zoom' => 13,
                'litterGeojson' => ['type' => 'FeatureCollection', 'features' => []],
                'hex' => request()->hex ?? 100,
            ];
        }

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [],
        ];

        foreach ($photoData as $photo) {
            $properties = [
                'photo_id' => $photo->id,
                'filename' => $photo->filename,
                'model' => $photo->model,
                'datetime' => $photo->datetime,
                'lat' => $photo->lat,
                'lon' => $photo->lon,
                'verified' => $photo->verified,
                'remaining' => $photo->remaining,
                'display_name' => $photo->display_name,
                'picked_up' => $photo->picked_up,
                'summary' => $photo->summary,
            ];

            if ($photo->user) {
                if ($photo->user->show_name_maps) {
                    $properties['name'] = $photo->user->name;
                }
                if ($photo->user->show_username_maps) {
                    $properties['username'] = $photo->user->username;
                }
                $properties['social'] = $photo->user->social_links;
            }

            $geojson['features'][] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat],
                ],
                'properties' => $properties,
            ];
        }

        return [
            'center_map' => [$photoData[0]->lat, $photoData[0]->lon],
            'map_zoom' => 13,
            'litterGeojson' => $geojson,
            'hex' => request()->hex ?? 100,
        ];
    }
}
