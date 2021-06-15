<?php

namespace App\Listeners\Locations;

use App\Events\ImageUploaded;
use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class CheckContributors implements ShouldQueue
{
    /**
     * Add user_id to a redis set for each location
     *
     * Country, State and City
     *
     * Not sure if sismember check is necessary, as the user_id will only be added once anyway
     */
    public function handle (ImageUploaded $event)
    {
        if (!Redis::sismember("country:$event->countryId:user_ids", $event->userId))
        {
            Redis::sadd("country:$event->countryId:user_ids", $event->userId);
        }

        if (!Redis::sismember("state:$event->stateId:user_ids", $event->userId))
        {
            Redis::sadd("state:$event->stateId:user_ids", $event->userId);
        }

        if (!Redis::sismember("city:$event->cityId:user_ids", $event->userId))
        {
            Redis::sadd("city:$event->cityId:user_ids", $event->userId);
        }
    }
}
