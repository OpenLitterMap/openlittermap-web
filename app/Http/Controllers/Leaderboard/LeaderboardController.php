<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\Users\User;
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

        $start = ($page - 1) * self::PER_PAGE;
        $end = $start + self::PER_PAGE - 1;

        // For location-based queries, both params are required
        if ($locationType xor $locationId) {
            return [
                'success' => false,
                'msg' => 'Both locationType and locationId required for location filtering'
            ];
        }

        // Get user IDs based on location filter
        $userIds = $this->getUserIdsForScope($locationType, $locationId);

        // Get XP data for all users
        $userData = $this->getUserXpData($userIds, $timeFilter);

        // Sort by XP descending
        $sorted = collect($userData)
            ->filter(fn($data) => $data['xp'] > 0)
            ->sortByDesc('xp')
            ->values();

        // Paginate
        $paginated = $sorted->slice($start, self::PER_PAGE);

        // Get user details
        $users = $this->formatUserData($paginated, $start);

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $sorted->count() > $end + 1
        ];
    }

    private function getUserIdsForScope(?string $locationType, ?string $locationId): array
    {
        if (!$locationType || !$locationId) {
            // Global scope - get all users with stats
            $keys = Redis::keys('{u:*}:stats');
            return array_map(fn($key) => (int) preg_replace('/^\{u:(\d+)\}:stats$/', '$1', $key), $keys);
        }

        // Location scope - get users from location
        $column = match($locationType) {
            'country' => 'country_id',
            'state' => 'state_id',
            'city' => 'city_id',
            default => null
        };

        if (!$column) {
            return [];
        }

        return User::where($column, $locationId)
            ->pluck('id')
            ->toArray();
    }

    private function getUserXpData(array $userIds, string $timeFilter): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userData = [];

        // For all-time, just get from stats hash
        if ($timeFilter === 'all-time') {
            $pipeline = Redis::pipeline(function($pipe) use ($userIds) {
                foreach ($userIds as $userId) {
                    $pipe->hGet("{u:$userId}:stats", 'xp');
                }
            });

            foreach ($userIds as $i => $userId) {
                $xp = (float) ($pipeline[$i] ?? 0);
                if ($xp > 0) {
                    $userData[$userId] = ['id' => $userId, 'xp' => $xp];
                }
            }

            return $userData;
        }

        // For time-filtered queries, we need to calculate from photos
        // This is a limitation of the new structure - you'll need to query photos
        $dateRange = $this->getDateRange($timeFilter);

        if (!$dateRange) {
            return [];
        }

        // Get photos for users in date range and calculate XP
        $photos = \App\Models\Photo::whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select('user_id', 'xp')
            ->get();

        foreach ($photos as $photo) {
            $userId = $photo->user_id;
            if (!isset($userData[$userId])) {
                $userData[$userId] = ['id' => $userId, 'xp' => 0];
            }
            $userData[$userId]['xp'] += (float) $photo->xp;
        }

        return $userData;
    }

    private function getDateRange(string $timeFilter): ?array
    {
        $now = now();

        return match($timeFilter) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ],
            'yesterday' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay()
            ],
            'this-month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth()
            ],
            'last-month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth()
            ],
            'this-year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear()
            ],
            'last-year' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear()
            ],
            default => null
        };
    }

    private function formatUserData($paginated, int $start): array
    {
        $userIds = $paginated->pluck('id')->toArray();

        if (empty($userIds)) {
            return [];
        }

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return $paginated->map(function ($data, $index) use ($users, $start) {
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
