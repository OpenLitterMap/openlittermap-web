<?php

namespace App\Listeners\Locations;

use App\Events\NewCountryAdded;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewCountry
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle (NewCountryAdded $event)
    {
        if (app()->environment() === 'production')
        {
            Slack::to('#new-locations')
                ->send("New country added :grin:. Say hello to $event->country, with code '$event->countryCode'!");
        }
    }
}
