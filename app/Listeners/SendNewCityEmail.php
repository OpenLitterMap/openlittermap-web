<?php

namespace App\Listeners;

use App\Events\NewCityAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewCityEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NewCityAdded  $event
     * @return void
     */
    public function handle(NewCityAdded $event)
    {
        //
    }
}
