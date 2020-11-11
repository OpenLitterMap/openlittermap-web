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
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $photo = Photo::find($event->photo_id);

        if ($state = State::find($photo->state_id))
        {
            $total_count = 0;

            // this is going to be the same for each location
            foreach ($photo->categories() as $category)
            {
                if ($photo->$category)
                {
                    $total = $photo->$category->total();

                    $total_string = "total_" . $category; // total_smoking, total_food...

                    $state->$total_string += $total;

                    $total_count += $total; // total counts of all categories
                }
            }

            $state->total_litter += $total_count;
            $state->total_images++;
            $state->save();
        }
    }
}
