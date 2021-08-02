<?php


namespace App\Actions\Photos;


use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

class UpdateLeaderboardsFromPhotoAction
{
    /**
     * @param User $user
     * @param Photo $photo
     */
    public function run(User $user, Photo $photo): void
    {
        // Update Leaderboards if user has public privacy settings
        if (!$user->show_name && !$user->show_username) {
            return;
        }

        $country = Country::find($photo->country_id);
        $state = State::find($photo->state_id);
        $city = City::find($photo->city_id);

        Redis::zadd($country->country . ':Leaderboard', $user->xp, $user->id);
        Redis::zadd($country->country . ':' . $state->state . ':Leaderboard', $user->xp, $user->id);
        Redis::zadd($country->country . ':' . $state->state . ':' . $city->city . ':Leaderboard', $user->xp, $user->id);
    }
}
