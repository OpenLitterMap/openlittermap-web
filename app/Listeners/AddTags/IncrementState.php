<?php

namespace App\Listeners\AddTags;

use App\Models\Location\State;
use App\Events\TagsVerifiedByAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class IncrementState implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $state = State::find($event->state_id);

        if ($state)
        {
            if ($event->total_litter_all_categories > 0)
            {
                foreach ($event->total_litter_per_category as $category => $total)
                {
                    Redis::hincrby("state:$state->id", $category, $total);
                }

                Redis::hincrby("state:$state->id", "total_litter", $event->total_litter_all_categories);
            }

            if ($event->total_brands > 0)
            {
                foreach ($event->total_litter_per_brand as $brand => $total)
                {
                    Redis::hincrby("state:$state->id", $brand, $total);
                }

                Redis::hincrby("state:$state->id", "total_brands", $event->total_brands);
            }
        }
    }
}
