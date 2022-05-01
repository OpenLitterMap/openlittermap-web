<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class LeaderboardController extends Controller
{
    /**
     * Get the first paginated section of the global leaderboard
     */
    public function getPaginated ()
    {
        $perPage = 100;

        $start = 0;
        $end = 100;

        // Get the current page
        $currentPage = request('page') ?: 0;

        if ($currentPage > 0)
        {
            // 101
            $start = ($currentPage * $perPage) + 1;

            // 200
            $end = $end + ($currentPage * $perPage);
        }

        $userIds = Redis::zrevrange("xp.users", $start, $end);

        $users = User::whereIn('id', $userIds)
            ->select('id', 'show_name', 'show_username', 'name', 'username')
            ->where(function ($query) {
                $query->where('show_name', true)
                    ->orWhere('show_username', true);
            })
            ->limit($perPage)
            ->get();

        // Loop over our users to attach their rank by index
        foreach ($users as $index => $user)
        {
            if ($currentPage > 0)
            {
                $index = $index + ($currentPage * $perPage);
            }

            $user['rank'] = $index + 1;

            if (!$user->show_name) $user['name'] = null;

            if ($user->show_username) {
                $user['username'] = "@" . $user->username;
            } else {
                $user['username'] = null;
            }
        }

        return [
            'success' => true,
            'users' => $users
        ];
    }
}
