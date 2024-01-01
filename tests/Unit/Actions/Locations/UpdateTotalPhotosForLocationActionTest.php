<?php

namespace Tests\Unit\Actions\Locations;

use App\Actions\Locations\UpdateTotalPhotosForLocationAction;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

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

        $this->assertEquals(null, Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertEquals(null, Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertEquals(null, Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));

        /** @var UpdateTotalPhotosForLocationAction $updateTotalPhotosAction */
        $updateTotalPhotosAction = app(UpdateTotalPhotosForLocationAction::class);
        $updateTotalPhotosAction->run($countryId, $stateId, $cityId, $increment);

        $this->assertSame("$increment", Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame("$increment", Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame("$increment", Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));

        // Executing the action twice
        $updateTotalPhotosAction->run($countryId, $stateId, $cityId, $increment);

        $this->assertSame(2 * $increment, (int) Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame(2 * $increment, (int) Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame(2 * $increment, (int) Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));
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

        Redis::hincrby("country:$countryId", UpdateTotalPhotosForLocationAction::KEY, 10);
        Redis::hincrby("state:$countryId", UpdateTotalPhotosForLocationAction::KEY, 10);
        Redis::hincrby("city:$countryId", UpdateTotalPhotosForLocationAction::KEY, 10);

        /** @var UpdateTotalPhotosForLocationAction $updateTotalPhotosAction */
        $updateTotalPhotosAction = app(UpdateTotalPhotosForLocationAction::class);
        $updateTotalPhotosAction->run($countryId, $stateId, $cityId, $decrement);

        $this->assertSame('5', Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame('5', Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame('5', Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));

        // Executing the action twice
        $updateTotalPhotosAction->run($countryId, $stateId, $cityId, $decrement);

        $this->assertSame('0', Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame('0', Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertSame('0', Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));
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

        $this->assertEquals(null, Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertEquals(null, Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertEquals(null, Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));

        /** @var UpdateTotalPhotosForLocationAction $updateTotalPhotosAction */
        $updateTotalPhotosAction = app(UpdateTotalPhotosForLocationAction::class);
        $updateTotalPhotosAction->run($countryId, $stateId, $cityId, $decrement);

        $this->assertNull(Redis::hget("country:$countryId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertNull(Redis::hget("state:$stateId", UpdateTotalPhotosForLocationAction::KEY));
        $this->assertNull(Redis::hget("city:$cityId", UpdateTotalPhotosForLocationAction::KEY));
    }
}
