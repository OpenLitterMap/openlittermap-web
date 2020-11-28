<?php

namespace App\Listeners\AddTags;

use App\Models\Photo;
use App\Models\Location\State;
use App\Events\TagsVerifiedByAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IncrementState
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        if ($state = State::find($event->state_id))
        {
            $state->total_litter += $event->total_count;
            $state->total_images++;
            $state->save();
        }
    }
}
