<?php

namespace App\Listeners;

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
        $new = $event->country;
        $newCode = $event->countryCode;
        $countries = Country::all(); // not sure why we are checking this twice!
        if (! array_key_exists($new, $countries))
        {
            $country = new Country;
            $country->country = $new;
            $country->shortcode = $newCode;
            $country->save();
        }
    }
}
