<?php

namespace App\Listeners\AddTags;

use App\Models\Photo;
use App\Models\Location\City;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class IncrementCity implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $city = City::find($event->city_id);

        \Log::info(['IncrCity', $event]);

        if ($city)
        {
            $categories = Photo::categories();
            $brands = Photo::getBrands();

            foreach ($categories as $category)
            {
                if ($event->$category)
                {
                    if ($category === "brands")
                    {
                        foreach ($brands as $brand)
                        {
                            if (isset($event->$brand))
                            {
                                Redis::hincrby("city:$city->id", $brand, $event->$brand);
                            }
                        }
                    }
                    else
                    {
                        Redis::hincrby("city:$city->id", $category, $event->$category);
                    }
                }
            }

            Redis::hincrby("city:$city->id", "total_photos", 1);
            Redis::hincrby("city:$city->id", "total_litter", $event->total_litter_all_categories);

            // update total brand + brands per location
        }
    }
}
