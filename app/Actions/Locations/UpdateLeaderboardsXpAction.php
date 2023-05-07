<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsXpAction
{
    /**
     * @param int $userId
     * @param int $incrXp
     * @param int $year
     * @param int $month
     * @param int $day
     */
    public function run (
        int $userId,
        int $incrXp,
        int $year,
        int $month,
        int $day
    ) :void
    {
        Redis::zincrby("xp.users", $incrXp, $userId);

        Redis::zincrby("daily-leaderboard:users:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("monthly-leaderboard:users:$year:$month", $incrXp, $userId);
        Redis::zincrby("annual-leaderboard:users:$year", $incrXp, $userId);
    }
}
