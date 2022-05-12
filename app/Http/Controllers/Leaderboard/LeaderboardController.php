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
    public function index()
    {
        // Get the current page
        $page = (int) request('page', 1); // 1, 2, 3...
        $start = ($page - 1) * self::PER_PAGE; // 0, 100, 200...
        $end = $start + self::PER_PAGE - 1; // 99, 199, 299...

        $total = Redis::zcount('xp.users', '-inf', '+inf');
        $userIds = Redis::zrevrange("xp.users", $start, $end);

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
