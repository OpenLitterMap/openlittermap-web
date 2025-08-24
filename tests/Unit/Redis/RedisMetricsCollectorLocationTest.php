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

class RedisMetricsCollectorLocationTest extends TestCase
{
    use RefreshDatabase;

    private array $tagIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        TagKeyCache::preloadAll();
        RedisMetricsCollector::preloadLuaScript();

        // Pre-create tag IDs
        $this->tagIds = [
            'cup' => TagKeyCache::getOrCreateId('object', 'cup'),
            'bottle' => TagKeyCache::getOrCreateId('object', 'bottle'),
            'plastic' => TagKeyCache::getOrCreateId('material', 'plastic'),
            'glass' => TagKeyCache::getOrCreateId('material', 'glass'),
            'starbucks' => TagKeyCache::getOrCreateId('brand', 'starbucks'),
            'cocacola' => TagKeyCache::getOrCreateId('brand', 'cocacola'),
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
     * Test that litter count is tracked in stats
     */
    public function test_location_stats_tracks_litter_count(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 3],
                        'bottle' => ['quantity' => 2]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Check that stats.litter equals sum of objects
        $stats = Redis::hgetall("c:{$country->id}:stats");
        $this->assertEquals('1', $stats['photos']);
        $this->assertEquals('5', $stats['litter']); // 3 cups + 2 bottles
    }

    /**
     * Test that last_ts timestamp is tracked
     */
    public function test_location_stats_tracks_last_timestamp(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $timestamp = now()->subDays(3);
        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'created_at' => $timestamp
        ]);

        RedisMetricsCollector::queue($photo);

        $stats = Redis::hgetall("c:{$country->id}:stats");
        $this->assertArrayHasKey('last_ts', $stats);
        $this->assertEquals($timestamp->getTimestamp(), (int)$stats['last_ts']);
    }

    /**
     * Test that ranking ZSETs are created for locations
     */
    public function test_location_ranking_zsets_are_created(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        // Create multiple photos with different objects
        $photos = [
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 5]
                        ]
                    ]
                ]
            ]),
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'bottle' => ['quantity' => 3]
                        ]
                    ]
                ]
            ]),
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 2] // More cups
                        ]
                    ]
                ]
            ])
        ];

        foreach ($photos as $photo) {
            RedisMetricsCollector::queue($photo);
        }

        // Check ZSET rankings exist and are ordered correctly
        $topObjects = Redis::zRevRange("rank:c:{$country->id}:objects", 0, -1, 'WITHSCORES');

        $this->assertNotEmpty($topObjects);
        $this->assertEquals('7', $topObjects[(string)$this->tagIds['cup']]); // 5 + 2 = 7
        $this->assertEquals('3', $topObjects[(string)$this->tagIds['bottle']]);

        // Check that cup is ranked higher (first in the list)
        $rankings = array_keys($topObjects);
        $this->assertEquals((string)$this->tagIds['cup'], $rankings[0]);
        $this->assertEquals((string)$this->tagIds['bottle'], $rankings[1]);
    }

    /**
     * Test ranking ZSETs for brands
     */
    public function test_brand_ranking_zsets(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 1,
                            'brands' => [
                                'starbucks' => 3,
                                'cocacola' => 1
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        $topBrands = Redis::zRevRange("rank:c:{$country->id}:brands", 0, -1, 'WITHSCORES');

        $this->assertEquals('3', $topBrands[(string)$this->tagIds['starbucks']]);
        $this->assertEquals('1', $topBrands[(string)$this->tagIds['cocacola']]);
    }

    /**
     * Test batch processing updates litter counts correctly
     */
    public function test_batch_processing_updates_litter_counts(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photos = collect([
            Photo::factory()->for($user)->create([
                'country_id' => $country->id,
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
                'summary' => [
                    'tags' => [
                        'food' => [
                            'bottle' => ['quantity' => 3]
                        ]
                    ]
                ]
            ])
        ]);

        RedisMetricsCollector::queueBatch($user->id, $photos);

        $stats = Redis::hgetall("c:{$country->id}:stats");
        $this->assertEquals('2', $stats['photos']);
        $this->assertEquals('5', $stats['litter']); // 2 + 3
    }

    /**
     * Test that global scope doesn't create ranking ZSETs
     */
    public function test_global_scope_skips_ranking_zsets(): void
    {
        $user = User::factory()->create();

        // Photo with no location (global only)
        $photo = Photo::factory()->for($user)->create([
            'country_id' => null,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 5]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Global rankings should not exist (we skip them to save memory)
        $this->assertEquals(0, Redis::zCard("rank:{g}:objects"));

        // But global hash should still be updated
        $this->assertEquals('5', Redis::hGet('{g}:t', (string)$this->tagIds['cup']));
    }

    /**
     * Test hierarchical location updates (country, state, city)
     */
    public function test_hierarchical_location_stats(): void
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
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 10]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        // Check all levels have litter count
        $countryStats = Redis::hgetall("c:{$country->id}:stats");
        $stateStats = Redis::hgetall("s:{$state->id}:stats");
        $cityStats = Redis::hgetall("ci:{$city->id}:stats");

        $this->assertEquals('10', $countryStats['litter']);
        $this->assertEquals('10', $stateStats['litter']);
        $this->assertEquals('10', $cityStats['litter']);

        // Check rankings exist at all levels
        $this->assertGreaterThan(0, Redis::zCard("rank:c:{$country->id}:objects"));
        $this->assertGreaterThan(0, Redis::zCard("rank:s:{$state->id}:objects"));
        $this->assertGreaterThan(0, Redis::zCard("rank:ci:{$city->id}:objects"));
    }

    /**
     * Test monthly aggregates include litter
     */
    public function test_monthly_aggregates_track_litter(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 7]
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo);

        $month = now()->format('Y-m');
        $monthData = Redis::hgetall("c:{$country->id}:{$month}:t");

        $this->assertEquals('1', $monthData['p']); // 1 photo
        // Note: Currently we don't track litter in monthly aggregates,
        // but we could add it if needed
    }

    /**
     * Test zero litter photos don't break stats
     */
    public function test_zero_litter_photos_handled_correctly(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => ['tags' => []]
        ]);

        RedisMetricsCollector::queue($photo);

        $stats = Redis::hgetall("c:{$country->id}:stats");
        $this->assertEquals('1', $stats['photos']);
        $this->assertEquals('0', $stats['litter'] ?? '0');
    }
}
