<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\City;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementCityMonth implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  IncrementPhotoMonth  $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        $city = City::find($event->city_id);

        if ($city)
        {
            $ppm = json_decode($city->photos_per_month, true);
            $date = Carbon::parse($event->created_at)->format('m-y');

            if (! is_null($ppm) && array_key_exists($date, $ppm))
            {
                $ppm[$date]++;
            }
            else
            {
                $ppm[$date] = 1;
            }

            $city->photos_per_month = json_encode($ppm);
            $city->save();
        }
    }
}
