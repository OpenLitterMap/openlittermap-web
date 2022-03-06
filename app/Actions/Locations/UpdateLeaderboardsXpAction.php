<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsXpAction
{
    /**
     * @param int $userId
     * @param int $incrXp
     */
    public function run (int $userId, int $incrXp) :void
    {
        Redis::zincrby("xp.users", $incrXp, $userId);
    }
}
