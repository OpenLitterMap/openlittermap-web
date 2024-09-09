<?php

namespace App\Listeners\Locations;

use App\Events\NewStateAdded;
use Illuminate\Notifications\Slack\SlackMessage;

class NotifySlackOfNewState
{
    public function handle (NewStateAdded $event)
    {
        if (app()->environment() === 'production')
        {
            return (new SlackMessage)
                ->to('#new-locations')
                ->headerBlock("New state added :grin:")
                ->text("Say hello to $event->state, $event->country!");
        }
    }
}
