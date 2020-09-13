<?php

namespace App\Listeners;

use DB;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\Events\NewCityAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCitiesTable
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
     * Handle the event
     *
     * @param  NewCityAdded  $event
     * @return void
     */
    public function handle (NewCityAdded $event)
    {
        $newCity = $event->city;
        $country = $event->country;
        $city = new City;
        $city->city = $newCity;

        $state = $event->state;
        $theState = State::where('state', $state)->orWhere('statenameb', $state)->first();
        $city->state_id = $theState->id;

        $country = Country::where('country', $country)->orWhere('countrynameb', $country)->first();
        $city->country_id = $country->id;

        $city->save();
    }
}
