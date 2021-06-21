<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateUserTimeSeries implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     *
     * Todo: move this to redis
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $user = User::find($event->user_id);

        $ppm = json_decode($user->photos_per_month, true);

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

        $user->photos_per_month = $ppm;

        $user->save();
    }
}
