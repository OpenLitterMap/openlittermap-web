<?php

namespace App\Actions\Locations;

use App\Models\Photo;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsForLocationAction
{
    /** @var UpdateLeaderboardsXpAction */
    protected $updateXpAction;

    public function __construct (UpdateLeaderboardsXpAction $updateXpAction)
    {
        $this->updateXpAction = $updateXpAction;
    }

    /**
     * Update the Leaderboards for each Location
     *
     * All time + time-stamped
     */
    public function run (
        Photo $photo,
        int $userId,
        int $incrXp
    ) :void
    {
        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        // All Time & Timestamped Leaderboard for all users
        $this->updateXpAction->run($userId, $incrXp);

        // All Time Leaderboard For Each Location
        // These are only needed to pass tests
        Redis::zincrby("xp.country.$photo->country_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id", $incrXp, $userId);
        Redis::zincrby("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $incrXp, $userId);

        // All-time score for each location
        Redis::zincrby("leaderboard:country:$photo->country_id:total", $incrXp, $userId);
        Redis::zincrby("leaderboard:state:$photo->state_id:total", $incrXp, $userId);
        Redis::zincrby("leaderboard:city:$photo->city_id:total", $incrXp, $userId);

        // Timestamped Leaderboards For Each Location
        Redis::zincrby("leaderboard:country:$photo->country_id:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("leaderboard:state:$photo->state_id:$year:$month:$day", $incrXp, $userId);
        Redis::zincrby("leaderboard:city:$photo->city_id:$year:$month:$day", $incrXp, $userId);

        Redis::zincrby("leaderboard:country:$photo->country_id:$year:$month", $incrXp, $userId);
        Redis::zincrby("leaderboard:state:$photo->state_id:$year:$month", $incrXp, $userId);
        Redis::zincrby("leaderboard:city:$photo->city_id:$year:$month", $incrXp, $userId);

        Redis::zincrby("leaderboard:country:$photo->country_id:$year", $incrXp, $userId);
        Redis::zincrby("leaderboard:state:$photo->state_id:$year", $incrXp, $userId);
        Redis::zincrby("leaderboard:city:$photo->city_id:$year", $incrXp, $userId);
    }
}
