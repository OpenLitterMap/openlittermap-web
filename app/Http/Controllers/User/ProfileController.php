<?php

namespace App\Http\Controllers\User;

use App\Exports\CreateCSVExport;
use App\Http\Controllers\Controller;
use App\Jobs\EmailUserExportCompleted;
use App\Level;
use App\Models\CustomTag;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
    public function download (Request $request)
    {
        $user = Auth::user();

        $dateFilter = $this->getDownloadDateFilter($request);

        $x     = new \DateTime();
        $date  = $x->format('Y-m-d');
        $date  = explode('-', $date);
        $year  = $date[0];
        $month = $date[1];
        $day   = $date[2];
        $unix  = now()->timestamp;

        $path = $year.'/'.$month.'/'.$day.'/'.$unix;  // 2020/10/25/unix/

        if (!empty($dateFilter)) {
            $path .= "_from_{$dateFilter['fromDate']}_to_{$dateFilter['toDate']}";
        }

        $path .= '_MyData_OpenLitterMap.csv';

        /* Dispatch job to create CSV file for export */
        (new CreateCSVExport(null, null, null, $user->id, $dateFilter))
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
        $photos = Photo::query()
            ->where([
                ['user_id', auth()->user()->id],
                'verified' => 2
            ])
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
            ])
            ->whereDate(request()->period, '>=', request()->start)
            ->whereDate(request()->period, '<=', request()->end)
            ->get();

        // Populate geojson object
        $features = [];
        foreach ($photos as $photo) {
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
                    'cluster' => false,
                    'verified' => $photo->verified,
                    'name' => $name,
                    'username' => $username,
                    'team' => $team,
                    'picked_up' => $photo->picked_up
                ]
            ];
        }

        return [
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => $features
            ]
        ];
    }

    /**
     * Get the total number of users, and the current users position
     * To get the current position, we need to count how many users have more XP than current users
     *
     * @return array
     */
    public function index ()
    {
        /** @var User $user */
        $user = Auth::user()->append('xp_redis');

        // Todo - Store this metadata in another table
        $totalUsers = User::count();

        $usersPosition = $user->position;

        // Todo - Store this metadata in Redis
        $totalPhotosAllUsers = Photo::count();
        // Todo - Store this metadata in Redis
        $totalTagsAllUsers = Photo::sum('total_litter') + CustomTag::count(); // this doesn't include brands

        $usersTotalTags = $user->total_tags;

        $photoPercent = ($user->total_images && $totalPhotosAllUsers) ? ($user->total_images / $totalPhotosAllUsers) : 0;
        $tagPercent = ($usersTotalTags && $totalTagsAllUsers) ? ($usersTotalTags / $totalTagsAllUsers) : 0;

        // XP needed to reach the next level
        $nextLevel = Level::where('xp', '>', $user->xp_redis)->first();
        $requiredXp = $nextLevel->xp - $user->xp_redis;
        $currentLevel = $nextLevel->level - 1;

        // Update the user's current level if needed
        if ($user->level != $currentLevel) {
            $user->level = $currentLevel;
            $user->save();
        }

        return [
            'totalUsers' => $totalUsers,
            'usersPosition' => $usersPosition,
            'tagPercent' => $tagPercent,
            'photoPercent' => $photoPercent,
            'requiredXp' => $requiredXp
        ];
    }

    /**
     * Returns an array of values
     * so that users can filter their own data
     *
     * @param Request $request
     * @return array
     */
    private function getDownloadDateFilter(Request $request): array
    {
        if (!$request->dateField || !($request->fromDate || $request->toDate)) {
            return [];
        }

        $fromDate = $request->fromDate
            ? Carbon::parse($request->fromDate)
            : Carbon::create(2017);
        $toDate = $request->toDate
            ? Carbon::parse($request->toDate)
            : now();
        return [
            'column' => $request->dateField,
            'fromDate' => $fromDate->toDateString(),
            'toDate' => $toDate->toDateString()
        ];
    }
}
