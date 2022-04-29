<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    /**
     * Get the first paginated section of the global leaderboard
     */
    public function getPaginated ()
    {
        $users = User::where('show_username', true)
            ->orWhere('show_name', true)
            ->orderBy('xp', 'desc')
            ->paginate(5);

        return [
            'success' => true,
            'paginated' => $users
        ];
    }
}
