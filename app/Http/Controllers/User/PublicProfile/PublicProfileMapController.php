<?php

namespace App\Http\Controllers\User\PublicProfile;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;

class PublicProfileMapController extends Controller
{
    /**
     * When the users settings allow it,
     *
     * Return their data for the given date range.
     *
     * Todo:
     * - a lot of duplication here with PublicProfileController @ get user
     * - a lot of duplication here with ProfileController @ geojson
     */
    public function index ()
    {
        $user = User::select([
            'id',
            'username',
            'level',
            'xp',
            'photos_per_month'
        ])
        ->with(['settings' => function ($q) {
            $q->select('id', 'user_id', 'public_profile_show_map', 'show_public_profile');
        }])
        ->where([
            'username' => request()->username
        ])->first();

        if (!$user || !isset($user->settings) || !$user->settings->show_public_profile || !$user->settings->public_profile_show_map) {
            return redirect('/');
        }

        // Todo - Pre-cluster each users photos
        $query = Photo::select('id', 'filename', 'datetime', 'lat', 'lon', 'model', 'result_string', 'created_at')
            ->where([
                'user_id' => $user->id,
                ['verified', '>=', 2]
            ])
            ->whereDate(request()->period, '>=', request()->start)
            ->whereDate(request()->period, '<=', request()->end);

        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => []
        ];

        // Might be big...
        $photos = $query->get();

        // Populate geojson object
        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lat, $photo->lon]
                ],

                'properties' => [
                    'img' => $photo->filename,
                    'model' => $photo->model,
                    'datetime' => $photo->datetime,
                    'latlng' => [$photo->lat, $photo->lon],
                    'text' => $photo->result_string
                ]
            ];

            array_push($geojson["features"], $feature);
        }

        return [
            'success' => true,
            'geojson' => $geojson
        ];
    }
}
