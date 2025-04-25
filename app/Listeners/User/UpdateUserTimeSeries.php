<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class UpdateUserTimeSeries implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $date = Carbon::parse($event->created_at)->format('m-y');

        Redis::hincrby("ppm:user:$event->user_id", $date, 1);
    }
}
