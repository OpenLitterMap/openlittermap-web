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
     * Handle the event.
     *
     * @return void
     */
    public function handle (NewStateAdded $event)
    {
        $country_id = Country::where('country', $event->country)
            ->orWhere('countrynameb', $event->country)
            ->orWhere('countrynamec', $event->country)
            ->first()->id;

        $state = State::create([
            'state' => $event->state,
            'country_id' => $country_id,
            'created_by' => $event->userId
        ]);

        $state->save();
    }
}
