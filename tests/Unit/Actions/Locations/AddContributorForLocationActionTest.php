<?php

namespace Tests\Unit\Actions\Locations;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * @group deprecated
 * @deprecated Needs rewrite for v5 — admin routes moved to /api/admin/*,
 *             setUp uses dead routes (/submit, /add-tags)
 */
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
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

        // Executing the action twice for the same user
        // should not store their id twice
        $addContributorAction->run($countryId, $stateId, $cityId, $userId);

        $this->assertEquals([$userId], Redis::smembers("country:$countryId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("state:$stateId:user_ids"));
        $this->assertEquals([$userId], Redis::smembers("city:$cityId:user_ids"));
    }
}
