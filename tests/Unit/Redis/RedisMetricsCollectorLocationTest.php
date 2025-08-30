<?php

declare(strict_types=1);

namespace Tests\Unit\Redis;

use App\Models\Litter\Tags\BrandList;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Redis\RedisKeys;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisMetricsCollectorLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();

        // Seed brands for tests that need them
        $this->seed(GenerateBrandsSeeder::class);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    /**
     * Helper to extract metrics from photo summary
     */
    private function getMetricsFromPhoto(Photo $photo): array
    {
        $summary = $photo->summary ?? ['tags' => []];
        $tags = $summary['tags'] ?? [];

        // Calculate litter count from tags
        $litter = 0;
        $categories = [];
        $objects = [];
        $materials = [];
        $brands = [];
        $custom_tags = [];

        foreach ($tags as $categoryName => $categoryObjects) {
            // Use simple numeric IDs for testing
            $categoryId = crc32($categoryName) % 1000;

            foreach ($categoryObjects as $objectName => $objectData) {
                $objectId = crc32($objectName) % 1000;

                if (is_array($objectData)) {
                    $quantity = $objectData['quantity'] ?? 0;
                    $litter += $quantity;

                    $categories[$categoryId] = ($categories[$categoryId] ?? 0) + $quantity;
                    $objects[$objectId] = ($objects[$objectId] ?? 0) + $quantity;

                    // Handle materials
                    if (isset($objectData['materials'])) {
                        foreach ($objectData['materials'] as $materialName => $matCount) {
                            $materialId = crc32($materialName) % 1000;
                            $materials[$materialId] = ($materials[$materialId] ?? 0) + $matCount;
                        }
                    }

                    // Handle brands
                    if (isset($objectData['brands'])) {
                        foreach ($objectData['brands'] as $brandName => $brandCount) {
                            // Use actual brand IDs from database
                            $brand = BrandList::where('key', $brandName)->first();
                            if ($brand) {
                                $brands[$brand->id] = ($brands[$brand->id] ?? 0) + $brandCount;
                            }
                        }
                    }
                } else {
                    // Simple quantity value
                    $litter += $objectData;
                    $categories[$categoryId] = ($categories[$categoryId] ?? 0) + $objectData;
                    $objects[$objectId] = ($objects[$objectId] ?? 0) + $objectData;
                }
            }
        }

        return [
            'litter' => $litter,
            'xp' => $photo->xp ?? ($litter * 2), // Simple XP calculation for testing
            'tags' => [
                'categories' => $categories,
                'objects' => $objects,
                'materials' => $materials,
                'brands' => $brands,
                'custom_tags' => $custom_tags
            ]
        ];
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

        $metrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Check that stats.litter equals sum of objects
        $stats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $this->assertEquals('1', $stats['photos']);
        $this->assertEquals('5', $stats['litter']); // 3 cups + 2 bottles
    }

    /**
     * Test that ranking is created for locations
     */
    public function test_location_ranking_created(): void
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
            $metrics = $this->getMetricsFromPhoto($photo);
            RedisMetricsCollector::processPhoto($photo, $metrics, 'create');
        }

        // Use consistent IDs for testing
        $cupId = crc32('cup') % 1000;
        $bottleId = crc32('bottle') % 1000;

        // Check rankings exist and are ordered correctly
        $topObjects = Redis::zRevRange(
            RedisKeys::ranking(RedisKeys::country($country->id), 'objects'),
            0,
            -1,
            'WITHSCORES'
        );

        $this->assertNotEmpty($topObjects);

        // In Redis ZSET with WITHSCORES, the format is [member => score]
        // So the keys are the object IDs and values are the counts

        // Check that we have 2 objects
        $this->assertCount(2, $topObjects);

        // Find the actual cup and bottle scores
        $cupScore = $topObjects[(string)$cupId] ?? null;
        $bottleScore = $topObjects[(string)$bottleId] ?? null;

        // Verify the scores
        $this->assertEquals('7', $cupScore, 'Cup should have score of 7');
        $this->assertEquals('3', $bottleScore, 'Bottle should have score of 3');

        // Verify cup is ranked higher (should be first since we used zRevRange)
        $rankings = array_keys($topObjects);
        $this->assertEquals((string)$cupId, $rankings[0], 'Cup should be ranked first');
    }

    /**
     * Test ranking for brands
     */
    public function test_brand_ranking(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        // Create brands first if they don't exist
        $starbucksId = BrandList::firstOrCreate(['key' => 'starbucks'])->id;
        $cocacolaId = BrandList::firstOrCreate(['key' => 'coke'])->id;

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => [
                            'quantity' => 1,
                            'brands' => [
                                'starbucks' => 3,
                                'coke' => 1
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $metrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        $topBrands = Redis::zRevRange(
            RedisKeys::ranking(RedisKeys::country($country->id), 'brands'),
            0,
            -1,
            'WITHSCORES'
        );

        // Check we have the expected brands with correct counts
        $this->assertNotEmpty($topBrands);
        $this->assertCount(2, $topBrands);

        // In Redis ZSET with WITHSCORES, format is [member => score]
        // Find the actual brand scores
        $starbucksScore = $topBrands[(string)$starbucksId] ?? null;
        $cocacolaScore = $topBrands[(string)$cocacolaId] ?? null;

        // Verify the scores
        $this->assertEquals('3', $starbucksScore, 'Starbucks should have score of 3');
        $this->assertEquals('1', $cocacolaScore, 'Coca-Cola should have score of 1');

        // Verify starbucks is ranked higher
        $rankings = array_keys($topBrands);
        $this->assertEquals((string)$starbucksId, $rankings[0], 'Starbucks should be ranked first');
    }

    /**
     * Test batch processing updates litter counts correctly
     */
    public function test_multiple_photos_accumulate_litter_counts(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photos = [
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
                            'wrapper' => ['quantity' => 3]
                        ]
                    ]
                ]
            ])
        ];

        foreach ($photos as $photo) {
            $metrics = $this->getMetricsFromPhoto($photo);
            RedisMetricsCollector::processPhoto($photo, $metrics, 'create');
        }

        $stats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $this->assertEquals('2', $stats['photos']);
        $this->assertEquals('5', $stats['litter']); // 2 + 3
    }

    /**
     * Test that global scope works differently
     */
    public function test_global_scope_still_tracks_objects(): void
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

        $metrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        $cupId = crc32('cup') % 1000;

        // Global objects hash should be updated
        $this->assertEquals('5', Redis::hGet(RedisKeys::objects('{g}'), (string)$cupId));

        // Global rankings should also exist
        $score = Redis::zScore(RedisKeys::ranking('{g}', 'objects'), (string)$cupId);
        $this->assertEquals('5', $score);
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

        $metrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Check all levels have litter count
        $countryStats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $stateStats = Redis::hGetAll(RedisKeys::stats(RedisKeys::state($state->id)));
        $cityStats = Redis::hGetAll(RedisKeys::stats(RedisKeys::city($city->id)));

        $this->assertEquals('10', $countryStats['litter']);
        $this->assertEquals('10', $stateStats['litter']);
        $this->assertEquals('10', $cityStats['litter']);

        $cupId = crc32('cup') % 1000;

        // Check rankings exist at all levels
        $countryRank = Redis::zScore(RedisKeys::ranking(RedisKeys::country($country->id), 'objects'), (string)$cupId);
        $stateRank = Redis::zScore(RedisKeys::ranking(RedisKeys::state($state->id), 'objects'), (string)$cupId);
        $cityRank = Redis::zScore(RedisKeys::ranking(RedisKeys::city($city->id), 'objects'), (string)$cupId);

        $this->assertEquals('10', $countryRank);
        $this->assertEquals('10', $stateRank);
        $this->assertEquals('10', $cityRank);
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

        $metrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        $stats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $this->assertEquals('1', $stats['photos']);
        $this->assertEquals('0', $stats['litter'] ?? '0');
    }

    /**
     * Test updating photo with delta metrics
     */
    public function test_update_operation_applies_deltas(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 3]
                    ]
                ]
            ]
        ]);

        // Initial create
        $initialMetrics = $this->getMetricsFromPhoto($photo);
        RedisMetricsCollector::processPhoto($photo, $initialMetrics, 'create');

        // Update with delta (added 2 more cups)
        $deltaMetrics = [
            'litter' => 2,
            'xp' => 4,
            'tags' => [
                'categories' => [crc32('drinking') % 1000 => 2],
                'objects' => [crc32('cup') % 1000 => 2],
                'materials' => [],
                'brands' => [],
                'custom_tags' => []
            ]
        ];
        RedisMetricsCollector::processPhoto($photo, $deltaMetrics, 'update');

        $stats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $this->assertEquals('1', $stats['photos']); // Still 1 photo
        $this->assertEquals('5', $stats['litter']); // 3 + 2 = 5
    }

    /**
     * Test delete operation
     */
    public function test_delete_operation_decrements_stats(): void
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();

        $photo = Photo::factory()->for($user)->create([
            'country_id' => $country->id,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 5]
                    ]
                ]
            ]
        ]);

        $metrics = $this->getMetricsFromPhoto($photo);

        // Create
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        // Then delete
        RedisMetricsCollector::processPhoto($photo, $metrics, 'delete');

        $stats = Redis::hGetAll(RedisKeys::stats(RedisKeys::country($country->id)));
        $this->assertEquals('0', $stats['photos']);
        $this->assertEquals('0', $stats['litter']);
    }
}
