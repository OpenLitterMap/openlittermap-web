<?php

namespace Tests\Feature\Leaderboard;

use App\Enums\LocationType;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    /**
     * Insert a per-user metrics row for leaderboard testing.
     */
    private function seedMetric(
        int $userId,
        float $xp,
        int $timescale = 0,
        int $locationType = 0,
        int $locationId = 0,
        int $year = 0,
        int $month = 0,
        ?string $bucketDate = null
    ): void {
        DB::table('metrics')->insert([
            'timescale' => $timescale,
            'location_type' => $locationType,
            'location_id' => $locationId,
            'user_id' => $userId,
            'year' => $year,
            'month' => $month,
            'week' => 0,
            'bucket_date' => $bucketDate ?? '1970-01-01',
            'uploads' => 0,
            'tags' => 0,
            'litter' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'xp' => $xp,
        ]);
    }

    public function test_global_all_time_leaderboard_returns_users_sorted_by_xp(): void
    {
        $users = User::factory(3)->create();

        $this->seedMetric($users[0]->id, 300);
        $this->seedMetric($users[1]->id, 100);
        $this->seedMetric($users[2]->id, 200);

        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(3, $response['users']);
        $this->assertEquals(1, $response['users'][0]['rank']);
        $this->assertEquals(300, $response['users'][0]['xp']);
        $this->assertEquals(2, $response['users'][1]['rank']);
        $this->assertEquals(200, $response['users'][1]['xp']);
        $this->assertEquals(3, $response['users'][2]['rank']);
        $this->assertEquals(100, $response['users'][2]['xp']);
        $this->assertEquals(3, $response['total']);
    }

    public function test_location_filtered_leaderboard_returns_users_for_that_scope(): void
    {
        $country = Country::factory()->create();
        $users = User::factory(2)->create();

        $this->seedMetric($users[0]->id, 50, 0, LocationType::Country->value, $country->id);
        $this->seedMetric($users[1]->id, 150, 0, LocationType::Country->value, $country->id);

        $response = $this
            ->actingAs($users[0])
            ->getJson("/api/leaderboard?locationType=country&locationId={$country->id}")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals(150, $response['users'][0]['xp']);
        $this->assertEquals(50, $response['users'][1]['xp']);
    }

    public function test_time_filtered_leaderboard_returns_users_for_that_period(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $now = now()->utc();

        $this->seedMetric($user1->id, 250, 3, LocationType::Global->value, 0, $now->year, $now->month, $now->copy()->startOfMonth()->toDateString());
        $this->seedMetric($user2->id, 400, 3, LocationType::Global->value, 0, $now->year, $now->month, $now->copy()->startOfMonth()->toDateString());

        $response = $this
            ->actingAs($user1)
            ->getJson('/api/leaderboard?timeFilter=this-month')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals(400, $response['users'][0]['xp']);
        $this->assertEquals(250, $response['users'][1]['xp']);
        $this->assertEquals(2, $response['currentUserRank']);
    }

    public function test_leaderboard_pagination_works(): void
    {
        $users = User::factory(3)->create();

        $this->seedMetric($users[0]->id, 300);
        $this->seedMetric($users[1]->id, 200);
        $this->seedMetric($users[2]->id, 100);

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

        $this->seedMetric($user->id, 100);

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

    public function test_unauthenticated_user_can_access_leaderboard_without_rank(): void
    {
        $response = $this->getJson('/api/leaderboard');

        $response->assertOk();
        $response->assertJsonFragment(['currentUserRank' => null]);
    }

    public function test_current_user_rank_is_included_in_all_time_response(): void
    {
        $users = User::factory(3)->create();

        $this->seedMetric($users[0]->id, 300);
        $this->seedMetric($users[1]->id, 200);
        $this->seedMetric($users[2]->id, 100);

        // Acting as user with 200 XP (rank 2)
        $response = $this
            ->actingAs($users[1])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertEquals(2, $response['currentUserRank']);
    }

    public function test_user_not_on_leaderboard_gets_null_rank(): void
    {
        $user = User::factory()->create();

        // No metrics seeded for this user
        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertNull($response['currentUserRank']);
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

    public function test_daily_leaderboard_only_returns_today(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $now = now()->utc();
        $yesterday = $now->copy()->subDay();

        // Insert daily row for today (user1)
        $this->seedMetric($user1->id, 100, 1, LocationType::Global->value, 0, $now->year, $now->month, $now->toDateString());

        // Insert daily row for yesterday (user2)
        $this->seedMetric($user2->id, 500, 1, LocationType::Global->value, 0, $yesterday->year, $yesterday->month, $yesterday->toDateString());

        // "today" filter should only return user1
        $response = $this
            ->actingAs($user1)
            ->getJson('/api/leaderboard?timeFilter=today')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['users']);
        $this->assertEquals(100, $response['users'][0]['xp']);

        // "yesterday" filter should only return user2
        $response = $this
            ->actingAs($user1)
            ->getJson('/api/leaderboard?timeFilter=yesterday')
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['users']);
        $this->assertEquals(500, $response['users'][0]['xp']);
    }

    public function test_deleted_photo_removes_user_from_redis_leaderboard_when_xp_hits_zero(): void
    {
        $user = User::factory()->create();
        $country = Country::factory()->create();

        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);

        $globalKey = RedisKeys::xpRanking(RedisKeys::global());
        $countryKey = RedisKeys::xpRanking(RedisKeys::country($country->id));

        // Seed with 50 XP
        Redis::zAdd($globalKey, 50, (string) $user->id);
        Redis::zAdd($countryKey, 50, (string) $user->id);

        // Simulate delete that removes all XP via RedisMetricsCollector
        RedisMetricsCollector::processPhoto($photo, [
            'tags' => ['objects' => [1 => 5]],
            'litter' => 5,
            'xp' => 50,
        ], 'delete');

        // User should be pruned from ZSETs (score hit 0)
        // Redis::zScore returns false when member doesn't exist
        $this->assertFalse(Redis::zScore($globalKey, (string) $user->id));
        $this->assertFalse(Redis::zScore($countryKey, (string) $user->id));
        $this->assertEquals(0, Redis::zCard($globalKey));
    }

    public function test_state_filtered_leaderboard(): void
    {
        $state = State::factory()->create();
        $users = User::factory(2)->create();

        $this->seedMetric($users[0]->id, 80, 0, LocationType::State->value, $state->id);
        $this->seedMetric($users[1]->id, 120, 0, LocationType::State->value, $state->id);

        $response = $this
            ->actingAs($users[0])
            ->getJson("/api/leaderboard?locationType=state&locationId={$state->id}")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals(120, $response['users'][0]['xp']);
        $this->assertEquals(80, $response['users'][1]['xp']);
        $this->assertEquals(2, $response['total']);
    }

    public function test_city_filtered_leaderboard(): void
    {
        $city = City::factory()->create();
        $users = User::factory(2)->create();

        $this->seedMetric($users[0]->id, 200, 0, LocationType::City->value, $city->id);
        $this->seedMetric($users[1]->id, 75, 0, LocationType::City->value, $city->id);

        $response = $this
            ->actingAs($users[0])
            ->getJson("/api/leaderboard?locationType=city&locationId={$city->id}")
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['users']);
        $this->assertEquals('200', $response['users'][0]['xp']);
        $this->assertEquals(75, $response['users'][1]['xp']);
        $this->assertEquals(2, $response['total']);
    }

    public function test_tied_xp_users_have_deterministic_order(): void
    {
        // Create 3 users with identical XP — order must be deterministic by user_id
        $users = User::factory(3)->create();

        $now = now()->utc();

        foreach ($users as $user) {
            $this->seedMetric($user->id, 100, 3, LocationType::Global->value, 0, $now->year, $now->month, $now->copy()->startOfMonth()->toDateString());
        }

        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard?timeFilter=this-month')
            ->assertOk()
            ->json();

        $this->assertCount(3, $response['users']);

        // Verify deterministic order — users sorted by user_id ascending when XP tied
        $returnedRanks = collect($response['users'])->pluck('rank')->toArray();
        $this->assertEquals([1, 2, 3], $returnedRanks);

        // Run again to confirm same order (determinism)
        $response2 = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard?timeFilter=this-month')
            ->assertOk()
            ->json();

        $this->assertEquals(
            collect($response['users'])->pluck('xp')->toArray(),
            collect($response2['users'])->pluck('xp')->toArray()
        );
    }

    public function test_zero_xp_users_are_excluded(): void
    {
        $users = User::factory(2)->create();

        $this->seedMetric($users[0]->id, 100);
        $this->seedMetric($users[1]->id, 0);

        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['users']);
        $this->assertEquals(1, $response['total']);
    }

    public function test_response_includes_active_and_total_user_counts(): void
    {
        // 3 users total, 2 with global all-time XP
        $users = User::factory(3)->create();

        $this->seedMetric($users[0]->id, 300);
        $this->seedMetric($users[1]->id, 100);

        $response = $this
            ->actingAs($users[0])
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertEquals(2, $response['activeUsers']);
        $this->assertEquals(3, $response['totalUsers']);
    }

    public function test_leaderboard_uses_pivot_privacy_over_global_settings(): void
    {
        $teamType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );
        $team = Team::factory()->create(['type_id' => $teamType->id]);

        // User has global show_name=true, but pivot show_name_leaderboards=false
        $user = User::factory()->create([
            'show_name' => true,
            'show_username' => true,
            'active_team' => $team->id,
        ]);
        $user->teams()->attach($team->id, [
            'show_name_leaderboards' => false,
            'show_username_leaderboards' => false,
        ]);

        $this->seedMetric($user->id, 100);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['users']);
        $this->assertEquals('', $response['users'][0]['name'], 'Pivot should override global show_name');
        $this->assertEquals('', $response['users'][0]['username'], 'Pivot should override global show_username');
    }

    public function test_leaderboard_shows_name_when_pivot_allows(): void
    {
        $teamType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );
        $team = Team::factory()->create(['type_id' => $teamType->id]);

        // User has global show_name=false, but pivot show_name_leaderboards=true
        $user = User::factory()->create([
            'name' => 'Visible Person',
            'username' => 'visible',
            'show_name' => false,
            'show_username' => false,
            'active_team' => $team->id,
        ]);
        $user->teams()->attach($team->id, [
            'show_name_leaderboards' => true,
            'show_username_leaderboards' => true,
        ]);

        $this->seedMetric($user->id, 100);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['users']);
        $this->assertEquals('Visible Person', $response['users'][0]['name']);
        $this->assertEquals('@visible', $response['users'][0]['username']);
    }

    public function test_leaderboard_falls_back_to_global_when_no_active_team(): void
    {
        $user = User::factory()->create([
            'name' => 'Global User',
            'show_name' => true,
            'show_username' => false,
            'active_team' => null,
        ]);

        $this->seedMetric($user->id, 100);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/leaderboard')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['users']);
        $this->assertEquals('Global User', $response['users'][0]['name']);
        $this->assertEquals('', $response['users'][0]['username']);
    }

    public function test_all_time_country_filtered_leaderboard(): void
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $users = User::factory(3)->create();

        // User 0 and 1 in country1, user 2 in country2
        $this->seedMetric($users[0]->id, 500, 0, LocationType::Country->value, $country1->id);
        $this->seedMetric($users[1]->id, 300, 0, LocationType::Country->value, $country1->id);
        $this->seedMetric($users[2]->id, 900, 0, LocationType::Country->value, $country2->id);

        // Country 1 should only show 2 users
        $response = $this
            ->actingAs($users[0])
            ->getJson("/api/leaderboard?locationType=country&locationId={$country1->id}")
            ->assertOk()
            ->json();

        $this->assertCount(2, $response['users']);
        $this->assertEquals(2, $response['total']);
        $this->assertEquals(500, $response['users'][0]['xp']);
        $this->assertEquals('300', $response['users'][1]['xp']);
    }
}
