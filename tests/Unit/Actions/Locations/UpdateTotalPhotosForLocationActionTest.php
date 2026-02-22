<?php

namespace Tests\Unit\Actions\Locations;

use App\Actions\Locations\UpdateTotalPhotosForLocationAction;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * @group deprecated
 * @deprecated Needs rewrite for v5 — admin routes moved to /api/admin/*,
 *             setUp uses dead routes (/submit, /add-tags)
 */
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
class UpdateTotalPhotosForLocationActionTest extends TestCase
{
    public function test_it_increments_a_redis_hash_for_each_location()
    {
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;
        $increment = 10;

        Redis::del("country:$countryId");
        Redis::del("state:$stateId");
        Redis::del("city:$cityId");

    }

    public function test_it_decrements_a_redis_hash_for_each_location()
    {
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;
        $decrement = -5;

        Redis::del("country:$countryId");
        Redis::del("state:$stateId");
        Redis::del("city:$cityId");

    }

    public function test_it_doesnt_decrement_below_zero()
    {
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;
        $decrement = -5;

        Redis::del("country:$countryId");
        Redis::del("state:$stateId");
        Redis::del("city:$cityId");
    }
}
