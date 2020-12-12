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
            // todo - merge this into dynamic function
            if ($event->total_alcohol)      $country->total_alcohol     += $event->total_alcohol;
            if ($event->total_coastal)      $country->total_coastal     += $event->total_coastal;
            if ($event->total_coffee)       $country->total_coffee      += $event->total_coffee;
            if ($event->total_dumping)      $country->total_dumping     += $event->total_dumping;
            if ($event->total_food)         $country->total_food        += $event->total_food;
            if ($event->total_industrial)   $country->total_industrial  += $event->total_industrial;
            if ($event->total_other)        $country->total_other       += $event->total_other;
            if ($event->total_sanitary)     $country->total_sanitary    += $event->total_sanitary;
            if ($event->total_softdrinks)   $country->total_softdrinks  += $event->total_softdrinks;
            if ($event->total_smoking)      $country->total_smoking     += $event->total_smoking;

            $country->total_litter += $event->total_count;
            $country->total_images++;
            $country->save();
        }
    }
}
