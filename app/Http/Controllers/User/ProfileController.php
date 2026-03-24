<?php

namespace App\Http\Controllers\User;

use App\Enums\LocationType;
use App\Enums\VerificationStatus;
use App\Exports\CreateCSVExport;
use App\Http\Controllers\Controller;
use App\Jobs\EmailUserExportCompleted;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\LevelService;
use App\Traits\ResolvesUserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    use ResolvesUserProfile;
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
        $allowedPeriods = ['created_at', 'datetime', 'updated_at'];
        $period = in_array(request()->period, $allowedPeriods, true)
            ? request()->period
            : 'created_at';

        $photos = Photo::query()
            ->where('user_id', auth()->user()->id)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
            ])
            ->whereDate($period, '>=', request()->start)
            ->whereDate($period, '<=', request()->end)
            ->orderBy($period, 'asc')
            ->get();

        // Populate geojson object
        $features = [];
        foreach ($photos as $photo) {
            $name = $photo->user->show_name_maps ? $photo->user->name : null;
            $username = $photo->user->show_username_maps ? $photo->user->username : null;
            $team = $photo->team ? $photo->team->name : null;
            $filename = ($photo->user->is_trusted || $photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value) ? $photo->filename : '/assets/images/waiting.png';
            $summary = $photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value ? $photo->summary : null;

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],
                'properties' => [
                    'photo_id' => $photo->id,
                    'summary' => $summary,
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
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => $features
            ]
        ];
    }

    /**
     * Get a public user profile by ID.
     */
    public function show(int $id): array
    {
        $user = User::findOrFail($id);

        if (! $user->public_profile) {
            return ['public' => false];
        }

        $stats = $this->resolveUserStats($id, $user);
        $uploads = $stats['uploads'];
        $tags = $stats['tags'];
        $xp = $stats['xp'];

        $levelInfo = LevelService::getUserLevel($xp);

        $totalRanked = Cache::remember('users:count', 3600, fn () => User::count());
        $globalPosition = $this->getGlobalRank($id, $xp);

        $percentile = $totalRanked > 0
            ? round((1 - ($globalPosition - 1) / $totalRanked) * 100, 1)
            : 0;

        $photoCount = $uploads ?: (int) Photo::where('user_id', $id)->count();
        $locationCounts = Cache::remember("profile:{$id}:public_locations:{$photoCount}", 300, fn () =>
            Photo::where('user_id', $id)
                ->where('is_public', true)
                ->whereNotNull('country_id')
                ->selectRaw('COUNT(DISTINCT country_id) as countries, COUNT(DISTINCT state_id) as states, COUNT(DISTINCT city_id) as cities')
                ->first()
        );

        $unlockedCount = DB::table('user_achievements')->where('user_id', $id)->count();
        $totalAchievements = Cache::remember('achievements:count', 3600, fn () => DB::table('achievements')->count());

        return [
            'public' => true,
            'user' => [
                'id' => $id,
                'name' => $user->show_name ? $user->name : null,
                'username' => $user->show_username ? $user->username : null,
                'avatar' => $user->avatar,
                'global_flag' => $user->global_flag,
                'member_since' => $user->created_at->format('F Y'),
            ],
            'stats' => [
                'uploads' => $uploads,
                'tags' => $tags,
                'xp' => $xp,
            ],
            'level' => $levelInfo,
            'rank' => [
                'global_position' => $globalPosition,
                'global_total' => $totalRanked,
                'percentile' => $percentile,
            ],
            'achievements' => [
                'unlocked' => $unlockedCount,
                'total' => $totalAchievements,
            ],
            'locations' => [
                'countries' => (int) ($locationCounts->countries ?? 0),
                'states' => (int) ($locationCounts->states ?? 0),
                'cities' => (int) ($locationCounts->cities ?? 0),
            ],
        ];
    }

    /**
     * Lightweight refresh — returns only user fields, XP, and level.
     * Used by REFRESH_USER() on app load and after uploads/tagging.
     */
    public function refresh(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $user->refresh();

        $xp = (int) $user->xp;
        $levelInfo = LevelService::getUserLevel($xp);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'global_flag' => $user->global_flag,
                'public_profile' => (bool) $user->public_profile,
                'show_name' => (bool) $user->show_name,
                'show_username' => (bool) $user->show_username,
                'show_name_maps' => (bool) $user->show_name_maps,
                'show_username_maps' => (bool) $user->show_username_maps,
                'picked_up' => $user->picked_up === null ? null : (bool) $user->picked_up,
                'previous_tags' => (bool) $user->previous_tags,
                'emailsub' => (bool) $user->emailsub,
                'prevent_others_tagging_my_photos' => (bool) $user->prevent_others_tagging_my_photos,
                'public_photos' => (bool) $user->public_photos,
            ],
            'stats' => ['xp' => $xp],
            'level' => $levelInfo,
        ];
    }

    /**
     * Get comprehensive profile data for the authenticated user.
     *
     * Builds on the fast core profile (buildFullProfileData) and layers on
     * heavier SPA-only data: streak, percentages, locations.
     */
    public function index(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->id;

        $t0 = microtime(true);
        $data = $this->buildFullProfileData($user);
        $t1 = microtime(true);

        // Streak only — don't re-run resolveUserStatsLight (already called by buildFullProfileData)
        $userScope = \App\Services\Redis\RedisKeys::user($userId);
        $data['stats']['streak'] = $this->calculateStreak($userScope, $userId);
        $t2 = microtime(true);

        // Global stats + percentages — cached 5min, only for SPA dashboard
        [$globalPhotos, $globalTags] = Cache::remember('profile:global_stats', 300, function () {
            $globalRow = DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->first(['uploads', 'tags']);

            $photos = (int) ($globalRow->uploads ?? 0)
                ?: (int) Photo::where('is_public', true)->count();
            $tags = (int) ($globalRow->tags ?? 0);

            return [$photos, $tags];
        });

        $uploads = $data['stats']['uploads'];
        $tags = $data['stats']['tags'];
        $data['stats']['photo_percent'] = ($uploads && $globalPhotos) ? round($uploads / $globalPhotos * 100, 2) : 0;
        $data['stats']['tag_percent'] = ($tags && $globalTags) ? round($tags / $globalTags * 100, 2) : 0;
        $t3 = microtime(true);

        // Location counts — cached 5min, keyed by upload count
        $photoCount = $uploads ?: (int) Photo::where('user_id', $userId)->count();
        $locationCounts = Cache::remember("profile:{$userId}:locations:{$photoCount}", 300, fn () =>
            Photo::where('user_id', $userId)
                ->whereNotNull('country_id')
                ->selectRaw('COUNT(DISTINCT country_id) as countries, COUNT(DISTINCT state_id) as states, COUNT(DISTINCT city_id) as cities')
                ->first()
        );

        $data['locations'] = [
            'countries' => (int) ($locationCounts->countries ?? 0),
            'states' => (int) ($locationCounts->states ?? 0),
            'cities' => (int) ($locationCounts->cities ?? 0),
        ];
        $t4 = microtime(true);

        // Temporary timing — remove after diagnosis
        $data['_timing'] = [
            'core_profile_ms' => round(($t1 - $t0) * 1000),
            'streak_ms' => round(($t2 - $t1) * 1000),
            'global_stats_ms' => round(($t3 - $t2) * 1000),
            'locations_ms' => round(($t4 - $t3) * 1000),
            'total_ms' => round(($t4 - $t0) * 1000),
        ];

        return $data;
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
