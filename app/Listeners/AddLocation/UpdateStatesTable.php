<?php

namespace App\Listeners\AddLocation;

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
    public function handle (NewStateAdded $event)
    {
        $state = new State;
        $state->state = $event->state

        $country_id = Country::where('country', $event->country)->orWhere('countrynameb', $event->country)->first()->id;
        $state->country_id = $country_id;

        $state->save();
    }
}
