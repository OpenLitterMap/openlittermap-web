<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

/**
 * @deprecated
 */
class UpdateLeaderboardsXpAction
{
    public function run (int $userId, int $incrXp) :void
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $day   = now()->format('d');

        // Update the Users total score in the Global Leaderboard
        $this->addXp("xp.users", $incrXp, $userId);

        // Update the Users total score for each time-stamped Leaderboard
        $this->addXp("leaderboard:users:$year:$month:$day", $incrXp, $userId);
        $this->addXp("leaderboard:users:$year:$month", $incrXp, $userId);
        $this->addXp("leaderboard:users:$year", $incrXp, $userId);
    }

    protected function addXp ($key, $xp, $userId): void {
        if ($xp <= 0) {
            $currentScore = Redis::zscore($key, $userId) ?? 0;

            $newScore = max(0, $currentScore + $xp);

            Redis::zadd($key, $newScore, $userId);
        } else {

            Redis::zincrby($key, $xp, $userId);
        }
    }
}
