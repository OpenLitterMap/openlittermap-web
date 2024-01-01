<?php

namespace App\Listeners\Locations;

use App\Events\NewStateAdded;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewState
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle (NewStateAdded $event)
    {
        if (app()->environment() === 'production')
        {
            Slack::to('#new-locations')
                ->send("New state added :grin:. Say hello to $event->state, $event->country!");
        }
    }
}
