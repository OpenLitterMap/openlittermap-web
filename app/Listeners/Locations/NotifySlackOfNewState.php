<?php

namespace App\Listeners\Locations;

use App\Events\NewStateAdded;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewState
{
    /**
     * Handle the event.
     *
     * @param  NewStateAdded  $event
     * @return void
     */
    public function handle(NewStateAdded $event)
    {
        Slack::send("New state added :grin:. Say hello to $event->state, $event->country!");
    }
}
