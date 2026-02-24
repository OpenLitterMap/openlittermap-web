<?php

namespace Tests\Feature\Leaderboard;

use App\Enums\LocationType;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    public function test_global_all_time_leaderboard_returns_users_sorted_by_xp(): void
    {
        $users = User::factory(3)->create();

        // Seed Redis ZSET with different XP values
        $globalKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalKey, 300, (string) $users[0]->id);
        Redis::zAdd($globalKey, 100, (string) $users[1]->id);
        Redis::zAdd($globalKey, 200, (string) $users[2]->id);

        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(3, $response['users']);
        $this->assertEquals(1, $response['users'][0]['rank']);
        $this->assertEquals('300', $response['users'][0]['xp']);
        $this->assertEquals(2, $response['users'][1]['rank']);
        $this->assertEquals('200', $response['users'][1]['xp']);
        $this->assertEquals(3, $response['users'][2]['rank']);
        $this->assertEquals('100', $response['users'][2]['xp']);
        $this->assertEquals(3, $response['total']);
    }

    public function test_location_filtered_leaderboard_returns_users_for_that_scope(): void
    {
        $country = Country::factory()->create();
        $users = User::factory(2)->create();

        $countryKey = RedisKeys::xpRanking(RedisKeys::country($country->id));
        Redis::zAdd($countryKey, 50, (string) $users[0]->id);
        Redis::zAdd($countryKey, 150, (string) $users[1]->id);

        $response = $this
            ->actingAs($users[0])
            ->getJson("/api/leaderboard?locationType=country&locationId={$country->id}")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals('150', $response['users'][0]['xp']);
        $this->assertEquals('50', $response['users'][1]['xp']);
    }

    public function test_time_filtered_leaderboard_returns_users_for_that_period(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $now = now()->utc();

        // Insert per-user metrics rows for "this-month" (timescale=3)
        DB::table('metrics')->insert([
            [
                'timescale' => 3,
                'location_type' => LocationType::Global->value,
                'location_id' => 0,
                'user_id' => $user1->id,
                'year' => $now->year,
                'month' => $now->month,
                'week' => 0,
                'bucket_date' => $now->copy()->startOfMonth()->toDateString(),
                'uploads' => 5,
                'tags' => 10,
                'litter' => 8,
                'brands' => 1,
                'materials' => 1,
                'custom_tags' => 0,
                'xp' => 250,
            ],
            [
                'timescale' => 3,
                'location_type' => LocationType::Global->value,
                'location_id' => 0,
                'user_id' => $user2->id,
                'year' => $now->year,
                'month' => $now->month,
                'week' => 0,
                'bucket_date' => $now->copy()->startOfMonth()->toDateString(),
                'uploads' => 3,
                'tags' => 6,
                'litter' => 5,
                'brands' => 0,
                'materials' => 1,
                'custom_tags' => 0,
                'xp' => 400,
            ],
        ]);

        $response = $this
            ->actingAs($user1)
            ->getJson('/api/leaderboard?timeFilter=this-month')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals('400', $response['users'][0]['xp']);
        $this->assertEquals('250', $response['users'][1]['xp']);
        $this->assertEquals(2, $response['currentUserRank']);
    }

    public function test_leaderboard_pagination_works(): void
    {
        // Create 3 users, set page size effectively to 2 by making 3 entries
        $users = User::factory(3)->create();

        $globalKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalKey, 300, (string) $users[0]->id);
        Redis::zAdd($globalKey, 200, (string) $users[1]->id);
        Redis::zAdd($globalKey, 100, (string) $users[2]->id);

        // Page 1 should return all 3 (PER_PAGE = 100)
        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard?page=1')
            ->assertOk()
            ->json();

        $this->assertCount(3, $response['users']);
        $this->assertFalse($response['hasNextPage']);

        // Page 2 should be empty
        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard?page=2')
            ->assertOk()
            ->json();

        $this->assertCount(0, $response['users']);
    }

    public function test_leaderboard_respects_privacy_settings(): void
    {
        $user = User::factory()->create([
            'show_name' => false,
            'show_username' => false,
        ]);

        $globalKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalKey, 100, (string) $user->id);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['users']);
        $this->assertEquals('', $response['users'][0]['name']);
        $this->assertEquals('', $response['users'][0]['username']);
    }

    public function test_unauthenticated_user_cannot_access_leaderboard(): void
    {
        $this->getJson('/api/leaderboard')->assertUnauthorized();
    }

    public function test_current_user_rank_is_included_in_all_time_response(): void
    {
        $users = User::factory(3)->create();

        $globalKey = RedisKeys::xpRanking(RedisKeys::global());
        Redis::zAdd($globalKey, 300, (string) $users[0]->id);
        Redis::zAdd($globalKey, 200, (string) $users[1]->id);
        Redis::zAdd($globalKey, 100, (string) $users[2]->id);

        // Acting as user with 200 XP (rank 2)
        $response = $this
            ->actingAs($users[1])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertEquals(2, $response['currentUserRank']);
    }

    public function test_invalid_location_type_returns_error(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard?locationType=invalid&locationId=1')
            ->assertOk()
            ->json();

        $this->assertFalse($response['success']);
    }

    public function test_location_type_without_id_returns_error(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard?locationType=country')
            ->assertOk()
            ->json();

        $this->assertFalse($response['success']);
    }
}
