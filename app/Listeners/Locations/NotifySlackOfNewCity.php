<?php

namespace App\Listeners\Locations;

use App\Events\NewCityAdded;
use App\Models\Photo;
use Pressutto\LaravelSlack\Facades\Slack;

class NotifySlackOfNewCity
{
    /**
     * Handle the event.
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
            $photo = Photo::where('city_id', $event->cityId)->first();

            if ($photo)
            {
                \Log::info(['photo found', $photo->id]);

                $link = "https://openlittermap.com/global?lat=" . $photo->lat . "&lon=" . $photo->lon . "&zoom=16'";
            }
            else
            {
                \Log::info(['photo not found', $event->cityId]);
            }

            \Log::info(['slack city link', $link]);
        }

        if (app()->environment() === 'production')
        {
            Slack::send(
                "New city added :grin: Say hello to $event->city, $event->state, $event->country! "
                . $link ?: ''
            );
        }
    }
}
