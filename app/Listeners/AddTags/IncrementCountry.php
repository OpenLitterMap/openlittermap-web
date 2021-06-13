<?php

namespace App\Listeners\AddTags;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\Country;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

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
        $country = Country::find($event->country_id);

        if ($country)
        {
            $categories = Photo::categories();

            foreach ($categories as $category)
            {
                if ($event->$category)
                {
                    Redis::hincrby("country:$country->id", $category, $event->$category);
                }
            }

            Redis::hincrby("country:$country->id", "total_photos", 1);
            Redis::hincrby("country:$country->id", "total_litter", $event->total_litter_all_categories);
        }
    }
}
