<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DisplayTagsOnMapController extends Controller
{
    /**
     * Create a geojson from custom_tag or brand
     *
     * @param Request $request
     * @return array
     */
    public function show (Request $request)
    {
        $photos = Photo::query();

        if ($request->has('custom_tag'))
        {
            $photos = $photos->whereHas('customTags', function (Builder $query) use ($request) {
                return $query->where('tag', $request->custom_tag);
            });
        }

        if ($request->has('custom_tags'))
        {
            $tags = explode(',', $request->custom_tags);

            $photos = $photos->whereHas('customTags', function (Builder $query) use ($tags) {
                return $query->whereIn('tag', $tags);
            });
        }

        if ($request->has('brand'))
        {
            $photos = $photos->whereHas('brands', function (Builder $query) use ($request) {
                return $query->whereNotNull($request->brand);
            });
        }

        $photos = $photos->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
            ])
            ->limit(5000)
            ->get();

        // Populate geojson object
        $features = [];

        foreach ($photos as $photo)
        {
            $name = $photo->user->show_name_maps ? $photo->user->name : null;
            $username = $photo->user->show_username_maps ? $photo->user->username : null;
            $team = $photo->team ? $photo->team->name : null;
            $filename = ($photo->user->is_trusted || $photo->verified >= 2) ? $photo->filename : '/assets/images/waiting.png';
            $resultString = $photo->verified >= 2 ? $photo->result_string : null;

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lat, $photo->lon]
                ],
                'properties' => [
                    'photo_id' => $photo->id,
                    'result_string' => $resultString,
                    'filename' => $filename,
                    'datetime' => $photo->datetime,
                    'time' => $photo->datetime,
                    'cluster' => false,
                    'verified' => $photo->verified,
                    'name' => $name,
                    'username' => $username,
                    'team' => $team,
                    'picked_up' => $photo->picked_up,
                    'social' => $photo->user->social_links,
                    'custom_tags' => $photo->customTags->pluck('tag')
                ]
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }
}
