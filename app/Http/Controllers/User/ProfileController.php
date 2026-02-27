<?php

namespace App\Http\Controllers\User;

use App\Enums\VerificationStatus;
use App\Exports\CreateCSVExport;
use App\Http\Controllers\Controller;
use App\Jobs\EmailUserExportCompleted;
use App\Models\CustomTag;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\LevelService;
use App\Services\Redis\RedisKeys;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProfileController extends Controller
{
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
            ->where('user_id', auth()->user()->id)
            ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED->value)
            ->with([
                'user:id,name,username,show_username_maps,show_name_maps,settings',
                'user.team:is_trusted',
                'team:id,name',
                'customTags:photo_id,tag',
            ])
            ->whereDate(request()->period, '>=', request()->start)
            ->whereDate(request()->period, '<=', request()->end)
            ->orderBy(request()->period, 'asc')
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
                    'coordinates' => [$photo->lat, $photo->lon]
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
     * Get comprehensive profile data for the authenticated user.
     */
    public function index(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->id;

        // Stats from Redis, with MySQL fallback for pre-v5 users
        $metrics = RedisMetricsCollector::getUserMetrics($userId);

        $uploads = $metrics['uploads'] ?: (int) $user->total_images;
        $litter = $metrics['litter'] ?: (int) $user->total_litter;
        $xp = $metrics['xp'] ?: (int) $user->xp;

        // Level
        $levelInfo = LevelService::getUserLevel($xp);

        if ($user->level != $levelInfo['level']) {
            $user->level = $levelInfo['level'];
            $user->save();
        }

        // Rank from Redis ZSET, with MySQL fallback
        $globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
        $rank = Redis::zRevRank($globalXpKey, (string) $userId);
        $totalRanked = (int) Redis::zCard($globalXpKey);

        if ($rank !== false) {
            $globalPosition = $rank + 1;
        } else {
            // Fallback: count users with more XP in MySQL
            $globalPosition = User::where('xp', '>', $xp)->count() + 1;
            $totalRanked = $totalRanked ?: User::where('xp', '>', 0)->count();
        }

        $percentile = $totalRanked > 0
            ? round((1 - ($globalPosition - 1) / $totalRanked) * 100, 1)
            : 0;

        // Global stats from Redis, with MySQL fallback
        $globalStats = Redis::hGetAll(RedisKeys::stats(RedisKeys::global()));
        $globalPhotos = (int) ($globalStats['photos'] ?? 0);
        $globalLitter = (int) ($globalStats['litter'] ?? 0);

        if ($globalPhotos === 0) {
            $globalPhotos = (int) Photo::where('is_public', true)->count();
            $globalLitter = (int) Photo::where('is_public', true)->sum('total_tags');
        }

        // Achievements
        $unlockedCount = DB::table('user_achievements')
            ->where('user_id', $userId)
            ->count();
        $totalAchievements = DB::table('achievements')->count();

        // Location counts from user's photos
        $locationCounts = Photo::where('user_id', $userId)
            ->whereNotNull('country_id')
            ->selectRaw('COUNT(DISTINCT country_id) as countries, COUNT(DISTINCT state_id) as states, COUNT(DISTINCT city_id) as cities')
            ->first();

        // Percentage of global contribution
        $photoPercent = ($uploads && $globalPhotos) ? round($uploads / $globalPhotos * 100, 2) : 0;
        $tagPercent = ($litter && $globalLitter) ? round($litter / $globalLitter * 100, 2) : 0;

        // Active team
        $team = null;
        if ($user->active_team) {
            $teamModel = $user->team;
            if ($teamModel) {
                $team = ['id' => $teamModel->id, 'name' => $teamModel->name];
            }
        }

        return [
            'user' => [
                'id' => $userId,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at->toIso8601String(),
                'member_since' => $user->created_at->format('F Y'),
                'global_flag' => $user->global_flag,
                'public_profile' => (bool) $user->public_profile,
                'show_name' => (bool) $user->show_name,
                'show_username' => (bool) $user->show_username,
                'show_name_maps' => (bool) $user->show_name_maps,
                'show_username_maps' => (bool) $user->show_username_maps,
                'previous_tags' => (bool) $user->previous_tags,
                'emailsub' => (bool) $user->emailsub,
            ],
            'stats' => [
                'uploads' => $uploads,
                'litter' => $litter,
                'xp' => $xp,
                'streak' => $metrics['streak'],
                'littercoin' => (int) ($user->total_littercoin ?? 0),
                'photo_percent' => $photoPercent,
                'tag_percent' => $tagPercent,
            ],
            'level' => $levelInfo,
            'rank' => [
                'global_position' => $globalPosition,
                'global_total' => $totalRanked,
                'percentile' => $percentile,
            ],
            'global_stats' => [
                'total_photos' => $globalPhotos,
                'total_litter' => $globalLitter,
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
            'team' => $team,
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
