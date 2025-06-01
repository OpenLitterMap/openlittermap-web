<?php

namespace Tests\Unit\Actions\Locations;

use App\Actions\Locations\AddContributorForLocationAction;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AddContributorForLocationActionTest extends TestCase
{
    public function test_it_adds_user_id_to_a_redis_set_for_each_location()
    {
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;
        $userId = 1;

        Redis::del("country:$countryId:user_ids");
        Redis::del("state:$stateId:user_ids");
        Redis::del("city:$cityId:user_ids");

        $this->assertEquals([], Redis::smembers("country:$countryId:user_ids"));
        $this->assertEquals([], Redis::smembers("state:$stateId:user_ids"));
        $this->assertEquals([], Redis::smembers("city:$cityId:user_ids"));

        /** @var AddContributorForLocationAction $addContributorAction */
        $addContributorAction = app(AddContributorForLocationAction::class);
        $addContributorAction->run($countryId, $stateId, $cityId, $userId);

        // Executing the action twice for the same user
        // should not store their id twice
        $addContributorAction->run($countryId, $stateId, $cityId, $userId);

        $this->assertEquals([$userId], Redis::smembers("country:$countryId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("state:$stateId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("city:$cityId:user_ids"));
    }
}
