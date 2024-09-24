<?php

namespace App\Listeners\Locations;

use App\Events\NewCountryAdded;
use Illuminate\Notifications\Slack\SlackMessage;

class NotifySlackOfNewCountry
{
    public function handle (NewCountryAdded $event)
    {
        if (app()->environment() === 'production')
        {
            return (new SlackMessage)
                ->to('#new-locations')
                ->headerBlock("New country added :grin:")
                ->text("Say hello to $event->country, with code '$event->countryCode'!");
        }
    }
}
