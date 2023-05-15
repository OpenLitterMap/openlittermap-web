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

        if (request()->has('timeFilter')) {
            $timeFilter = request('timeFilter');
        }

        // Get the current page
        $page = (int) request('page', 1); // 1, 2, 3...
        $start = ($page - 1) * self::PER_PAGE; // 0, 100, 200...
        $end = $start + self::PER_PAGE - 1; // 99, 199, 299...

        // Get the values we need, depending on the filters given
        if ($timeFilter === null || $timeFilter === 'all-time')
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
}
