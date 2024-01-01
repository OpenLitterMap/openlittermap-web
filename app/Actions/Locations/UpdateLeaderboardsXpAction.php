<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsXpAction
{
    public function run (
        int $userId,
        int $incrXp
    ) :void
    {
        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        // Update the Users total score in the Global Leaderboard
        Redis::zincrby("xp.users", $incrXp, $userId);

        // Update the Users total score for each time-stamped Leaderboard
        Redis::zincrby("leaderboard:users:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("leaderboard:users:$year:$month", $incrXp, $userId);
        Redis::zincrby("leaderboard:users:$year", $incrXp, $userId);
    }
}
