<?php

namespace App\Listeners\AddTags;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class IncrementCountry implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $country = Country::find($event->country_id);

        if ($country)
        {
            if ($event->total_litter_all_categories > 0)
            {
                foreach ($event->total_litter_per_category as $category => $total)
                {
                    Redis::hincrby("country:$country->id", $category, $total);
                }

                Redis::hincrby("country:$country->id", "total_litter", $event->total_litter_all_categories);
            }

            if ($event->total_brands > 0)
            {
                foreach ($event->total_litter_per_brand as $brand => $total)
                {
                    Redis::hincrby("country:$country->id", $brand, $total);
                }

                Redis::hincrby("country:$country->id", "total_brands", $event->total_brands);
            }

            Redis::hincrby("country:$country->id", "total_photos", 1);
        }
    }
}
