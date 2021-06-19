<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\State;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementStateMonth implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  IncrementPhotoMonth  $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        $state = State::find($event->state_id);

        if ($state)
        {
            $ppm = json_decode($state->photos_per_month, true);

            $date = Carbon::parse($event->created_at)->format('m-y');

            if (! is_null($ppm) && array_key_exists($date, $ppm))
            {
                $ppm[$date]++;
            }
            else
            {
                $ppm[$date] = 1;
            }

            $state->photos_per_month = json_encode($ppm);
            $state->save();
        }
    }
}
