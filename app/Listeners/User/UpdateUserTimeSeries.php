<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class UpdateUserTimeSeries implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     *
     * Todo: move this to redis
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $date = Carbon::parse($event->created_at)->format('m-y');

        Redis::hincrby("ppm:user:$event->user_id", $date, 1);
    }
}
