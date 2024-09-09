<?php

namespace App\Listeners\Locations;

use App\Events\NewCityAdded;
use Illuminate\Notifications\Slack\SlackMessage;

class NotifySlackOfNewCity
{
    /**
     * Note: Photo is not created yet
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
            return (new SlackMessage)
                ->to('#new-locations')
                ->headerBlock("New city added :grin:")
                ->text("Say hello to $event->city, $event->state, $event->country! " . $link ?: '');
        }
    }
}
