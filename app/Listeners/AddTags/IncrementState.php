<?php

namespace App\Listeners\AddTags;

use App\Models\Photo;
use App\Models\Location\State;
use App\Events\TagsVerifiedByAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

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
        $state = State::find($event->state_id);

        if ($state)
        {
            $categories = Photo::categories();

            foreach ($categories as $category)
            {
                if ($event->$category)
                {
                    Redis::hincrby("state:$state->id", $category, $event->$category);
                }
            }

            Redis::hincrby("state:$state->id", "total_photos", 1);
            Redis::hincrby("state:$state->id", "total_litter", $event->total_litter_all_categories);
        }
    }
}
