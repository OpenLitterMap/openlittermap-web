<?php

namespace Tests\Feature\User;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\DB;
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

        // Seed metrics table (source of truth for profile stats + rank)
        DB::table('metrics')->insert([
            'timescale' => 0, 'location_type' => 0, 'location_id' => 0,
            'user_id' => $userId, 'year' => 0, 'month' => 0, 'week' => 0,
            'bucket_date' => '1970-01-01',
            'uploads' => 10, 'tags' => 42, 'litter' => 30,
            'brands' => 5, 'materials' => 4, 'custom_tags' => 3,
            'xp' => 500,
        ]);

        // Seed another user's metrics for global tag total
        $otherUser = User::factory()->create();
        DB::table('metrics')->insert([
            'timescale' => 0, 'location_type' => 0, 'location_id' => 0,
            'user_id' => $otherUser->id, 'year' => 0, 'month' => 0, 'week' => 0,
            'bucket_date' => '1970-01-01',
            'uploads' => 5, 'tags' => 58, 'litter' => 50,
            'brands' => 3, 'materials' => 3, 'custom_tags' => 2,
            'xp' => 200,
        ]);

        // Seed aggregate global row (user_id=0) — written by MetricsService in production
        DB::table('metrics')->insert([
            'timescale' => 0, 'location_type' => 0, 'location_id' => 0,
            'user_id' => 0, 'year' => 0, 'month' => 0, 'week' => 0,
            'bucket_date' => '1970-01-01',
            'uploads' => 15, 'tags' => 100, 'litter' => 80,
            'brands' => 8, 'materials' => 7, 'custom_tags' => 5,
            'xp' => 700,
        ]);

        // Seed Redis leaderboard ZSET for rank lookup
        $globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalXpKey, 500, (string) $userId);
        Redis::zAdd($globalXpKey, 200, (string) $otherUser->id);

        // Seed public photos for global photo count
        Photo::factory()->count(10)->create(['is_public' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/index');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'username', 'avatar', 'created_at', 'global_flag', 'public_profile'],
            'stats' => ['uploads', 'tags', 'xp', 'streak'],
            'level' => ['level', 'title', 'xp', 'xp_into_level', 'xp_for_next', 'xp_remaining', 'progress_percent'],
            'rank' => ['global_position', 'global_total', 'percentile'],
            'global_stats' => ['total_photos', 'total_tags'],
            'achievements' => ['unlocked', 'total'],
            'locations' => ['countries', 'states', 'cities'],
        ]);

        $data = $response->json();

        $this->assertEquals($userId, $data['user']['id']);
        $this->assertEquals('Test User', $data['user']['name']);
        $this->assertEquals(500, $data['stats']['xp']);
        $this->assertEquals(10, $data['stats']['uploads']);
        $this->assertEquals(42, $data['stats']['tags']);
        $this->assertEquals(3, $data['level']['level']); // 500 XP → level 3 "Post-Noob" (thresholds: 0, 100, 500)
        $this->assertEquals(1, $data['rank']['global_position']);
        $this->assertEquals(15, $data['global_stats']['total_photos']); // from aggregate global row
        $this->assertEquals(100, $data['global_stats']['total_tags']); // from aggregate global row
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
    public function profile_refresh_returns_lightweight_structure(): void
    {
        $user = User::factory()->create([
            'name' => 'Refresh User',
            'username' => 'refreshuser',
            'xp' => 500,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/user/profile/refresh');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'username', 'email', 'avatar', 'global_flag', 'picked_up', 'previous_tags'],
            'stats' => ['xp'],
            'level' => ['level', 'title', 'xp_for_next', 'progress_percent'],
        ]);

        // Should NOT contain heavy profile data
        $response->assertJsonMissing(['rank']);
        $data = $response->json();
        $this->assertArrayNotHasKey('achievements', $data);
        $this->assertArrayNotHasKey('locations', $data);
        $this->assertArrayNotHasKey('global_stats', $data);

        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertEquals(500, $data['stats']['xp']);
    }

    /** @test */
    public function profile_refresh_requires_authentication(): void
    {
        $response = $this->getJson('/api/user/profile/refresh');

        $response->assertUnauthorized();
    }

    /** @test */
    public function rank_total_is_full_user_count(): void
    {
        // Create some users with XP and one without
        $activeUser = User::factory()->create(['xp' => 100]);
        $zeroXpUser = User::factory()->create(['xp' => 0]);

        // Seed metrics table for the active user (all-time global)
        DB::table('metrics')->insert([
            'timescale' => 0, 'location_type' => 0, 'location_id' => 0,
            'user_id' => $activeUser->id, 'year' => 0, 'month' => 0, 'week' => 0,
            'bucket_date' => '1970-01-01',
            'uploads' => 1, 'tags' => 0, 'litter' => 0,
            'brands' => 0, 'materials' => 0, 'custom_tags' => 0,
            'xp' => 100,
        ]);

        // Seed Redis leaderboard — only the active user has XP
        $globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalXpKey, 100, (string) $activeUser->id);

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
