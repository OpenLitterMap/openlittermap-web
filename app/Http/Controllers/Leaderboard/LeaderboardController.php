<?php

namespace App\Http\Controllers\Leaderboard;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\{DB, Redis};

class LeaderboardController extends Controller
{
    private const PER_PAGE = 100;

    public function __invoke()
    {
        $timeFilter = request('timeFilter', 'all-time');
        $locationType = request('locationType');
        $locationId = request('locationId');
        $page = (int) request('page', 1);

        $start = ($page - 1) * self::PER_PAGE;
        $end = $start + self::PER_PAGE - 1;

        // For location-based queries, both params are required
        if ($locationType xor $locationId) {
            return [
                'success' => false,
                'msg' => 'Both locationType and locationId required for location filtering'
            ];
        }

        // Resolve scope
        $scope = $this->resolveScope($locationType, $locationId);
        $enumType = $locationType ? LocationType::fromString($locationType) : LocationType::Global;

        if ($locationType && !$enumType) {
            return ['success' => false, 'msg' => 'Invalid locationType'];
        }

        if ($timeFilter === 'all-time') {
            return $this->allTimeLeaderboard($scope, $enumType, (int) ($locationId ?? 0), $start, $end);
        }

        return $this->timeFilteredLeaderboard($timeFilter, $enumType, (int) ($locationId ?? 0), $start, $end);
    }

    private function resolveScope(?string $locationType, ?string $locationId): string
    {
        if (!$locationType || !$locationId) {
            return RedisKeys::global();
        }

        return match ($locationType) {
            'country' => RedisKeys::country((int) $locationId),
            'state' => RedisKeys::state((int) $locationId),
            'city' => RedisKeys::city((int) $locationId),
            default => RedisKeys::global(),
        };
    }

    private function allTimeLeaderboard(string $scope, LocationType $enumType, int $locationId, int $start, int $end): array
    {
        $key = RedisKeys::xpRanking($scope);

        // O(log N + M) — fast sorted set range
        $results = Redis::zRevRange($key, $start, $end, ['WITHSCORES' => true]);
        $total = (int) Redis::zCard($key);

        if (empty($results)) {
            return [
                'success' => true,
                'users' => [],
                'hasNextPage' => false,
                'total' => 0,
                'currentUserRank' => null,
            ];
        }

        $ranked = collect($results)->map(function ($xp, $userId) {
            return ['id' => (int) $userId, 'xp' => (float) $xp];
        })->values();

        $users = $this->formatUserData($ranked, $start);
        $currentUserRank = $this->getCurrentUserRank($key);

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1,
            'total' => $total,
            'currentUserRank' => $currentUserRank,
        ];
    }

    /**
     * Time-filtered leaderboard using per-user rows in the metrics table.
     * Works for all scopes (global, country, state, city).
     */
    private function timeFilteredLeaderboard(string $timeFilter, LocationType $enumType, int $locationId, int $start, int $end): array
    {
        $params = $this->resolveTimeParams($timeFilter);

        if (!$params) {
            return ['success' => false, 'msg' => 'Invalid time filter'];
        }

        $query = DB::table('metrics')
            ->where('timescale', $params['timescale'])
            ->where('location_type', $enumType->value)
            ->where('location_id', $locationId)
            ->where('user_id', '>', 0)
            ->where('year', $params['year'])
            ->where('month', $params['month']);

        if (isset($params['bucket_date'])) {
            $query->where('bucket_date', $params['bucket_date']);
        }

        $total = (clone $query)->where('xp', '>', 0)->count();

        $rows = $query
            ->where('xp', '>', 0)
            ->orderByDesc('xp')
            ->orderBy('user_id')
            ->offset($start)
            ->limit(self::PER_PAGE)
            ->select('user_id', 'xp')
            ->get();

        $ranked = $rows->map(function ($row) {
            return ['id' => (int) $row->user_id, 'xp' => (float) $row->xp];
        });

        $users = $this->formatUserData($ranked, $start);

        // Get current user's rank in this time period
        $currentUserRank = null;
        if (auth()->check()) {
            $baseQuery = DB::table('metrics')
                ->where('timescale', $params['timescale'])
                ->where('location_type', $enumType->value)
                ->where('location_id', $locationId)
                ->where('year', $params['year'])
                ->where('month', $params['month']);

            if (isset($params['bucket_date'])) {
                $baseQuery->where('bucket_date', $params['bucket_date']);
            }

            $userRow = (clone $baseQuery)->where('user_id', auth()->id())->first();
            if ($userRow && $userRow->xp > 0) {
                $currentUserRank = (clone $baseQuery)
                    ->where('user_id', '>', 0)
                    ->where('xp', '>', $userRow->xp)
                    ->count() + 1;
            }
        }

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1,
            'total' => $total,
            'currentUserRank' => $currentUserRank,
        ];
    }

    private function resolveTimeParams(string $timeFilter): ?array
    {
        $now = now()->utc();

        return match ($timeFilter) {
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

    /**
     * Get current user's rank from Redis ZSET.
     * ZREVRANK returns 0-indexed, so we add 1 to make it 1-indexed for display.
     */
    private function getCurrentUserRank(string $key): ?int
    {
        if (!auth()->check()) {
            return null;
        }

        $rank = Redis::zRevRank($key, (string) auth()->id());

        // ZREVRANK is 0-indexed; convert to 1-indexed for display
        return $rank !== null ? (int) $rank + 1 : null;
    }

    private function formatUserData($ranked, int $start): array
    {
        $userIds = $ranked->pluck('id')->toArray();

        if (empty($userIds)) {
            return [];
        }

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return $ranked->map(function ($data, $index) use ($users, $start) {
            $user = $users->get($data['id']);

            if (!$user) {
                return null;
            }

            $showTeamName = $user->active_team && $user->teams
                    ->where('pivot.team_id', $user->active_team)
                    ->first(function ($value) {
                        return $value->pivot->show_name_leaderboards ||
                            $value->pivot->show_username_leaderboards;
                    });

            return [
                'name' => $user->show_name ? $user->name : '',
                'username' => $user->show_username ? ('@' . $user->username) : '',
                'xp' => number_format($data['xp']),
                'global_flag' => $user->global_flag,
                'social' => !empty($user->social_links) ? $user->social_links : null,
                'team' => $showTeamName && $user->team ? $user->team->name : '',
                'rank' => $start + $index + 1
            ];
        })->filter()->values()->toArray();
    }
}
