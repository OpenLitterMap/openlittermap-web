<?php

namespace App\Listeners\AddTags;

use App\Models\Photo;
use App\Models\Location\City;
use App\Events\TagsVerifiedByAdmin;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class IncrementCity
{
    /**
     * Handle the event.
     *
     * @param  PhotoVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        if ($city = City::find($event->city_id))
        {
            $city->total_litter += $event->total_count;
            $city->total_images++;
            $city->save();
        }
    }
}
