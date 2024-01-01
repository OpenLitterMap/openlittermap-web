<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\Country;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class IncrementCountryMonth implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        // Part 1 - Update the current month
        // 1.1 - Format the created_at into month-year
        $formattedDate = Carbon::parse($event->created_at)->format('m-y');

        // 1.2 - Update Redis
        Redis::hincrby("ppm:country:$event->country_id", $formattedDate, 1);

        // Part 2 - Update the total photos from all months
        $exists = Redis::hexists("totalppm:country:$event->country_id", $formattedDate);

        if ($exists)
        {
            // 2.1 - Update total redis count
            Redis::hincrby("totalppm:country:$event->country_id", $formattedDate, 1);
        }
        else
        {
            $previousMonth = Carbon::parse($event->created_at)->subMonth()->format('m-y');

            $value = Redis::hget("totalppm:country:$event->country_id", $previousMonth);

            if ($value)
            {
                Redis::hincrby("totalppm:country:$event->country_id", $formattedDate, $value + 1);
            }
        }
    }
}
