<?php

namespace App\Listeners\Locations\Twitter;

use App\Events\NewCountryAdded;
use App\Helpers\Twitter;

class TweetNewCountry
{
    /**
     * Handle the event.
     *
     * @param  NewCountryAdded  $event
     * @return void
     */
    public function handle (NewCountryAdded $event)
    {
        if (app()->environment() === 'production')
        {
            Twitter::sendTweet("New country added. Say hello to $event->country, with code '$event->countryCode'!");
        }
    }
}
