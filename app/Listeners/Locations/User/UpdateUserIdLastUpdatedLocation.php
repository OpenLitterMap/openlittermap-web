<?php

namespace App\Listeners\Locations\User;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateUserIdLastUpdatedLocation implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle ($event)
    {
        $country = Country::find($event->country_id);
        $state = State::find($event->state_id);
        $city = City::find($event->city_id);

        if ($country)
        {
            $country->user_id_last_uploaded = $event->user_id;
            $country->save();
        }

        if ($state)
        {
            $state->user_id_last_uploaded = $event->user_id;
            $state->save();
        }

        if ($city)
        {
            $city->user_id_last_uploaded = $event->user_id;
            $city->save();
        }
    }
}
