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
        $filter = null;
        $total = 1;
        $userIds = [];

        if (request()->has('filter')) {
            $filter = request('filter');
        }

        // Get the current page
        $page = (int) request('page', 1); // 1, 2, 3...
        $start = ($page - 1) * self::PER_PAGE; // 0, 100, 200...
        $end = $start + self::PER_PAGE - 1; // 99, 199, 299...

        // Get the values we need, depending on the filters given
        if ($filter === null || $filter === 'all-time')
        {
            $total = Redis::zcount('xp.users', '-inf', '+inf');
            $userIds = Redis::zrevrange("xp.users", $start, $end);
        }
        else
        {
            if ($filter === 'today')
            {
                $year = now()->year;
                $month = now()->month;
                $day = now()->day;

                $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
            }
            else if ($filter === 'yesterday')
            {
                $year = now()->subDays(1)->year;
                $month = now()->subDays(1)->month;
                $day = now()->subDays(1)->day;

                $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
            }
            else if ($filter === 'this-month')
            {
                $year = now()->year;
                $month = now()->month;

                $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
            }
            else if ($filter === 'last-month')
            {
                $year = now()->subMonths(1)->year;
                $month = now()->subMonths(1)->month;

                $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
            }
            else if ($filter === 'this-year')
            {
                $year = now()->year;

                $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
            }
            else if ($filter === 'last-year')
            {
                $year = now()->year -1;

                $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
            }
        }

        \Log::info($total);
        \Log::info($userIds);

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->append('xp_redis')
            ->sortByDesc('xp_redis')
            ->values()
            ->map(function (User $user, $index) use ($start) {
                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => number_format($user->xp_redis),
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
