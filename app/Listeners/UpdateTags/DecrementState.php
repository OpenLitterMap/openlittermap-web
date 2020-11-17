<?php

namespace App\Listeners\UpdateTags;

use App\Events\ResetTagsCountAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DecrementState
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
     * @param  ResetTagsCountAdmin  $event
     * @return void
     */
    public function handle(ResetTagsCountAdmin $event)
    {
        //
    }
}
