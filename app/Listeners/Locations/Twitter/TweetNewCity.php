<?php

namespace App\Listeners\Locations\Twitter;

use App\Events\NewCityAdded;
use App\Helpers\Twitter;

class TweetNewCity
{
    /**
     * Handle the event.
     *
     * Note: Photo is not created yet
     *
     * @param  NewCityAdded  $event
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
            Twitter::sendTweet(
                "A new city has been added. Say hello to $event->city, $event->state, $event->country! "
                . $link ?: ''
            );
        }
    }
}
