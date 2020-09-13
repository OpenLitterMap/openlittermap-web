<?php

namespace App\Listeners;

use DB;
use App\Models\Location\State;
use App\Models\Location\Country;
use App\Events\NewStateAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateStatesTable
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
     * @param  NewStateAdded  $event
     * @return void
     */
    public function handle(NewStateAdded $event)
    {
        $newState = $event->state;
        $country = $event->country;
        $state = new State;
        $state->state = $newState;

        $country = Country::where('country', $country)->orWhere('countrynameb', $country)->first();
        $state->country_id = $country->id;

        $state->save();
    }
}
