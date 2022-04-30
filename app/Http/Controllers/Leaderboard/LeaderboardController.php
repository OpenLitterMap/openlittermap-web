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
        $perPage = 100;

        $users = User::where('show_username', true)
            ->orWhere('show_name', true)
            ->orderBy('xp', 'desc')
            ->paginate($perPage);

        // Get the current page
        $currentPage = request('page') ?: 1;

        // Decrement the current page to use 0-index
        $currentPage--;

        // Loop over our users to attach their rank by index
        foreach ($users as $index => $user)
        {
            if ($currentPage > 0)
            {
                $index = $index + ($currentPage * $perPage);
            }

            $user['rank'] = $index + 1;
        }

        return [
            'success' => true,
            'paginated' => $users
        ];
    }
}
