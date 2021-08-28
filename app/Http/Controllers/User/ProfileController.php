<?php

namespace App\Http\Controllers\User;

use App\Helpers\Get\User\GetUserDataHelper;
use App\Level;
use App\Models\Photo;
use App\Models\User\User;

use App\Exports\CreateCSVExport;
use App\Jobs\EmailUserExportCompleted;

use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    /**
     * Apply middleware to all of these routes
     */
    public function __construct ()
    {
        $this->middleware('auth');
    }

    /**
     * Dispatch a request to download the users data
     *
     * @return array
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
     *
     * Period created_at || datetime
     *
     * start null? yyyy-mm-dd
     * end null? yyyy-mm-dd
     *
     * @return array
     */
    public function geojson ()
    {
        // we might need this again
//        if (request()->period === 'today') $period = now()->startOfDay();
//        else if (request()->period === 'week') $period = now()->startOfWeek();
//        else if (request()->period === 'month') $period = now()->startOfMonth();
//        else if (request()->period === 'year') $period = now()->startOfYear();
//        else if (request()->period === 'all') $period = '2017-01-01 00:00:00'; // Year OLM began

        // Todo - Pre-cluster each users photos
        $query = Photo::select('id', 'filename', 'datetime', 'lat', 'lon', 'model', 'result_string', 'created_at')
            ->where([
                ['user_id', auth()->user()->id],
                'verified' => 2
            ])
            ->whereDate(request()->period, '>=', request()->start)
            ->whereDate(request()->period, '<=', request()->end);

        // Note, we need a total_tags column as this does not contain brands
        // Note, we need to save this metadata into another table
        // $photos_count = $query->count();
        // $litter_count = $query->sum('total_litter');

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
            'success' => true,
            'geojson' => $geojson
        ];
    }

    /**
     * Load extra data for the Users Profile Page
     *
     * This is also used to get extra data for a PublicProfile if allowed.
     *
     * Gets:
     * - total number of users,
     * - users position
     *
     * @return array
     */
    public function index () : array
    {
        $user = request()->has('username')
            ? User::where('username', request()->username)->first()
            : Auth::user();

        if (Auth::guest() && (!$user || !isset($user->settings) || !$user->settings->show_public_profile)) {
            return [
                'success' => false
            ];
        }

        $userData = GetUserDataHelper::get(
            $user->xp,
            $user->total_tags,
            $user->total_images
        );

        return [
            'success' => true,
            'totalUsers' => $userData->totalUsers,
            'usersPosition' => $userData->usersPosition,
            'tagPercent' => $userData->tagPercent,
            'photoPercent' => $userData->photoPercent,
            'requiredXp' => $userData->requiredXp
        ];
    }
}
