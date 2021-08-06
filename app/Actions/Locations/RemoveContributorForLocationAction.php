<?php

namespace App\Actions\Locations;

use Illuminate\Support\Facades\Redis;

class RemoveContributorForLocationAction
{
    /**
     * Remove user_id from a redis set for each location
     *
     * Country, State and City
     */
    public function run(string $countryId, string $stateId, string $cityId, int $userId)
    {
        Redis::srem("country:$countryId:user_ids", $userId);

        Redis::srem("state:$stateId:user_ids", $userId);

        Redis::srem("city:$cityId:user_ids", $userId);
    }
}
