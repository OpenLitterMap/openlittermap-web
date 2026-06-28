<?php

namespace App\Listeners\Locations\Twitter;

use App\Events\NewStateAdded;
use App\Helpers\Social;

class TweetNewState
{
    /**
     * Handle the event.
     *
     * @param  NewStateAdded  $event
     * @return void
     */
    public function handle (NewStateAdded $event)
    {
        if (app()->environment() === 'production')
        {
            Social::text("A new state has been added. Say hello to $event->state, $event->country!");
        }
    }
}
