<?php

namespace App\Actions\Locations;

use App\Models\Photo;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsForLocationAction
{
    /** @var UpdateLeaderboardsXpAction */
    protected $updateXpAction;

    /**
     * @param UpdateLeaderboardsXpAction $updateXpAction
     */
    public function __construct(UpdateLeaderboardsXpAction $updateXpAction)
    {
        $this->updateXpAction = $updateXpAction;
    }

    /**
     *
     * @param Photo $photo
     * @param int $userId
     * @param int $incrXp
     */
    public function run (Photo $photo, int $userId, int $incrXp) :void
    {
        $this->updateXpAction->run($userId, $incrXp);
        Redis::zincrby("xp.country.$photo->country_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $incrXp, $userId);
    }
}
