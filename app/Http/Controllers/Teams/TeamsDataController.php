<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamsDataController extends Controller
{
    /**
     * Get the combined effort for 1 or all of the users teams for the time-period
     *
     * @return array
     */
    public function index ()
    {
        $team_ids = Auth::user()->teams->pluck('id')->toArray();

        // if 0, we want to use all team_ids
        // if its not 0, we only want the data for this team
        if (request()->team_id !== '0')
        {
            if (in_array(request()->team_id, $team_ids))
            {
                $team_ids = [request()->team_id];
            }
        }

        // period
        if (request()->period === 'today') $period = now()->startOfDay();
        else if (request()->period === 'week') $period = now()->startOfWeek();
        else if (request()->period === 'month') $period = now()->startOfMonth();
        else if (request()->period === 'year') $period = now()->startOfYear();
        else if (request()->period === 'all') $period = '2020-11-22 00:00:00'; // date of writing

        $query = Photo::whereIn('team_id', $team_ids)
            ->whereDate('created_at', '>=', $period)
            ->where('verified', 2);

        $photos_count = $query->count();
        $members_count = $query->distinct()->count('user_id');

        // might need photo.verified_at
        $litter_count = Photo::whereIn('team_id', $team_ids)
            ->whereDate('updated_at', '>=', $period)
            ->where('verified', 2)
            ->sum('total_litter');

        $geojson = [
            'type'      => 'FeatureCollection',
            'features'  => []
        ];

        $photos = $query->get();

        // Populate geojson object
        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],

                'properties' => [
                    'photo_id' => $photo->id,
                    'img' => $photo->filename,
                    'model' => $photo->model,
                    'datetime' => $photo->datetime,
                    'latlng' => [$photo->lat, $photo->lon],
                    'text' => $photo->result_string
                ]
            ];

            array_push($geojson["features"], $feature);
        }

        // json_encode($geojson, JSON_NUMERIC_CHECK);

        return [
            'photos_count' => $photos_count,
            'litter_count' => $litter_count,
            'members_count' => $members_count,
            'geojson' => $geojson
        ];
    }
}
