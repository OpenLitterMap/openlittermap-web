<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\Country;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementCountryMonth implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param IncrementPhotoMonth $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        $country = Country::find($event->country_id);

        if ($country)
        {
            $ppm = json_decode($country->photos_per_month, true);
            $date = Carbon::parse($event->created_at)->format('m-y');

            if (! is_null($ppm) && array_key_exists($date, $ppm))
            {
                $ppm[$date]++;
            }
            else
            {
                $ppm[$date] = 1;
            }

            $country->photos_per_month = json_encode($ppm);
            $country->save();
        }
    }
}
