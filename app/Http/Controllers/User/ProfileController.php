<?php

namespace App\Http\Controllers\User;

use App\Exports\CreateCSVExport;
use App\Http\Controllers\Controller;
use App\Jobs\EmailUserExportCompleted;
use App\Level;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Apply middleware to all of these routes
     */
    public function __construct ()
    {
        return $this->middleware('auth');

        parent::__construct();
    }

    /**
     * Dispatch a request to download the users data
     */
    public function download ()
    {
        $user = Auth::user();

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix.'/';  // 2020/10/25/unix/

        $path .= '_MyData_OpenLitterMap.csv';

        /* Dispatch job to create CSV file for export */
        (new CreateCSVExport(null, null, null, $user->id))
            ->queue($path, 's3', null, ['visibility' => 'public'])
            ->chain([
                // These jobs are executed when above is finished.
                new EmailUserExportCompleted($user->email, $path)
                // new ....job
            ]);

        return ['success' => true];
    }

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
        // Todo - Store this metadata in another table
        $totalUsers = User::count();

        $usersPosition = User::where('xp', '>', auth()->user()->xp)->count() + 1;

        $user = Auth::user();

        // Todo - Store this metadata in another table
        $totalPhotosAllUsers = Photo::count();
        // Todo - Store this metadata in another table
        $totalLitterAllUsers = Photo::sum('total_litter');

        $photoPercent = ($user->total_images / $totalPhotosAllUsers);
        $tagPercent = ($user->total_litter / $totalLitterAllUsers);

        // XP needed to reach the next level
        $nextLevelXp = Level::where('xp', '>=', $user->xp)->first()->xp;
        $requiredXp = $nextLevelXp - $user->xp;

        return [
            'totalUsers' => $totalUsers,
            'usersPosition' => $usersPosition,
            'tagPercent' => $tagPercent,
            'photoPercent' => $photoPercent,
            'requiredXp' => $requiredXp
        ];
    }
}
