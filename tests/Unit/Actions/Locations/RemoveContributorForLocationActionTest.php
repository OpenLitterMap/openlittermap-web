<?php

namespace Tests\Unit\Actions\Locations;

use App\Actions\Locations\RemoveContributorForLocationAction;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RemoveContributorForLocationActionTest extends TestCase
{
    public function test_it_removes_user_id_rom_a_redis_set_for_each_location()
    {
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;
        $userId = 1;

        Redis::del("country:$countryId:user_ids");
        Redis::del("state:$stateId:user_ids");
        Redis::del("city:$cityId:user_ids");

        Redis::sadd("country:$countryId:user_ids", $userId);
        Redis::sadd("state:$stateId:user_ids", $userId);
        Redis::sadd("city:$cityId:user_ids", $userId);

        $this->assertEquals([$userId], Redis::smembers("country:$countryId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("state:$stateId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("city:$cityId:user_ids"));

        /** @var RemoveContributorForLocationAction $removeContributorAction */
        $removeContributorAction = app(RemoveContributorForLocationAction::class);
        $removeContributorAction->run($countryId, $stateId, $cityId, $userId);

        $this->assertEquals([], Redis::smembers("country:$countryId:user_ids"));
        $this->assertEquals([], Redis::smembers("state:$stateId:user_ids"));
        $this->assertEquals([], Redis::smembers("city:$cityId:user_ids"));
    }
}
