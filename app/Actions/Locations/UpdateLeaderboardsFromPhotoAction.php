<?php

namespace App\Actions\Locations;

use App\Models\Photo;
use App\Models\User\User;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsFromPhotoAction
{
    /**
     * @param User $user
     * @param Photo $photo
     */
    public function run (User $user, Photo $photo) :void
    {
        // Update Leaderboards if user has public privacy settings
        if (!$user->show_name && !$user->show_username)
        {
            return;
        }

        $country = Country::find($photo->country_id);
        $state = State::find($photo->state_id);
        $city = City::find($photo->city_id);

        if ($country)
        {
            Redis::zadd($country->country . ':Leaderboard', $user->xp, $user->id);
        }

        if ($country && $state)
        {
            Redis::zadd($country->country . ':' . $state->state . ':Leaderboard', $user->xp, $user->id);
        }

        if ($country && $state && $city)
        {
            Redis::zadd($country->country . ':' . $state->state . ':' . $city->city . ':Leaderboard', $user->xp, $user->id);
        }
    }
}
