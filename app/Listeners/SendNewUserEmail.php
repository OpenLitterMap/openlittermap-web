<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use App\Events\UserSignedUp;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNewUserEmail
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
     * @return void
     */
    public function handle(UserSignedUp $event)
    {
        Log::info("event handle - new user signed up");
    }
}
