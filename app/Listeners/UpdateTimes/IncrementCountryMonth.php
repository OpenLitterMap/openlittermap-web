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
     * @param IncrementPhotoMonth $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        // 1.1 - Format the created_at into month-year
        $formattedDate = Carbon::parse($event->created_at)->format('m-y');

        // 1.2 - Update Redis
        Redis::hincrby("ppm:country:$event->country_id", $formattedDate, 1);

        // 2.1 - Update total redis count
        Redis::hincrby("total_ppm:country:$event->country_id", $formattedDate, 1);
    }
}
