<?php

namespace App\Listeners;

use App\Events\PhotoVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IncrementCountryAdmin
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
     * @param  PhotoVerifiedByAdmin  $event
     * @return void
     */
    public function handle(PhotoVerifiedByAdmin $event)
    {
        $photo = Photo::find($event->photoId);
        $country = Country::find($photo->country_id);

        $total_count = 0;

        // this is going to be the same for each location
        foreach ($photo->categories() as $category)
        {
            if ($photo->$category)
            {
                $total = $photo->$category->total();

                $total_string = "total_" . $category; // total_smoking, total_food...

                $country->$total_string += $total;

                $total_count += $total; // total counts of all categories
            }
        }

        $country->total_litter += $total_count;
        $country->total_images++;
        $country->save();
    }
}
