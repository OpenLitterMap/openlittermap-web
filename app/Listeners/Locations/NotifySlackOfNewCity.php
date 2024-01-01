<?php

namespace App\Listeners\Locations;

use App\Events\NewCityAdded;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewCity
{
    /**
     * Handle the event.
     *
     * Note: Photo is not created yet
     *
     * @return void
     */
    public function handle (NewCityAdded $event)
    {
        $link = null;

        // Get the first photo that created this City
        if ($event->cityId)
        {
            $link = "https://openlittermap.com/global?lat=" . $event->lat . "&lon=" . $event->lon . "&zoom=16";

            if ($event->photoId !== null)
            {
                $link .= "&photoId=" . $event->photoId;
            }
        }

        if (app()->environment() === 'production')
        {
            Slack::to('#new-locations')
                ->send(
                    "New city added :grin: Say hello to $event->city, $event->state, $event->country! "
                    . $link ?: ''
                );
        }
    }
}
