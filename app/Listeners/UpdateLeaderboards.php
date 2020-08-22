<?php

namespace App\Listeners;

use App\Photo;
use App\User;
use App\City;
use App\State;
use App\Country;
use App\Events\PhotoVerifiedByUser;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class UpdateLeaderboards
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PhotoVerifiedByUser  $event
     * @return void
     */
    public function handle(PhotoVerifiedByUser $event)
    {
        // find the user who uploaded the photo 
        $photoId = $event->photoId;
        $photo = Photo::find($photoId);
        $user = User::find($photo->user_id);
        // get their xp 
        $user->xp += 1;
        $user->save();

        $country = Country::find($photo->country_id);
        $state = State::find($photo->state_id);
        $city = City::find($photo->city_id);

        // Add to leaderboard if the user wants to be made public
        if (($user->show_name == 1) || ($user->show_username == 1)) {
            Redis::zadd($country->country.':Leaderboard', $user->xp, $user->id);
            Redis::zadd($country->country.':'.$state->state.':Leaderboard', $user->xp, $user->id);
            Redis::zadd($country->country.':'.$state->state.':'.$city->city.':Leaderboard', $user->xp, $user->id);
        }
    }
}
