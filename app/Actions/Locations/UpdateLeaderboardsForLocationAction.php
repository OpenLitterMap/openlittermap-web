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

        // All Time Leaderboard
        Redis::zincrby("xp.country.$photo->country_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $incrXp, $userId);

        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        // Timestamped Leaderboards
        Redis::zincrby("daily-leaderboard:country:$photo->country_id:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("daily-leaderboard:state:$photo->state_id:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("daily-leaderboard:city:$photo->city_id:$year:$month:$day", $incrXp, $userId);

        Redis::zincrby("monthly-leaderboard:country:$photo->country_id:$year:$month", $incrXp, $userId);
        Redis::zincrby("monthly-leaderboard:state:$photo->state_id:$year:$month", $incrXp, $userId);
        Redis::zincrby("monthly-leaderboard:city:$photo->city_id:$year:$month", $incrXp, $userId);

        Redis::zincrby("annual-leaderboard:country:$photo->country_id:$year", $incrXp, $userId);
        Redis::zincrby("annual-leaderboard:state:$photo->state_id:$year", $incrXp, $userId);
        Redis::zincrby("annual-leaderboard:city:$photo->city_id:$year", $incrXp, $userId);
    }
}
