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
     * @param int $xp
     */
    public function run (Photo $photo, int $userId, int $xp) :void
    {
        Redis::zincrby("xp.country.$photo->country_id", $xp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id", $xp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $xp, $userId);
    }
}
