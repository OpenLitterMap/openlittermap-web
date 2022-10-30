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
        $date = Carbon::parse($event->created_at)->format('m-y');

        Redis::hincrby("ppm:country:$event->country_id", $date, 1);
    }
}
