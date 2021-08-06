<?php

namespace App\Listeners\AddTags;

use App\Models\Location\City;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Contracts\Queue\ShouldQueue;
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

        if ($city)
        {
            if ($event->total_litter_all_categories > 0)
            {
                foreach ($event->total_litter_per_category as $category => $total)
                {
                    Redis::hincrby("city:$city->id", $category, $total);
                }

                Redis::hincrby("city:$city->id", "total_litter", $event->total_litter_all_categories);
            }

            if ($event->total_brands > 0)
            {
                foreach ($event->total_litter_per_brand as $brand => $total)
                {
                    Redis::hincrby("city:$city->id", $brand, $total);
                }

                Redis::hincrby("city:$city->id", "total_brands", $event->total_brands);
            }
        }
    }
}
