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

        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        Redis::zincrby("leaderboards.country.$photo->country_id.year.$year", $incrXp, $userId);
        Redis::zincrby("leaderboards.country.$photo->country_id.year.$year.month.$month", $incrXp, $userId);
        Redis::zincrby("leaderboards.country.$photo->country_id.year.$year.month.$month.day.$day", $incrXp, $userId);
    }
}
