<?php

namespace Tests\Unit\Commands;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class UpdateRedisLocationsXpTest extends TestCase
{
    public function test_it_recalculates_users_xp_by_location()
    {
        list($country1, $state1, $city1) = $this->createLocation();
        list($country2, $state2, $city2) = $this->createLocation();
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $photo1 = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country1->id,
            'state_id' => $state1->id,
            'city_id' => $city1->id
        ]);
        /** @var Photo $photo2 */
        $photo2 = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country2->id,
            'state_id' => $state2->id,
            'city_id' => $city2->id
        ]);

        $photo1->tags()->attach($tag, ['quantity' => 3]);
        $photo2->tags()->attach($tag, ['quantity' => 5]);
        $photo2->customTags()->create(['tag' => 'custom tag example']);

        Redis::del("xp.users");
        $this->clearRedisLocation($country1, $state1, $city1);
        $this->clearRedisLocation($country2, $state2, $city2);
        $this->assertEquals(0, Redis::zscore("xp.users", $user->id));
        $this->assertRedisLocationEquals(0, $user, $country1, $state1, $city1);
        $this->assertRedisLocationEquals(0, $user, $country2, $state2, $city2);

        $this->artisan('users:update-redis-locations-xp');

        $this->assertEquals(11, Redis::zscore("xp.users", $user->id));
        $this->assertRedisLocationEquals(4, $user, $country1, $state1, $city1);
        $this->assertRedisLocationEquals(7, $user, $country2, $state2, $city2);
    }

    private function createLocation(): array
    {
        $country = Country::factory()->create(['shortcode' => 'us', 'country' => 'USA']);
        $state = State::factory()->create(['state' => 'North Carolina', 'country_id' => $country->id]);
        $city = City::factory()->create(['city' => 'Swain County', 'country_id' => $country->id, 'state_id' => $state->id]);
        return array($country, $state, $city);
    }

    private function clearRedisLocation($country, $state, $city): void
    {
        Redis::del("xp.country.$country->id");
        Redis::del("xp.country.$country->id.state.$state->id");
        Redis::del("xp.country.$country->id.state.$state->id.city.$city->id");
    }

    private function assertRedisLocationEquals($expected, $user, $country, $state, $city): void
    {
        $this->assertEquals($expected, Redis::zscore("xp.country.$country->id", $user->id));
        $this->assertEquals($expected, Redis::zscore("xp.country.$country->id.state.$state->id", $user->id));
        $this->assertEquals($expected, Redis::zscore("xp.country.$country->id.state.$state->id.city.$city->id", $user->id));
    }
}
