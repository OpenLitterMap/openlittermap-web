<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LeaderboardController extends Controller
{
    private const PER_PAGE = 100;

    /**
     * Get the first paginated section of the global leaderboard
     */
    public function __invoke ()
    {
        // Filter all data by "today", "yesterday", "last year" etc
        $timeFilter = null;

        // Filter all data by Location Type (eg Countries) and Id
        $locationType = null;
        $locationId = null;

        // Data to return
        $total = 1;
        $userIds = [];

        // Old key to get all global leaders
        // This will change if we are filtering by
        $queryFilter = "xp_redis";

        if (request()->has('timeFilter')) {
            $timeFilter = request('timeFilter');
        }

        // Get the current page
        $page = (int) request('page', 1); // 1, 2, 3...
        $start = ($page - 1) * self::PER_PAGE; // 0, 100, 200...
        $end = $start + self::PER_PAGE - 1; // 99, 199, 299...

        // Filter all users
        // Returns
        // - total
        // - userIds
        // - queryFilter
        $leaderboardData = $this->getGlobalLeaderboard(
            $timeFilter,
            $start,
            $end,
            $total,
            $userIds,
            $queryFilter,
            $locationType,
            $locationId
        );

        $total = $leaderboardData['total'];
        $userIds = $leaderboardData['userIds'];
        $queryFilter = $leaderboardData['queryFilter'];

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->append($queryFilter)
            ->sortByDesc($queryFilter)
            ->values()
            ->map(function (User $user, $index) use ($start, $queryFilter) {
                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => number_format($user->$queryFilter),
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
                    'rank' => $start + $index + 1
                ];
            })
            ->toArray();

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1
        ];
    }

    /**
     * Get data for the Global Leaderboard
     * All users, globally, not filtered by any location.
     *
     * @param $timeFilter
     * @param $start
     * @param $end
     * @param $total
     * @param $userIds
     * @param $queryFilter
     * @param $filterKey
     * @param $locationId
     * @return array
     */
    private function getGlobalLeaderboard (
        $timeFilter,
        $start,
        $end,
        $total,
        $userIds,
        $queryFilter,
        $locationType,
        $locationId
    ): array
    {
        // Users for global leaderboard
        // We can also use model definition: country, state, city if we have locationType
        $filterKey = "users";

        // Get Global Leaderboard for all users, all locations
        if ($timeFilter === null || $timeFilter === 'all-time')
        {
            $total = Redis::zcount('xp.users', '-inf', '+inf');
            $userIds = Redis::zrevrange("xp.users", $start, $end);
        }
        else
        {
            // Filter by time / location
            if ($locationType !== null)
            {
                $filterKey = $locationType;
            }

            if ($timeFilter === 'today')
            {
                $year = now()->year;
                $month = now()->month;
                $day = now()->day;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year:$month:$day", $start, $end);
                }

                $queryFilter = "todays_xp";
            }
            else if ($timeFilter === 'yesterday')
            {
                $year = now()->subDays(1)->year;
                $month = now()->subDays(1)->month;
                $day = now()->subDays(1)->day;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year:$month:$day", $start, $end);
                }

                $queryFilter = "yesterdays_xp";
            }
            else if ($timeFilter === 'this-month')
            {
                $year = now()->year;
                $month = now()->month;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year:$month", $start, $end);
                }

                $queryFilter = "this_months_xp";
            }
            else if ($timeFilter === 'last-month')
            {
                $year = now()->subMonths(1)->year;
                $month = now()->subMonths(1)->month;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year:$month", $start, $end);
                }

                $queryFilter = "last_months_xp";
            }
            else if ($timeFilter === 'this-year')
            {
                $year = now()->year;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year", $start, $end);
                }

                $queryFilter = "this_years_xp";
            }
            else if ($timeFilter === 'last-year')
            {
                $year = now()->year -1;

                if ($filterKey === "users") {
                    $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$filterKey:$locationId:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$filterKey:$locationId:$year", $start, $end);
                }

                $queryFilter = "last_years_xp";
            }
        }

        return [
            'total' => $total,
            'userIds' => $userIds,
            'queryFilter' => $queryFilter
        ];
    }
}
