<?php

namespace App\Listeners\UpdateTimes;

use App\Models\Location\State;
use Carbon\Carbon;
use App\Events\Photo\IncrementPhotoMonth;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncrementStateMonth
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
     * @param  ImageUploaded  $event
     * @return void
     */
    public function handle (IncrementPhotoMonth $event)
    {
        if ($state = State::find($event->state_id))
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

            $ppm = json_encode($ppm);

            $state->photos_per_month = $ppm;

            $state->save();
        }
    }
}
