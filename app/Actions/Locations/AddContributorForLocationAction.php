<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class AddContributorForLocationAction
{
    /**
     * Add user_id to a redis set for each location
     *
     * Country, State and City
     */
    public function run(string $countryId, string $stateId, string $cityId, int $userId)
    {
        Redis::sadd("country:$countryId:user_ids", $userId);

        Redis::sadd("state:$stateId:user_ids", $userId);

        Redis::sadd("city:$cityId:user_ids", $userId);
    }
}
