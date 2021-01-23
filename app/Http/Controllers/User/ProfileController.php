<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Get the users data for the given time period
     */
    public function geojson ()
    {
        // period
        if (request()->period === 'today') $period = now()->startOfDay();
        else if (request()->period === 'week') $period = now()->startOfWeek();
        else if (request()->period === 'month') $period = now()->startOfMonth();
        else if (request()->period === 'year') $period = now()->startOfYear();
        else if (request()->period === 'all') $period = '2017-01-01 00:00:00'; // Year OLM began

        // Todo - Pre-cluster each users photos
        $query = Photo::select('id', 'filename', 'datetime', 'lat', 'lon', 'model', 'result_string')
            ->where([
                ['user_id', auth()->user()->id],
                'verified' => 2
            ])
            ->whereDate('created_at', '>=', $period);

        // Note, we need a total_tags column as this does not contain brands
        // Note, we need to save this metadata into another table
        // $photos_count = $query->count();
        // $litter_count = $query->sum('total_litter');

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
                    // 'photo_id' => $photo->id,
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
            'geojson' => $geojson
        ];
    }

    /**
     * Get the total number of users, and the current users position
     *
     * To get the current position, we need to count how many users have more XP than current users
     */
    public function index ()
    {
        $totalUsers = User::count();
        $usersPosition = User::where('xp', '>', auth()->user()->xp)->count() + 1;

        // Todo - Store this metadata in another table
        $userPhotoCount = Photo::where('user_id', auth()->user()->id)->count();
        // Todo - Store this metadata in another table
        $userTagsCount = Photo::where('user_id', auth()->user()->id)->sum('total_litter');

        // Todo - Store this metadata in another table
        $totalPhotosAllUsers = Photo::count();
        // Todo - Store this metadata in another table
        $totalLitterAllUsers = Photo::sum('total_litter');

        $photoPercent = ($userPhotoCount / $totalPhotosAllUsers);
        $tagPercent = ($userTagsCount / $totalLitterAllUsers);

        return [
            'totalUsers' => $totalUsers,
            'usersPosition' => $usersPosition,
            'totalPhotos' => $userPhotoCount,
            'totalTags' => $userTagsCount,
            'tagPercent' => $tagPercent,
            'photoPercent' => $photoPercent
        ];
    }
}
