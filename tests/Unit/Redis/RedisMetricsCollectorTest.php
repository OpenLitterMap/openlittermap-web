<?php

declare(strict_types=1);

namespace Tests\Unit\Redis;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Redis\RedisKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisMetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    /**
     * Test basic photo creation
     */
    public function test_processes_photo_creation(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        $metrics = [
            'litter' => 5,
            'xp' => 10,
            'tags' => [
                'categories' => [1 => 3],
                'objects' => [2 => 5],
                'materials' => [],
                'brands' => [],
                'custom_tags' => []
            ]
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Check global stats
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats('{g}'), 'photos'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('10', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));

        // Check user stats
        $userScope = RedisKeys::user($user->id);
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals('10', Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats($userScope), 'litter'));
    }

    /**
     * Test photo update with deltas
     */
    public function test_processes_photo_update(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        // Initial create
        $initialMetrics = [
            'litter' => 5,
            'xp' => 10,
            'tags' => []
        ];
        RedisMetricsCollector::processPhoto($photo, $initialMetrics, 'create');

        // Update with deltas
        $deltaMetrics = [
            'litter' => 3,  // Added 3 more
            'xp' => 5,      // Added 5 more XP
            'tags' => []
        ];
        RedisMetricsCollector::processPhoto($photo, $deltaMetrics, 'update');

        // Check updated totals
        $this->assertEquals('8', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('15', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));
    }

    /**
     * Test photo deletion
     */
    public function test_processes_photo_deletion(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        // Create first
        $metrics = [
            'litter' => 5,
            'xp' => 10,
            'tags' => []
        ];
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Then delete
        RedisMetricsCollector::processPhoto($photo, $metrics, 'delete');

        // Check stats are back to zero
        $this->assertEquals('0', Redis::hGet(RedisKeys::stats('{g}'), 'photos'));
        $this->assertEquals('0', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('0', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));
    }

    /**
     * Test tag counting
     */
    public function test_tracks_tags_correctly(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        $metrics = [
            'litter' => 10,
            'xp' => 20,
            'tags' => [
                'categories' => [1 => 5, 2 => 3],
                'objects' => [10 => 5, 11 => 3],
                'materials' => [20 => 2],
                'brands' => [30 => 1],
                'custom_tags' => [40 => 1]
            ]
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Check global tag counts
        $this->assertEquals('5', Redis::hGet(RedisKeys::categories('{g}'), '1'));
        $this->assertEquals('3', Redis::hGet(RedisKeys::categories('{g}'), '2'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects('{g}'), '10'));
        $this->assertEquals('2', Redis::hGet(RedisKeys::materials('{g}'), '20'));
        $this->assertEquals('1', Redis::hGet(RedisKeys::brands('{g}'), '30'));
        $this->assertEquals('1', Redis::hGet(RedisKeys::customTags('{g}'), '40'));

        // Check rankings
        $this->assertEquals('5', Redis::zScore(RedisKeys::ranking('{g}', 'categories'), '1'));
        $this->assertEquals('3', Redis::zScore(RedisKeys::ranking('{g}', 'categories'), '2'));
    }

    /**
     * Test user metrics tracking
     */
    public function test_tracks_user_metrics(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        $metrics = [
            'litter' => 5,
            'xp' => 15,
            'tags' => [
                'categories' => [1 => 2],
                'objects' => [10 => 3],
                'materials' => [20 => 1],
                'brands' => [30 => 2],
                'custom_tags' => [40 => 1]
            ]
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        $userScope = RedisKeys::user($user->id);

        // Check user stats
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats($userScope), 'uploads'));
        $this->assertEquals('15', Redis::hGet(RedisKeys::stats($userScope), 'xp'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats($userScope), 'litter'));

        // Check user tags
        $this->assertEquals('2', Redis::hGet("{$userScope}:tags", 'cat:1'));
        $this->assertEquals('3', Redis::hGet("{$userScope}:tags", 'obj:10'));
        $this->assertEquals('1', Redis::hGet("{$userScope}:tags", 'mat:20'));
        $this->assertEquals('2', Redis::hGet("{$userScope}:tags", 'brand:30'));
        $this->assertEquals('1', Redis::hGet("{$userScope}:tags", 'custom:40'));
    }

    /**
     * Test contributor ranking
     */
    public function test_updates_contributor_ranking(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $photo1 = Photo::factory()->for($user1)->create();
        $photo2 = Photo::factory()->for($user1)->create();
        $photo3 = Photo::factory()->for($user2)->create();

        $metrics = ['litter' => 1, 'xp' => 1, 'tags' => []];

        RedisMetricsCollector::processPhoto($photo1, $metrics, 'create');
        RedisMetricsCollector::processPhoto($photo2, $metrics, 'create');
        RedisMetricsCollector::processPhoto($photo3, $metrics, 'create');

        // Check contributor ranking
        $this->assertEquals('2', Redis::zScore(RedisKeys::contributorRanking('{g}'), (string)$user1->id));
        $this->assertEquals('1', Redis::zScore(RedisKeys::contributorRanking('{g}'), (string)$user2->id));
    }

    /**
     * Test HyperLogLog for unique contributors
     */
    public function test_tracks_unique_contributors(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $photo1 = Photo::factory()->for($user1)->create();
        $photo2 = Photo::factory()->for($user2)->create();

        $metrics = ['litter' => 1, 'xp' => 1, 'tags' => []];

        RedisMetricsCollector::processPhoto($photo1, $metrics, 'create');
        RedisMetricsCollector::processPhoto($photo2, $metrics, 'create');

        // HLL should count 2 unique users
        $count = Redis::pfCount(RedisKeys::hll('{g}'));
        $this->assertEquals(2, $count);
    }

    /**
     * Test location-scoped updates
     */
    public function test_updates_location_scopes(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create([
            'country_id' => $country->id,
            'state_id' => $state->id
        ]);

        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
        ]);

        $metrics = [
            'litter' => 5,
            'xp' => 10,
            'tags' => [
                'categories' => [1 => 3],
                'objects' => [10 => 5],
                'materials' => [],
                'brands' => [],
                'custom_tags' => []
            ]
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Check all location scopes
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats(RedisKeys::country($country->id)), 'photos'));
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats(RedisKeys::state($state->id)), 'photos'));
        $this->assertEquals('1', Redis::hGet(RedisKeys::stats(RedisKeys::city($city->id)), 'photos'));

        // Check location tag counts
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects(RedisKeys::country($country->id)), '10'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects(RedisKeys::state($state->id)), '10'));
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects(RedisKeys::city($city->id)), '10'));
    }

    /**
     * Test getUserMetrics method
     */
    public function test_get_user_metrics_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        $metrics = [
            'litter' => 8,
            'xp' => 25,
            'tags' => [
                'categories' => [1 => 2],
                'objects' => [10 => 3],
                'materials' => [20 => 1],
                'brands' => [30 => 2],
                'custom_tags' => [40 => 1]
            ]
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        $result = RedisMetricsCollector::getUserMetrics($user->id);

        // Check structure
        $this->assertArrayHasKey('uploads', $result);
        $this->assertArrayHasKey('xp', $result);
        $this->assertArrayHasKey('litter', $result);
        $this->assertArrayHasKey('streak', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('objects', $result);
        $this->assertArrayHasKey('materials', $result);
        $this->assertArrayHasKey('brands', $result);
        $this->assertArrayHasKey('custom_tags', $result);

        // Check values
        $this->assertEquals(1, $result['uploads']);
        $this->assertEquals(25, $result['xp']);
        $this->assertEquals(8, $result['litter']);
        $this->assertEquals(2, $result['categories']['1']);
        $this->assertEquals(3, $result['objects']['10']);
        $this->assertEquals(1, $result['materials']['20']);
        $this->assertEquals(2, $result['brands']['30']);
        $this->assertEquals(1, $result['custom_tags']['40']);
    }

    /**
     * Test empty user metrics
     */
    public function test_get_user_metrics_returns_empty_structure_for_new_user(): void
    {
        $user = User::factory()->create();
        $result = RedisMetricsCollector::getUserMetrics($user->id);

        $this->assertEquals(0, $result['uploads']);
        $this->assertEquals(0, $result['xp']);
        $this->assertEquals(0, $result['litter']);
        $this->assertEquals(0, $result['streak']);
        $this->assertEmpty($result['categories']);
        $this->assertEmpty($result['objects']);
        $this->assertEmpty($result['materials']);
        $this->assertEmpty($result['brands']);
        $this->assertEmpty($result['custom_tags']);
    }

    /**
     * Test streak calculation with bitmap
     */
    public function test_streak_calculation_with_consecutive_days(): void
    {
        $user = User::factory()->create();

        // Create photos for consecutive days
        $today = Photo::factory()->for($user)->create(['created_at' => now()]);
        $yesterday = Photo::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $twoDaysAgo = Photo::factory()->for($user)->create(['created_at' => now()->subDays(2)]);

        $metrics = ['litter' => 1, 'xp' => 1, 'tags' => []];

        RedisMetricsCollector::processPhoto($twoDaysAgo, $metrics, 'create');
        RedisMetricsCollector::processPhoto($yesterday, $metrics, 'create');
        RedisMetricsCollector::processPhoto($today, $metrics, 'create');

        $result = RedisMetricsCollector::getUserMetrics($user->id);

        // Should have 3-day streak
        $this->assertEquals(3, $result['streak']);
    }

    /**
     * Test streak breaks with gap
     */
    public function test_streak_breaks_with_gap(): void
    {
        $user = User::factory()->create();

        // Create photos with a gap
        $today = Photo::factory()->for($user)->create(['created_at' => now()]);
        $threeDaysAgo = Photo::factory()->for($user)->create(['created_at' => now()->subDays(3)]);

        $metrics = ['litter' => 1, 'xp' => 1, 'tags' => []];

        RedisMetricsCollector::processPhoto($threeDaysAgo, $metrics, 'create');
        RedisMetricsCollector::processPhoto($today, $metrics, 'create');

        $result = RedisMetricsCollector::getUserMetrics($user->id);

        // Streak should be 1 (only today counts due to gap)
        $this->assertEquals(1, $result['streak']);
    }

    /**
     * Test handling of zero values in update
     */
    public function test_update_skips_zero_deltas(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        // Initial create
        $initialMetrics = ['litter' => 5, 'xp' => 10, 'tags' => []];
        RedisMetricsCollector::processPhoto($photo, $initialMetrics, 'create');

        // Update with zero deltas (no change)
        $deltaMetrics = ['litter' => 0, 'xp' => 0, 'tags' => []];
        RedisMetricsCollector::processPhoto($photo, $deltaMetrics, 'update');

        // Values should remain unchanged
        $this->assertEquals('5', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('10', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));
    }

    /**
     * Test error handling doesn't crash
     */
    public function test_handles_redis_errors_gracefully(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create();

        // Close Redis connection to simulate error
        Redis::disconnect();

        $metrics = ['litter' => 5, 'xp' => 10, 'tags' => []];

        // Should not throw exception
        $this->assertNull(
            RedisMetricsCollector::processPhoto($photo, $metrics, 'create')
        );

        // Reconnect for cleanup
        Redis::connection();
    }

    /**
     * Test pipeline performance with multiple photos
     */
    public function test_processes_multiple_photos_efficiently(): void
    {
        $user = User::factory()->create();
        $metrics = ['litter' => 1, 'xp' => 2, 'tags' => []];

        $startTime = microtime(true);

        // Process 100 photos
        for ($i = 0; $i < 100; $i++) {
            $photo = Photo::factory()->for($user)->create();
            RedisMetricsCollector::processPhoto($photo, $metrics, 'create');
        }

        $duration = microtime(true) - $startTime;

        // Should complete quickly due to pipelining
        $this->assertLessThan(5.0, $duration);

        // Verify counts
        $this->assertEquals('100', Redis::hGet(RedisKeys::stats('{g}'), 'photos'));
        $this->assertEquals('100', Redis::hGet(RedisKeys::stats('{g}'), 'litter'));
        $this->assertEquals('200', Redis::hGet(RedisKeys::stats('{g}'), 'xp'));
    }
}
