<?php

namespace App\Listeners\Locations;

use App\Events\NewCityAdded;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewCity
{
    /**
     * Handle the event.
     *
     * @param  NewCityAdded  $event
     * @return void
     */
    public function handle(NewCityAdded $event)
    {
        Slack::send("New city added :grin:. Say hello to $event->city, $event->state, $event->country!");
    }
}
