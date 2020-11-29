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
            $country->total_litter += $event->total_count;
            $country->total_images++;
            $country->save();
        }
    }
}
