<?php

namespace App\Listeners\AddLocation;

use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\Events\NewCityAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCitiesTable
{
    /**
     * Handle the event
     *
     * @param  NewCityAdded  $event
     * @return void
     */
    public function handle (NewCityAdded $event)
    {
        $city = new City;
        $city->city = $event->city;

        $state_id = State::where('state', $event->state)->orWhere('statenameb', $event->state)->first()->id;
        $city->state_id = $state_id;

        $country_id = Country::where('country', $event->country)->orWhere('countrynameb', $event->country)->first()->id;
        $city->country_id = $country_id;
        $city->created_by = $event->userId;

        $city->save();
    }
}
