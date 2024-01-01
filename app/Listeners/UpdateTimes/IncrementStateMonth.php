<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\State;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class IncrementStateMonth implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        $formattedDate = Carbon::parse($event->created_at)->format('m-y');

        Redis::hincrby("ppm:state:$event->state_id", $formattedDate, 1);

        // Part 2 - Update the total photos from all months
        $exists = Redis::hexists("totalppm:state:$event->state_id", $formattedDate);

        if ($exists)
        {
            // 2.1 - Update total redis count
            Redis::hincrby("totalppm:state:$event->state_id", $formattedDate, 1);
        }
        else
        {
            $previousMonth = Carbon::parse($event->created_at)->subMonth()->format('m-y');

            $value = Redis::hget("totalppm:state:$event->state_id", $previousMonth);

            if ($value)
            {
                Redis::hincrby("totalppm:state:$event->state_id", $formattedDate, $value + 1);
            }
        }
    }
}
