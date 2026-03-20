<?php

namespace App\Http\Controllers\Leaderboard;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class LeaderboardController extends Controller
{
    private const PER_PAGE = 100;

    public function __invoke()
    {
        $timeFilter = request('timeFilter', 'all-time');
        $locationType = request('locationType');
        $locationId = request('locationId');
        $page = (int) request('page', 1);

        // For location-based queries, both params are required
        if ($locationType xor $locationId) {
            return [
                'success' => false,
                'msg' => 'Both locationType and locationId required for location filtering'
            ];
        }

        $enumType = $locationType ? LocationType::fromString($locationType) : LocationType::Global;

        if ($locationType && !$enumType) {
            return ['success' => false, 'msg' => 'Invalid locationType'];
        }

        $params = $this->resolveTimeParams($timeFilter);

        if (!$params) {
            return ['success' => false, 'msg' => 'Invalid time filter'];
        }

        $start = ($page - 1) * self::PER_PAGE;

        // All-time uses Redis ZSETs (O(log n)); time-filtered uses MySQL metrics
        if ($timeFilter === 'all-time') {
            $result = $this->getAllTimeFromRedis($enumType, (int) ($locationId ?? 0), $start);
        } else {
            $result = $this->getTimeFilteredFromMySQL($params, $enumType, (int) ($locationId ?? 0), $start);
        }

        $users = $this->formatUserData($result['ranked'], $start);

        // Global counts (independent of filters)
        $activeUsers = Cache::remember('leaderboard:global:active_users', 300, fn () =>
            (int) Redis::zCard(RedisKeys::xpRanking(RedisKeys::global()))
        );

        $totalUsers = Cache::remember('users:count', 3600, fn () => User::count());

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $result['total'] > $start + self::PER_PAGE,
            'total' => $result['total'],
            'activeUsers' => $activeUsers,
            'totalUsers' => $totalUsers,
            'currentUserRank' => $result['currentUserRank'],
        ];
    }

    /**
     * All-time leaderboard from Redis ZSET — O(log n) for all operations.
     */
    private function getAllTimeFromRedis(LocationType $enumType, int $locationId, int $start): array
    {
        $scope = match ($enumType) {
            LocationType::Global => RedisKeys::global(),
            LocationType::Country => RedisKeys::country($locationId),
            LocationType::State => RedisKeys::state($locationId),
            LocationType::City => RedisKeys::city($locationId),
        };

        $key = RedisKeys::xpRanking($scope);
        $end = $start + self::PER_PAGE - 1;

        // ZREVRANGE returns [member => score] sorted by score DESC
        $results = Redis::zRevRange($key, $start, $end, ['WITHSCORES' => true]);
        $total = (int) Redis::zCard($key);

        $ranked = collect();
        foreach ($results as $userId => $xp) {
            $ranked->push(['id' => (int) $userId, 'xp' => (float) $xp]);
        }

        // Current user's rank from ZREVRANK (0-indexed)
        $currentUserRank = null;
        if (auth()->check()) {
            $rank = Redis::zRevRank($key, (string) auth()->id());
            if ($rank !== false) {
                $currentUserRank = $rank + 1;
            }
        }

        return [
            'ranked' => $ranked,
            'total' => $total,
            'currentUserRank' => $currentUserRank,
        ];
    }

    /**
     * Time-filtered leaderboard from MySQL metrics table.
     */
    private function getTimeFilteredFromMySQL(array $params, LocationType $enumType, int $locationId, int $start): array
    {
        $baseQuery = DB::table('metrics')
            ->where('timescale', $params['timescale'])
            ->where('location_type', $enumType->value)
            ->where('location_id', $locationId)
            ->where('year', $params['year'])
            ->where('month', $params['month']);

        if (isset($params['bucket_date'])) {
            $baseQuery->where('bucket_date', $params['bucket_date']);
        }

        $total = (clone $baseQuery)->where('user_id', '>', 0)->where('xp', '>', 0)->count();

        $rows = (clone $baseQuery)
            ->where('user_id', '>', 0)
            ->where('xp', '>', 0)
            ->orderByDesc('xp')
            ->orderBy('user_id')
            ->offset($start)
            ->limit(self::PER_PAGE)
            ->select('user_id', 'xp')
            ->get();

        $ranked = $rows->map(fn ($row) => ['id' => (int) $row->user_id, 'xp' => (float) $row->xp]);

        // Current user's rank
        $currentUserRank = null;
        if (auth()->check()) {
            $userRow = (clone $baseQuery)->where('user_id', auth()->id())->first();
            if ($userRow && $userRow->xp > 0) {
                $currentUserRank = (clone $baseQuery)
                    ->where('user_id', '>', 0)
                    ->where(function ($q) use ($userRow) {
                        $q->where('xp', '>', $userRow->xp)
                          ->orWhere(function ($q2) use ($userRow) {
                              $q2->where('xp', $userRow->xp)
                                 ->where('user_id', '<', auth()->id());
                          });
                    })
                    ->count() + 1;
            }
        }

        return [
            'ranked' => $ranked,
            'total' => $total,
            'currentUserRank' => $currentUserRank,
        ];
    }

    private function resolveTimeParams(string $timeFilter): ?array
    {
        $now = now()->utc();

        return match ($timeFilter) {
            'all-time' => [
                'timescale' => 0,
                'year' => 0,
                'month' => 0,
            ],
            'today' => [
                'timescale' => 1,
                'year' => $now->year,
                'month' => $now->month,
                'bucket_date' => $now->toDateString(),
            ],
            'yesterday' => [
                'timescale' => 1,
                'year' => $now->copy()->subDay()->year,
                'month' => $now->copy()->subDay()->month,
                'bucket_date' => $now->copy()->subDay()->toDateString(),
            ],
            'this-month' => [
                'timescale' => 3,
                'year' => $now->year,
                'month' => $now->month,
            ],
            'last-month' => [
                'timescale' => 3,
                'year' => $now->copy()->subMonth()->year,
                'month' => $now->copy()->subMonth()->month,
            ],
            'this-year' => [
                'timescale' => 4,
                'year' => $now->year,
                'month' => 0,
            ],
            'last-year' => [
                'timescale' => 4,
                'year' => $now->year - 1,
                'month' => 0,
            ],
            default => null,
        };
    }

    private function formatUserData($ranked, int $start): array
    {
        $userIds = $ranked->pluck('id')->toArray();

        if (empty($userIds)) {
            return [];
        }

        $users = User::query()
            ->with(['team:id,name,safeguarding', 'teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $result = [];
        $displayRank = $start;

        foreach ($ranked as $data) {
            $user = $users->get($data['id']);

            if (!$user) {
                continue;
            }

            $displayRank++;

            // Check team-level leaderboard privacy from pivot
            $teamPivot = $user->active_team
                ? $user->teams->where('pivot.team_id', $user->active_team)->first()
                : null;

            $showTeamName = $teamPivot && (
                $teamPivot->pivot->show_name_leaderboards ||
                $teamPivot->pivot->show_username_leaderboards
            );

            // Use pivot-level leaderboard privacy if available, fall back to global settings
            $showName = $teamPivot
                ? (bool) $teamPivot->pivot->show_name_leaderboards
                : (bool) $user->show_name;
            $showUsername = $teamPivot
                ? (bool) $teamPivot->pivot->show_username_leaderboards
                : (bool) $user->show_username;

            // Mask identity for safeguarded school teams
            $hasSafeguarding = $user->team && $user->team->safeguarding;

            $result[] = [
                'user_id' => $data['id'],
                'public_profile' => (bool) $user->public_profile,
                'name' => $hasSafeguarding ? null : ($showName ? $user->name : ''),
                'username' => $hasSafeguarding ? null : ($showUsername ? ('@' . $user->username) : ''),
                'xp' => (int) $data['xp'],
                'global_flag' => $hasSafeguarding ? null : $user->global_flag,
                'social' => $hasSafeguarding ? null : (!empty($user->social_links) ? $user->social_links : null),
                'team' => $showTeamName && $user->team ? $user->team->name : '',
                'rank' => $displayRank,
            ];
        }

        return $result;
    }
}
