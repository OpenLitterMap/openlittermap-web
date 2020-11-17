<?php

namespace App\Listeners\AddLocation;

use App\Models\Location\Country;
use App\Events\NewCountryAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateCountriesTable
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
     * @param  NewCountryAdded  $event
     * @return void
     */
    public function handle (NewCountryAdded $event)
    {
        Country::create([
            'country' => $event->country,
            'shortcode' => $event->countryCode
        ]);
    }
}
