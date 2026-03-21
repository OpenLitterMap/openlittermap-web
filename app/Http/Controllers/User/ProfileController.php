<?php

namespace App\Http\Controllers\User;

use App\Enums\LocationType;
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
use Illuminate\Support\Facades\Cache;
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
                'picked_up' => $user->picked_up === null ? null : (bool) $user->picked_up,
                'previous_tags' => (bool) $user->previous_tags,
                'public_photos' => (bool) $user->public_photos,
            ],
            'stats' => ['xp' => $xp],
            'level' => $levelInfo,
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

        // Refresh user to pick up any query-builder updates (e.g. MetricsService
        // syncs users.xp via User::where()->increment(), which doesn't update
        // the cached model attributes)
        $user->refresh();

        $stats = $this->resolveUserStats($userId, $user);
        $uploads = $stats['uploads'];
        $tags = $stats['tags'];
        $xp = $stats['xp'];

        // Level
        $levelInfo = LevelService::getUserLevel($xp);

        if ($user->level != $levelInfo['level']) {
            $user->level = $levelInfo['level'];
            $user->save();
        }

        // Rank from metrics table (all-time), consistent with LeaderboardController
        $totalRanked = Cache::remember('users:count', 3600, fn () => User::count());
        $globalPosition = $this->getGlobalRank($userId, (int) $user->xp);

        $percentile = $totalRanked > 0
            ? round((1 - ($globalPosition - 1) / $totalRanked) * 100, 1)
            : 0;

        // Global stats — cached 5 minutes (updated by MetricsService on every tag/upload)
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

        // Achievements
        $unlockedCount = DB::table('user_achievements')
            ->where('user_id', $userId)
            ->count();
        $totalAchievements = Cache::remember('achievements:count', 3600, fn () => DB::table('achievements')->count());

        // Location counts — cached 5 minutes, keyed by upload count so new uploads bust the cache
        $photoCount = $uploads ?: (int) Photo::where('user_id', $userId)->count();
        $locationCounts = Cache::remember("profile:{$userId}:locations:{$photoCount}", 300, fn () =>
            Photo::where('user_id', $userId)
                ->whereNotNull('country_id')
                ->selectRaw('COUNT(DISTINCT country_id) as countries, COUNT(DISTINCT state_id) as states, COUNT(DISTINCT city_id) as cities')
                ->first()
        );

        // Percentage of global contribution
        $photoPercent = ($uploads && $globalPhotos) ? round($uploads / $globalPhotos * 100, 2) : 0;
        $tagPercent = ($tags && $globalTags) ? round($tags / $globalTags * 100, 2) : 0;

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
                'picked_up' => $user->picked_up === null ? null : (bool) $user->picked_up,
                'previous_tags' => (bool) $user->previous_tags,
                'emailsub' => (bool) $user->emailsub,
                'prevent_others_tagging_my_photos' => (bool) $user->prevent_others_tagging_my_photos,
                'public_photos' => (bool) $user->public_photos,
            ],
            'stats' => [
                'uploads' => $uploads,
                'tags' => $tags,
                'xp' => $xp,
                'streak' => $stats['streak'],
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
                'total_tags' => $globalTags,
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
     * Resolve user stats from Redis with MySQL fallbacks.
     */
    private function resolveUserStats(int $userId, User $user): array
    {
        $redisMetrics = RedisMetricsCollector::getUserMetrics($userId);

        // Total tags from metrics table (single indexed row lookup)
        $metricsRow = DB::table('metrics')
            ->where('user_id', $userId)
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('year', 0)
            ->where('month', 0)
            ->first(['uploads', 'tags', 'xp']);

        $uploads = (int) ($metricsRow->uploads ?? 0);
        $tags = (int) ($metricsRow->tags ?? 0);
        $xp = (int) ($metricsRow->xp ?? 0);

        // Fallback to Redis/DB when no metrics row exists yet
        if (! $uploads) {
            $uploads = $redisMetrics['uploads'] ?: (int) Photo::where('user_id', $userId)->count();
        }
        if (! $xp) {
            $xp = $redisMetrics['xp'] ?: (int) $user->xp;
        }

        return [
            'uploads' => $uploads,
            'tags' => $tags,
            'xp' => $xp,
            'streak' => $redisMetrics['streak'],
        ];
    }

    /**
     * Get the user's global rank position from Redis ZREVRANK.
     * Falls back to users.xp count if user is not in the Redis ZSET.
     */
    private function getGlobalRank(int $userId, int $fallbackXp): int
    {
        $globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
        $rank = Redis::zRevRank($globalXpKey, (string) $userId);

        if ($rank !== false) {
            return $rank + 1; // 0-indexed → 1-indexed
        }

        return User::where('xp', '>', $fallbackXp)->count() + 1;
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
