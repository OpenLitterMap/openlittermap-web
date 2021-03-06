<?php

namespace App\Listeners\UpdateTags;

use App\Events\ResetTagsCountAdmin;
use App\Models\Location\City;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DecrementCity
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
    public function handle (ResetTagsCountAdmin $event)
    {
        $photo = Photo::find($event->photo_id);

        if ($city = City::find($photo->city_id))
        {
            $total_count = 0;

            // this is going to be the same for each location
            foreach ($photo->categories() as $category)
            {
                if ($photo->$category)
                {
                    $total = $photo->$category->total();

                    $total_string = "total_" . $category; // total_smoking, total_food...

                    $city->$total_string -= $total;

                    $total_count += $total; // total counts of all categories
                }
            }

            $city->total_litter -= $total_count;
            $city->total_images--;
            $city->save();
        }
    }
}
