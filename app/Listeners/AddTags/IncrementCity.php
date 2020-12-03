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
            // todo - merge this into dynamic function
            if ($event->total_alcohol)      $city->total_alcohol     += $event->total_alcohol;
            if ($event->total_coastal)      $city->total_coastal     += $event->total_coastal;
            if ($event->total_coffee)       $city->total_coffee      += $event->total_coffee;
            if ($event->total_dumping)      $city->total_dumping     += $event->total_dumping;
            if ($event->total_food)         $city->total_food        += $event->total_food;
            if ($event->total_industrial)   $city->total_industrial  += $event->total_industrial;
            if ($event->total_other)        $city->total_other       += $event->total_other;
            if ($event->total_sanitary)     $city->total_sanitary    += $event->total_sanitary;
            if ($event->total_softdrinks)   $city->total_softdrinks  += $event->total_softdrinks;
            if ($event->total_smoking)      $city->total_smoking     += $event->total_smoking;
            // $country->total_litter += $event->total_count; todo - add this column

            $city->total_images++;
            $city->save();
        }
    }
}
