<?php

namespace App\Listeners\Photo;

use App\Models\Location\City;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementCityMonth
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
     * @param  IncrementPhotoMonth  $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        if ($city = City::find($event->city_id))
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

            $ppm = json_encode($ppm);

            $city->photos_per_month = $ppm;

            $city->save();
        }
    }
}
