<?php

declare(strict_types=1);

namespace Tests\Unit\Redis;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisMetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    // Tag IDs storage
    private array $tagIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Redis before each test
        Redis::flushall();

        // Warm up the TagKeyCache and get IDs for test data
        TagKeyCache::preloadAll();

        // Pre-create the tag IDs we'll need
        $this->tagIds = [
            'cup' => TagKeyCache::getOrCreateId('object', 'cup'),
            'butt' => TagKeyCache::getOrCreateId('object', 'butt'),
            'plastic' => TagKeyCache::getOrCreateId('material', 'plastic'),
            'glass' => TagKeyCache::getOrCreateId('material', 'glass'),
            'starbucks' => TagKeyCache::getOrCreateId('brand', 'starbucks'),
            'cocacola' => TagKeyCache::getOrCreateId('brand', 'cocacola'),
            'biodegradable' => TagKeyCache::getOrCreateId('customTag', 'biodegradable'),
            'food' => TagKeyCache::getOrCreateId('category', 'food'),
            'drinking' => TagKeyCache::getOrCreateId('category', 'drinking'),
        ];
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    /**
     * Test basic counting functionality
     */
    public function test_queue_processes_single_photo_correctly(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 3]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Check user stats
        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:stats", 'uploads'));
        $this->assertEquals('3', Redis::hGet("{u:{$user->id}}:t", (string)$this->tagIds['cup']));
        $this->assertEquals('3', Redis::hGet("{u:{$user->id}}:c", (string)$this->tagIds['drinking']));

        // Check global counts
        $this->assertEquals('3', Redis::hGet('{g}:t', (string)$this->tagIds['cup']));
        $this->assertEquals('3', Redis::hGet('{g}:c', (string)$this->tagIds['drinking']));

        // Check photo is marked as processed
        $this->assertNotNull($photo->fresh()->processed_at);
    }

    /**
     * Test that same photo isn't counted twice
     */
    public function test_prevents_double_counting_same_photo(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 1]
                    ]
                ]
            ]
        ]);

        // Process same photo twice
        RedisMetricsCollector::queue($photo);
        RedisMetricsCollector::queue($photo);

        // Should only count once
        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:stats", 'uploads'));
        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:t", (string)$this->tagIds['cup']));
    }

    /**
     * Test batch processing
     */
    public function test_batch_processing_accumulates_correctly(): void
    {
        $user = User::factory()->create();
        $photos = collect([
            Photo::factory()->for($user)->create([
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 2]
                        ]
                    ]
                ]
            ]),
            Photo::factory()->for($user)->create([
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 3],
                            'butt' => ['quantity' => 1]
                        ]
                    ]
                ]
            ]),
        ]);

        RedisMetricsCollector::queueBatch($user->id, $photos);

        // Check accumulated counts
        $this->assertEquals('2', Redis::hGet("{u:{$user->id}}:stats", 'uploads'));
        $this->assertEquals('5', Redis::hGet("{u:{$user->id}}:t", (string)$this->tagIds['cup'])); // 2 + 3
        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:t", (string)$this->tagIds['butt']));
    }

    /**
     * Test XP accumulation
     */
    public function test_xp_accumulates_correctly(): void
    {
        $user = User::factory()->create();
        $photos = collect([
            Photo::factory()->for($user)->create(['xp' => 10.5]),
            Photo::factory()->for($user)->create(['xp' => 20.3]),
            Photo::factory()->for($user)->create(['xp' => 15.2]),
        ]);

        foreach ($photos as $photo) {
            RedisMetricsCollector::queue($photo);
        }

        // XP is cast to int in the implementation
        $expectedXp = 10 + 20 + 15;  // 10.5→10, 20.3→20, 15.2→15
        $actualXp = (int)Redis::hGet("{u:{$user->id}}:stats", 'xp');

        $this->assertEquals($expectedXp, $actualXp);
    }

    /**
     * Test materials and brands tracking
     */
    public function test_tracks_materials_and_brands(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 3,
                            'materials' => ['plastic' => 1],
                            'brands' => ['starbucks' => 3],
                        ]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:m", (string)$this->tagIds['plastic']));
        $this->assertEquals('3', Redis::hGet("{u:{$user->id}}:brands", (string)$this->tagIds['starbucks']));
    }

    /**
     * Test custom tags tracking
     */
    public function test_tracks_custom_tags(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 1,
                            'custom_tags' => ['biodegradable' => 1],
                        ]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:custom", (string)$this->tagIds['biodegradable']));
    }

    /**
     * Test streak tracking
     */
    public function test_streak_increments_for_consecutive_days(): void
    {
        $user = User::factory()->create();

        // Upload yesterday
        $yesterday = Photo::factory()->for($user)->create([
            'created_at' => now()->subDay()
        ]);
        RedisMetricsCollector::queue($yesterday);

        // Upload today
        $today = Photo::factory()->for($user)->create([
            'created_at' => now()
        ]);
        RedisMetricsCollector::queue($today);

        $this->assertEquals('2', Redis::hGet("{u:{$user->id}}:stats", 'streak'));
    }

    /**
     * Test streak resets after gap
     */
    public function test_streak_resets_after_gap(): void
    {
        $user = User::factory()->create();

        // Upload 3 days ago
        $oldPhoto = Photo::factory()->for($user)->create([
            'created_at' => now()->subDays(3)
        ]);
        RedisMetricsCollector::queue($oldPhoto);

        // Upload today (gap of 2 days)
        $today = Photo::factory()->for($user)->create([
            'created_at' => now()
        ]);
        RedisMetricsCollector::queue($today);

        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:stats", 'streak'));
    }

    /**
     * Test geographic scoping works
     */
    public function test_geographic_scoping_works(): void
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

        RedisMetricsCollector::queue($photo);

        $date = now()->format('Y-m-d');

        // Check all levels are tracked
        $this->assertEquals('1', Redis::hGet('{g}:t:p', $date));
        $this->assertEquals('1', Redis::hGet("c:{$country->id}:t:p", $date));
        $this->assertEquals('1', Redis::hGet("s:{$state->id}:t:p", $date));
        $this->assertEquals('1', Redis::hGet("ci:{$city->id}:t:p", $date));
    }

    /**
     * Test empty/invalid data handling
     */
    public function test_handles_empty_summary_gracefully(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => ['tags' => []]
        ]);

        RedisMetricsCollector::queue($photo);

        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:stats", 'uploads'));
        // No tag counts should exist
        $this->assertFalse(Redis::hExists("{u:{$user->id}}:t", (string)$this->tagIds['cup']));
    }

    /**
     * Test zero quantity handling
     */
    public function test_zero_quantity_does_not_increment(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 0]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Should track upload but not items
        $this->assertEquals('1', Redis::hGet("{u:{$user->id}}:stats", 'uploads'));
        $this->assertFalse(Redis::hExists("{u:{$user->id}}:t", (string)$this->tagIds['cup']));
    }

    /**
     * Test negative quantities are ignored
     */
    public function test_negative_quantities_are_ignored(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => -5]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Negative quantities should be treated as 0
        $this->assertFalse(Redis::hExists("{u:{$user->id}}:t", (string)$this->tagIds['cup']));
    }

    /**
     * Test batch tracking returns changed dimensions
     */
    public function test_batch_tracking_detects_changes(): void
    {
        $user = User::factory()->create();
        $photos = collect([
            Photo::factory()->for($user)->create([
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 1]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($user->id, $photos);

        $this->assertContains('uploads', $result['changed_dimensions']);
        $this->assertContains('objects', $result['changed_dimensions']);
        $this->assertContains('categories', $result['changed_dimensions']);

        $this->assertEquals(0, $result['previous_counts']['uploads']);
        $this->assertEquals(1, $result['new_counts']['uploads']);
    }

    /**
     * Test getUserCounts returns correct structure
     */
    public function test_get_user_counts_structure(): void
    {
        $user = User::factory()->create();

        // Process some data first
        $photo = Photo::factory()->for($user)->create([
            'xp' => 25.5,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 2,
                            'materials' => ['plastic' => 1],
                            'brands' => ['starbucks' => 2],
                            'custom_tags' => ['biodegradable' => 1]
                        ]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        $counts = RedisMetricsCollector::getUserCounts($user->id);

        // Check structure
        $this->assertArrayHasKey('uploads', $counts);
        $this->assertArrayHasKey('streak', $counts);
        $this->assertArrayHasKey('xp', $counts);
        $this->assertArrayHasKey('categories', $counts);
        $this->assertArrayHasKey('objects', $counts);
        $this->assertArrayHasKey('materials', $counts);
        $this->assertArrayHasKey('brands', $counts);
        $this->assertArrayHasKey('custom_tags', $counts);

        // Check values
        $this->assertEquals(1, $counts['uploads']);
        $this->assertEquals(25.0, $counts['xp']);  // Stored as int 25, but getUserCounts returns float
        $this->assertEquals(2, $counts['objects'][(string)$this->tagIds['cup']]);
        $this->assertEquals(1, $counts['materials'][(string)$this->tagIds['plastic']]);
        $this->assertEquals(2, $counts['brands'][(string)$this->tagIds['starbucks']]);
        $this->assertEquals(1, $counts['custom_tags'][(string)$this->tagIds['biodegradable']]);
    }

    /**
     * Test large batch doesn't timeout
     */
    public function test_processes_large_batch_within_reasonable_time(): void
    {
        $user = User::factory()->create();
        $photos = collect();

        // Create 100 photos with varied data
        for ($i = 0; $i < 100; $i++) {
            $photos->push(Photo::factory()->for($user)->create([
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => rand(1, 5)]
                        ]
                    ]
                ]
            ]));
        }

        $startTime = microtime(true);
        RedisMetricsCollector::queueBatch($user->id, $photos);
        $duration = microtime(true) - $startTime;

        // Should complete in reasonable time
        $this->assertLessThan(3.0, $duration, 'Large batch should process in under 3 seconds');

        // Verify all were processed
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(100, $counts['uploads']);
    }

    /**
     * Test month-based time series
     */
    public function test_month_time_series_tracking(): void
    {
        $user = User::factory()->create();

        // Photos in different months
        $currentMonth = Photo::factory()->for($user)->create([
            'xp' => 10,
            'created_at' => now()
        ]);
        $lastMonth = Photo::factory()->for($user)->create([
            'xp' => 20,
            'created_at' => now()->subMonth()
        ]);

        RedisMetricsCollector::queue($currentMonth);
        RedisMetricsCollector::queue($lastMonth);

        $currentKey = '{g}:' . now()->format('Y-m') . ':t';
        $lastKey = '{g}:' . now()->subMonth()->format('Y-m') . ':t';

        $this->assertEquals('1', Redis::hGet($currentKey, 'p'));
        $this->assertEquals('10', Redis::hGet($currentKey, 'xp'));  // XP stored as int
        $this->assertEquals('1', Redis::hGet($lastKey, 'p'));
        $this->assertEquals('20', Redis::hGet($lastKey, 'xp'));  // XP stored as int
    }

    // ============= NEW TESTS FOR LOCATION-SCOPED FUNCTIONALITY =============

    /**
     * Test location-scoped stats are written
     */
    public function test_location_stats_are_written(): void
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

        RedisMetricsCollector::queue($photo);

        // Check location stats
        $this->assertEquals('1', Redis::hGet("c:{$country->id}:stats", 'photos'));
        $this->assertEquals('1', Redis::hGet("s:{$state->id}:stats", 'photos'));
        $this->assertEquals('1', Redis::hGet("ci:{$city->id}:stats", 'photos'));
    }

    /**
     * Test location-scoped user sets
     */
    public function test_location_user_sets_are_maintained(): void
    {
        $country = Country::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 uploads
        $photo1 = Photo::factory()->for($user1)->create([
            'country_id' => $country->id,
        ]);
        RedisMetricsCollector::queue($photo1);

        // User 2 uploads
        $photo2 = Photo::factory()->for($user2)->create([
            'country_id' => $country->id,
        ]);
        RedisMetricsCollector::queue($photo2);

        // Check both users are in the set
        $this->assertEquals(2, Redis::sCard("c:{$country->id}:users"));
        $this->assertTrue(Redis::sIsMember("c:{$country->id}:users", (string)$user1->id));
        $this->assertTrue(Redis::sIsMember("c:{$country->id}:users", (string)$user2->id));
    }

    /**
     * Test location-scoped dimension hashes
     */
    public function test_location_dimension_hashes_are_written(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 3,
                            'materials' => ['plastic' => 2],
                            'brands' => ['starbucks' => 1],
                        ]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Check location dimensions
        $this->assertEquals('3', Redis::hGet("c:{$country->id}:c", (string)$this->tagIds['drinking']));
        $this->assertEquals('3', Redis::hGet("c:{$country->id}:t", (string)$this->tagIds['cup']));
        $this->assertEquals('2', Redis::hGet("c:{$country->id}:m", (string)$this->tagIds['plastic']));
        $this->assertEquals('1', Redis::hGet("c:{$country->id}:brands", (string)$this->tagIds['starbucks']));
    }

    /**
     * Test location monthly aggregates
     */
    public function test_location_monthly_aggregates(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'xp' => 15,
            'created_at' => now()
        ]);

        RedisMetricsCollector::queue($photo);

        $month = now()->format('Y-m');
        $monthKey = "c:{$country->id}:$month:t";

        $this->assertEquals('1', Redis::hGet($monthKey, 'p'));
        $this->assertEquals('15', Redis::hGet($monthKey, 'xp'));
    }

    /**
     * Test batch processing with location data
     */
    public function test_batch_processing_with_location_data(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);

        $user = User::factory()->create();
        $photos = collect([
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
                'state_id' => $state->id,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 2]
                        ]
                    ]
                ]
            ]),
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
                'state_id' => $state->id,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 3]
                        ]
                    ]
                ]
            ]),
        ]);

        RedisMetricsCollector::queueBatch($user->id, $photos);

        // Check location stats accumulated correctly
        $this->assertEquals('2', Redis::hGet("c:{$country->id}:stats", 'photos'));
        $this->assertEquals('2', Redis::hGet("s:{$state->id}:stats", 'photos'));

        // Check location dimensions accumulated
        $this->assertEquals('5', Redis::hGet("c:{$country->id}:t", (string)$this->tagIds['cup']));
        $this->assertEquals('5', Redis::hGet("s:{$state->id}:t", (string)$this->tagIds['cup']));

        // Check user is in location sets
        $this->assertTrue(Redis::sIsMember("c:{$country->id}:users", (string)$user->id));
        $this->assertTrue(Redis::sIsMember("s:{$state->id}:users", (string)$user->id));
    }

    /**
     * Test global scope doesn't write location-specific keys
     */
    public function test_global_scope_skips_location_specific_keys(): void
    {
        $user = User::factory()->create();

        // Photo with no location (only global scope)
        $photo = Photo::factory()->for($user)->create([
            'country_id' => null,
            'state_id' => null,
            'city_id' => null,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 1]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Global keys should exist
        $this->assertEquals('1', Redis::hGet('{g}:t', (string)$this->tagIds['cup']));

        // But no global:stats or global:users (we skip these for {g} scope)
        $this->assertFalse(Redis::hExists('{g}:stats', 'photos'));
        $this->assertEquals(0, Redis::sCard('{g}:users'));
    }
}
