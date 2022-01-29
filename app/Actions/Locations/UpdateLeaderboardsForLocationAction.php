<?php

namespace App\Actions\Locations;

use App\Models\Photo;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsForLocationAction
{
    /**
     *
     * @param Photo $photo
     * @param int $userId
     * @param int $incrXp
     */
    public function run (Photo $photo, int $userId, int $incrXp) :void
    {
        Redis::zincrby("xp.users", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $incrXp, $userId);
    }
}
