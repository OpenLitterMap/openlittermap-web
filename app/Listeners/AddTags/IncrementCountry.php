<?php

namespace App\Listeners\AddTags;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IncrementCountry
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        if ($country = Country::find($event->country_id))
        {
<<<<<<< HEAD
            $country->total_litter += $event->total_count;
=======
            $total_count = 0;

            // this is going to be the same for each location
            foreach ($photo->categories() as $category)
            {
                if ($photo->$category)
                {
                    $total = $photo->$category->total();

                    $total_category = "total_" . $category; // total_smoking, total_food...

                    $country->$total_category += $total;

                    $total_count += $total; // total counts of all categories
                }
            }

            $country->total_litter += $total_count;
>>>>>>> master
            $country->total_images++;
            $country->save();
        }
    }
}
