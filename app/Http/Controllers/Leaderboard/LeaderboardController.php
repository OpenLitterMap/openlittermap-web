<?php

namespace App\Http\Controllers\Leaderboard;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

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

        // Build base query (shared by leaderboard list, total count, and rank)
        $baseQuery = DB::table('metrics')
            ->where('timescale', $params['timescale'])
            ->where('location_type', $enumType->value)
            ->where('location_id', (int) ($locationId ?? 0))
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

        $users = $this->formatUserData($ranked, $start);

        // Current user's rank
        $currentUserRank = null;
        if (auth()->check()) {
            $userRow = (clone $baseQuery)->where('user_id', auth()->id())->first();
            if ($userRow && $userRow->xp > 0) {
                $currentUserRank = (clone $baseQuery)
                    ->where('user_id', '>', 0)
                    ->where('xp', '>', $userRow->xp)
                    ->count() + 1;
            }
        }

        // Global counts (independent of filters)
        $activeUsers = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', 0)
            ->where('location_id', 0)
            ->where('user_id', '>', 0)
            ->where('xp', '>', 0)
            ->count();

        $totalUsers = User::count();

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $start + self::PER_PAGE,
            'total' => $total,
            'activeUsers' => $activeUsers,
            'totalUsers' => $totalUsers,
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
            ->with(['team:id,name', 'teams:id,name'])
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

            $showTeamName = $user->active_team && $user->teams
                    ->where('pivot.team_id', $user->active_team)
                    ->first(function ($value) {
                        return $value->pivot->show_name_leaderboards ||
                            $value->pivot->show_username_leaderboards;
                    });

            $result[] = [
                'user_id' => $data['id'],
                'public_profile' => (bool) $user->public_profile,
                'name' => $user->show_name ? $user->name : '',
                'username' => $user->show_username ? ('@' . $user->username) : '',
                'xp' => (int) $data['xp'],
                'global_flag' => $user->global_flag,
                'social' => !empty($user->social_links) ? $user->social_links : null,
                'team' => $showTeamName && $user->team ? $user->team->name : '',
                'rank' => $displayRank,
            ];
        }

        return $result;
    }
}
