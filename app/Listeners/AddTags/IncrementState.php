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
            // todo - merge this into dynamic function
            if ($event->total_alcohol)      $state->total_alcohol     += $event->total_alcohol;
            if ($event->total_coastal)      $state->total_coastal     += $event->total_coastal;
            if ($event->total_coffee)       $state->total_coffee      += $event->total_coffee;
            if ($event->total_dumping)      $state->total_dumping     += $event->total_dumping;
            if ($event->total_food)         $state->total_food        += $event->total_food;
            if ($event->total_industrial)   $state->total_industrial  += $event->total_industrial;
            if ($event->total_other)        $state->total_other       += $event->total_other;
            if ($event->total_sanitary)     $state->total_sanitary    += $event->total_sanitary;
            if ($event->total_softdrinks)   $state->total_softdrinks  += $event->total_softdrinks;
            if ($event->total_smoking)      $state->total_smoking     += $event->total_smoking;

            $state->total_litter += $event->total_count;
            $state->total_images++;
            $state->save();
        }
    }
}
