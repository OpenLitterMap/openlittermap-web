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
            Twitter::sendTweet("A new country has been added. Say hello to $event->country!");
        }
    }
}
