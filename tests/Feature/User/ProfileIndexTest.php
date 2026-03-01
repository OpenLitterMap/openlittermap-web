<?php

namespace Tests\Feature\User;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ProfileIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Redis keys
        $keys = Redis::keys('{u:*');
        if ($keys && is_array($keys)) {
            Redis::del($keys);
        }
        $globalKeys = Redis::keys('{g}:*');
        if ($globalKeys && is_array($globalKeys)) {
            Redis::del($globalKeys);
        }
    }

    /** @test */
    public function profile_index_returns_expected_structure(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
        ]);

        $userId = $user->id;
        $userScope = RedisKeys::user($userId);

        // Seed Redis stats
        Redis::hSet(RedisKeys::stats($userScope), 'uploads', 10);
        Redis::hSet(RedisKeys::stats($userScope), 'xp', 500);
        Redis::hSet(RedisKeys::stats($userScope), 'litter', 42);
        Redis::zAdd(RedisKeys::xpRanking(RedisKeys::global()), 500, (string) $userId);

        // Seed global stats
        Redis::hSet(RedisKeys::stats(RedisKeys::global()), 'photos', 1000);
        Redis::hSet(RedisKeys::stats(RedisKeys::global()), 'litter', 5000);

        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/index');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'username', 'avatar', 'created_at', 'global_flag', 'public_profile'],
            'stats' => ['uploads', 'litter', 'xp', 'streak'],
            'level' => ['level', 'title', 'xp', 'xp_into_level', 'xp_for_next', 'xp_remaining', 'progress_percent'],
            'rank' => ['global_position', 'global_total', 'percentile'],
            'global_stats' => ['total_photos', 'total_litter'],
            'achievements' => ['unlocked', 'total'],
            'locations' => ['countries', 'states', 'cities'],
        ]);

        $data = $response->json();

        $this->assertEquals($userId, $data['user']['id']);
        $this->assertEquals('Test User', $data['user']['name']);
        $this->assertEquals(500, $data['stats']['xp']);
        $this->assertEquals(10, $data['stats']['uploads']);
        $this->assertEquals(42, $data['stats']['litter']);
        $this->assertEquals(3, $data['level']['level']); // 500 XP → level 3 "Post-Noob" (thresholds: 0, 100, 500)
        $this->assertEquals(1, $data['rank']['global_position']);
        $this->assertEquals(1000, $data['global_stats']['total_photos']);
    }

    /** @test */
    public function profile_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/user/profile/index');

        $response->assertUnauthorized();
    }

    /** @test */
    public function profile_index_returns_location_counts(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;

        // Seed minimal Redis stats
        $userScope = RedisKeys::user($userId);
        Redis::hSet(RedisKeys::stats($userScope), 'uploads', 3);
        Redis::hSet(RedisKeys::stats($userScope), 'xp', 0);
        Redis::hSet(RedisKeys::stats($userScope), 'litter', 0);

        // Create locations
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $state1 = State::factory()->create(['country_id' => $country1->id]);
        $state2 = State::factory()->create(['country_id' => $country1->id]);
        $state3 = State::factory()->create(['country_id' => $country2->id]);
        $city1 = City::factory()->create(['state_id' => $state1->id, 'country_id' => $country1->id]);
        $city2 = City::factory()->create(['state_id' => $state2->id, 'country_id' => $country1->id]);
        $city3 = City::factory()->create(['state_id' => $state3->id, 'country_id' => $country2->id]);

        // Create photos with different locations
        Photo::factory()->for($user)->create(['country_id' => $country1->id, 'state_id' => $state1->id, 'city_id' => $city1->id]);
        Photo::factory()->for($user)->create(['country_id' => $country1->id, 'state_id' => $state2->id, 'city_id' => $city2->id]);
        Photo::factory()->for($user)->create(['country_id' => $country2->id, 'state_id' => $state3->id, 'city_id' => $city3->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/index');

        $response->assertOk();
        $this->assertEquals(2, $response->json('locations.countries'));
        $this->assertEquals(3, $response->json('locations.states'));
        $this->assertEquals(3, $response->json('locations.cities'));
    }

    /** @test */
    public function rank_total_is_full_user_count(): void
    {
        // Create some users with XP and one without
        $activeUser = User::factory()->create(['xp' => 100]);
        $zeroXpUser = User::factory()->create(['xp' => 0]);

        $userScope = RedisKeys::user($activeUser->id);
        Redis::hSet(RedisKeys::stats($userScope), 'xp', 100);
        Redis::zAdd(RedisKeys::xpRanking(RedisKeys::global()), 100, (string) $activeUser->id);

        $response = $this->actingAs($zeroXpUser)
            ->getJson('/api/user/profile/index');

        $response->assertOk();

        $total = $response->json('rank.global_total');
        $position = $response->json('rank.global_position');

        // Total should be ALL users, not just those with XP
        $this->assertEquals(User::count(), $total);
        // 0 XP user is tied last — position equals count of users with more XP + 1
        $this->assertLessThanOrEqual($total, $position);
    }
}
