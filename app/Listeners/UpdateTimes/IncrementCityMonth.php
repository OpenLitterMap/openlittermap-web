<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\City;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

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
        $formattedDate = Carbon::parse($event->created_at)->format('m-y');

        Redis::hincrby("ppm:city:$event->city_id", $formattedDate, 1);

        // Part 2 - Update the total photos from all months
        $exists = Redis::hexists("totalppm:city:$event->city_id", $formattedDate);

        if ($exists)
        {
            // 2.1 - Update total redis count
            Redis::hincrby("totalppm:city:$event->city_id", $formattedDate, 1);
        }
        else
        {
            $previousMonth = Carbon::parse($event->created_at)->subMonth()->format('m-y');

            $value = Redis::hget("totalppm:city:$event->city_id", $previousMonth);

            if ($value)
            {
                Redis::hincrby("totalppm:city:$event->city_id", $formattedDate, $value + 1);
            }
        }
    }
}
