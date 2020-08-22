<?php

namespace App\Listeners;

use App\Events\NewCountryAdded;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewCountryEmail
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
     * @param  NewCountryAdded  $event
     * @return void
     */
    public function handle(NewCountryAdded $event)
    {
        //
    }
}
