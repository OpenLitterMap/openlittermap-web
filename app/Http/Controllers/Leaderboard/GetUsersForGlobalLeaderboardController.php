<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

class GetUsersForGlobalLeaderboardController extends Controller
{
    private const PER_PAGE = 100;

    /**
     * Get the first paginated section of the global leaderboard
     */
    public function __invoke ()
    {
        $timeFilter = null;
        $total = 1;
        $userIds = [];
        $queryFilter = "xp_redis";
        $year = null;
        $month = null;

        if (request()->has('timeFilter'))
        {
            $timeFilter = request('timeFilter');
        }

        if (request()->has('year'))
        {
            $year = request('year');

            if (request()->has('month'))
            {
                $month = request('month');
            }
        }

        // Get the current page
        $page = (int) request('page', 1); // 1, 2, 3...
        $start = ($page - 1) * self::PER_PAGE; // 0, 100, 200...
        $end = $start + self::PER_PAGE - 1; // 99, 199, 299...

        // Get the values we need, depending on the filters given
        if ($timeFilter !== null)
        {
            $timeFilterData = $this->getDataForTimeFilter($timeFilter, $start, $end);

            $total = $timeFilterData['total'];
            $userIds = $timeFilterData['userIds'];
            $queryFilter = $timeFilterData['queryFilter'];

            $users = $this->getUsersForTimeFilter($userIds, $queryFilter, $start);
        }
        else if ($year !== null && $month === null)
        {
            $yearData = $this->getDataForYear($year, $start, $end);

            $total = $yearData['total'];
            $userIds = $yearData['userIds'];
            $queryFilter = $yearData['queryFilter'];

            $users = $this->getUsersForCustomYear($userIds, $queryFilter, $start, $year);
        }
        else if ($year !== null && $month !== null)
        {
            $monthData = $this->getDataForMonth($year, $month, $start, $end);

            $total = $monthData['total'];
            $userIds = $monthData['userIds'];
            $queryFilter = $monthData['queryFilter'];

            $users = $this->getUsersForCustomMonth($userIds, $queryFilter, $start, $year, $month);
        }

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1
        ];
    }

    /**
     * @param $timeFilter
     * @param $start
     * @param $end
     * @return array
     */
    protected function getDataForTimeFilter ($timeFilter, $start, $end): array
    {
        $total = 0;
        $userIds = null;
        $queryFilter = 'xp_redis';

        if ($timeFilter === 'all-time')
        {
            $total = Redis::zcount('xp.users', '-inf', '+inf');
            $userIds = Redis::zrevrange("xp.users", $start, $end);
        }
        else
        {
            if ($timeFilter === 'today')
            {
                $year = now()->year;
                $month = now()->month;
                $day = now()->day;

                $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);

                $queryFilter = "todays_xp";
            }
            else if ($timeFilter === 'yesterday')
            {
                $year = now()->subDays(1)->year;
                $month = now()->subDays(1)->month;
                $day = now()->subDays(1)->day;

                $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);

                $queryFilter = "yesterdays_xp";
            }
            else if ($timeFilter === 'this-month')
            {
                $year = now()->year;
                $month = now()->month;

                $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);

                $queryFilter = "this_months_xp";
            }
            else if ($timeFilter === 'last-month')
            {
                $year = now()->subMonths(1)->year;
                $month = now()->subMonths(1)->month;

                $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);

                $queryFilter = "last_months_xp";
            }
            else if ($timeFilter === 'this-year')
            {
                $year = now()->year;

                $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);

                $queryFilter = "this_years_xp";
            }
            else if ($timeFilter === 'last-year')
            {
                $year = now()->year -1;

                $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);

                $queryFilter = "last_years_xp";
            }
        }

        return [
            'total' => $total,
            'userIds' => $userIds,
            'queryFilter' => $queryFilter
        ];
    }

    /**
     * Retrieve data based on the year filter.
     * @param $year
     * @param $start
     * @param $end
     * @return array
     */
    protected function getDataForYear ($year, $start, $end): array
    {
        $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
        $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);

        $queryFilter = "custom_year_xp";

        return [
            'total' => $total,
            'userIds' => $userIds,
            'queryFilter' => $queryFilter
        ];
    }

    /**
     * @param $year
     * @param $start
     * @param $end
     * @return array
     */
    protected function getDataForMonth ($year, $month, $start, $end): array
    {
        $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
        $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);

        $queryFilter = "custom_month_xp";

        return [
            'total' => $total,
            'userIds' => $userIds,
            'queryFilter' => $queryFilter
        ];
    }

    /**
     * @param $userIds
     * @param $queryFilter
     * @param $start
     * @return array
     */
    protected function getUsersForTimeFilter ($userIds, $queryFilter, $start)
    {
        return User::query()
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
    }

    /**
     *
     */
    protected function getUsersForCustomYear ($userIds, $queryFilter, $start, $year)
    {
        return User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->map(function (User $user, $index) use ($start, $queryFilter, $year) {

                $user->setAttribute('custom_year', $year);

                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

            // Not able to calculate rank here, but it is generated on the frontend
            return [
                'name' => $user->show_name ? $user->name : '',
                'username' => $user->show_username ? ('@' . $user->username) : '',
                'xp' => $user->custom_year_xp,
                'global_flag' => $user->global_flag,
                'social' => !empty($user->social_links) ? $user->social_links : null,
                'team' => $showTeamName ? $user->team->name : '',
//                 'rank' => $start + $index + 1
            ];
        })
        ->sortByDesc(function ($user) {
            return intval($user['xp']);
        })
        ->values()
        ->toArray();
    }

    /**
     *
     */
    protected function getUsersForCustomMonth ($userIds, $queryFilter, $start, $year, $month)
    {
        return User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->map(function (User $user, $index) use ($start, $queryFilter, $year, $month) {

                $user->setAttribute('custom_year', $year);
                $user->setAttribute('custom_month', $month);

                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

                // Not able to calculate rank here, but it is generated on the frontend
                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => $user->custom_month_xp,
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
//                 'rank' => $start + $index + 1
                ];
            })
            ->sortByDesc(function ($user) {
                return intval($user['xp']);
            })
            ->values()
            ->toArray();
    }
}
