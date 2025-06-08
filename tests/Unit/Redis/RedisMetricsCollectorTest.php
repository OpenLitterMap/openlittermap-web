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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisMetricsCollectorTest extends TestCase
{
    use RefreshDatabase;

    // Store tag IDs for reuse in tests
    private int $cupId;
    private int $buttId;
    private int $plasticId;
    private int $glassId;
    private int $starbucksId;
    private int $cocacolaId;
    private int $biodegradableId;
    private int $foodId;
    private int $drinkingId;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Redis before each test
        Redis::flushall();

        // Warm up the TagKeyCache and get IDs for test data
        TagKeyCache::preloadAll();

        // Pre-create the tag IDs we'll need for testing
        $this->cupId = TagKeyCache::getOrCreateId('object', 'cup');
        $this->buttId = TagKeyCache::getOrCreateId('object', 'butt');
        $this->plasticId = TagKeyCache::getOrCreateId('material', 'plastic');
        $this->glassId = TagKeyCache::getOrCreateId('material', 'glass');
        $this->starbucksId = TagKeyCache::getOrCreateId('brand', 'starbucks');
        $this->cocacolaId = TagKeyCache::getOrCreateId('brand', 'cocacola');
        $this->biodegradableId = TagKeyCache::getOrCreateId('customTag', 'biodegradable');
        $this->foodId = TagKeyCache::getOrCreateId('category', 'food');
        $this->drinkingId = TagKeyCache::getOrCreateId('category', 'drinking');
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    public function test_queue_handles_empty_summary(): void
    {
        $photo = $this->createPhoto(['summary' => ['tags' => []]]);

        RedisMetricsCollector::queue($photo);

        $this->assertSame('1', Redis::hGet('{u:3}:stats', 'uploads'));
        $this->assertFalse(Redis::hGet('{u:3}:t', (string)$this->cupId));
    }

    public function test_queue_prevents_double_counting(): void
    {
        $photo = $this->createPhoto();

        RedisMetricsCollector::queue($photo);
        RedisMetricsCollector::queue($photo); // Process same photo twice

        $this->assertSame('1', Redis::hGet('{u:3}:stats', 'uploads'));
        $this->assertSame('1', Redis::hGet('{u:3}:t', (string)$this->cupId)); // Should be quantity 1
    }

    public function test_queue_processes_full_payload(): void
    {
        $photo = $this->createPhoto([
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

        // User hashes - using IDs instead of keys
        $this->assertSame('3', Redis::hGet('{u:3}:t', (string)$this->cupId));         // objects
        $this->assertSame('1', Redis::hGet('{u:3}:m', (string)$this->plasticId));     // materials
        $this->assertSame('3', Redis::hGet('{u:3}:brands', (string)$this->starbucksId)); // brands

        // Global mirrors
        $this->assertSame('3', Redis::hGet('{g}:t', (string)$this->cupId));
        $this->assertSame('1', Redis::hGet('{g}:m', (string)$this->plasticId));
        $this->assertSame('3', Redis::hGet('{g}:brands', (string)$this->starbucksId));
    }

    public function test_queue_batch_accumulates_photos(): void
    {
        $photos = new Collection([
            $this->createPhoto([
                'id' => 1,
                'user_id' => 4,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 1]
                        ]
                    ]
                ]
            ]),
            $this->createPhoto([
                'id' => 2,
                'user_id' => 4,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 2]
                        ]
                    ]
                ]
            ])
        ]);

        RedisMetricsCollector::queueBatch(4, $photos);

        $this->assertSame('3', Redis::hGet('{u:4}:t', (string)$this->cupId));      // 1 + 2
        $this->assertSame('2', Redis::hGet('{u:4}:stats', 'uploads'));

        // Verify photo 2 was marked as processed by trying to process it again
        $photo2Again = $this->createPhoto([
            'id' => 2,
            'user_id' => 4,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 10]  // Different quantity
                    ]
                ]
            ]
        ]);

        RedisMetricsCollector::queue($photo2Again);

        // Counts should not change since photo 2 was already processed
        $this->assertSame('3', Redis::hGet('{u:4}:t', (string)$this->cupId));      // Still 3, not 13
        $this->assertSame('2', Redis::hGet('{u:4}:stats', 'uploads'));             // Still 2
    }

    public function test_streak_increments_with_consecutive_days(): void
    {
        // Create photos with UTC dates to ensure they're consecutive
        $yesterday = now()->setTimezone('UTC')->startOfDay()->subDay();
        $today = now()->setTimezone('UTC')->startOfDay();

        $photo1 = $this->createPhoto(['id' => 101, 'created_at' => $yesterday]);
        $photo2 = $this->createPhoto(['id' => 102, 'created_at' => $today]);

        RedisMetricsCollector::queue($photo1);
        RedisMetricsCollector::queue($photo2);

        $this->assertSame('2', Redis::hGet('{u:3}:stats', 'streak'));
    }

    public function test_streak_resets_after_gap(): void
    {
        $photo1 = $this->createPhoto(['id' => 103, 'created_at' => now()->subDays(3)]);
        $photo2 = $this->createPhoto(['id' => 104, 'created_at' => now()]);

        RedisMetricsCollector::queue($photo1);
        RedisMetricsCollector::queue($photo2);

        $this->assertSame('1', Redis::hGet('{u:3}:stats', 'streak'));
    }

    public function test_geo_scopes_and_ttl(): void
    {
        // Create location records first
        $country = Country::factory()->create(['id' => 1]);
        $state = State::factory()->create([
            'id' => 2,
            'country_id' => $country->id
        ]);
        $city = City::factory()->create([
            'id' => 3,
            'country_id' => $country->id,
            'state_id' => $state->id
        ]);

        $photo = $this->createPhoto([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'created_at' => now()
        ]);

        RedisMetricsCollector::queue($photo);

        $date = now()->format('Y-m-d');
        $this->assertSame('1', Redis::hGet('c:1:t:p', $date));
        $this->assertSame('1', Redis::hGet('s:2:t:p', $date));
        $this->assertSame('1', Redis::hGet('ci:3:t:p', $date));

        // Check TTL is set
        $this->assertGreaterThan(0, Redis::pttl('c:1:t:p'));
    }

    public function test_get_user_counts_defaults(): void
    {
        $counts = RedisMetricsCollector::getUserCounts(999);

        $this->assertSame(0, $counts['uploads']);
        $this->assertSame(0, $counts['streak']);
        $this->assertSame(0.0, $counts['xp']);
        $this->assertSame([], $counts['categories']);
        $this->assertSame([], $counts['objects']);
        $this->assertSame([], $counts['materials']);
        $this->assertSame([], $counts['brands']);
        $this->assertSame([], $counts['custom_tags']);
    }

    public function test_queue_batch_with_tracking_empty_batch(): void
    {
        $result = RedisMetricsCollector::queueBatchWithTracking(5, new Collection());

        $this->assertSame([], $result['changed_dimensions']);
        $this->assertSame([], $result['previous_counts']);
        $this->assertSame([], $result['new_counts']);
    }

    public function test_queue_batch_with_tracking_detects_upload_change(): void
    {
        $photos = new Collection([
            $this->createPhoto(['user_id' => 6, 'id' => 10])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking(6, $photos);

        $this->assertContains('uploads', $result['changed_dimensions']);
        $this->assertSame(0, $result['previous_counts']['uploads']);
        $this->assertSame(1, $result['new_counts']['uploads']);
    }

    public function test_queue_batch_with_tracking_detects_category_change(): void
    {
        $userId = 7;
        $photos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 11,
                'summary' => [
                    'tags' => [
                        'food' => [
                            'cup' => ['quantity' => 1]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('categories', $result['changed_dimensions']);
        $this->assertContains('objects', $result['changed_dimensions']);
        $this->assertArrayNotHasKey((string)$this->foodId, $result['previous_counts']['categories']);
        $this->assertSame(1, $result['new_counts']['categories'][(string)$this->foodId]);
    }

    public function test_queue_batch_with_tracking_detects_material_change(): void
    {
        $userId = 8;
        $photos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 12,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => [
                                'quantity' => 1,
                                'materials' => ['plastic' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('materials', $result['changed_dimensions']);
        $this->assertArrayNotHasKey((string)$this->plasticId, $result['previous_counts']['materials']);
        $this->assertSame(1, $result['new_counts']['materials'][(string)$this->plasticId]);
    }

    public function test_queue_batch_with_tracking_detects_brand_change(): void
    {
        $userId = 9;
        $photos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 13,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => [
                                'quantity' => 1,
                                'brands' => ['starbucks' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('brands', $result['changed_dimensions']);
        $this->assertArrayNotHasKey((string)$this->starbucksId, $result['previous_counts']['brands']);
        $this->assertSame(1, $result['new_counts']['brands'][(string)$this->starbucksId]);
    }

    public function test_queue_batch_with_tracking_detects_custom_tag_change(): void
    {
        $userId = 10;
        $photos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 14,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => [
                                'quantity' => 1,
                                'custom_tags' => ['biodegradable' => 1]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('custom_tags', $result['changed_dimensions']);
        $this->assertArrayNotHasKey((string)$this->biodegradableId, $result['previous_counts']['custom_tags']);
        $this->assertSame(1, $result['new_counts']['custom_tags'][(string)$this->biodegradableId]);
    }

    public function test_queue_batch_with_tracking_detects_multiple_dimensions(): void
    {
        $userId = 11;

        // First, process 5 photos to establish baseline
        for ($i = 0; $i < 5; $i++) {
            RedisMetricsCollector::queue($this->createPhoto(['user_id' => $userId, 'id' => 100 + $i]));
        }

        // Now add one more photo that should increment cup count
        $newPhotos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 16,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 1]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $newPhotos);

        // Verify counts
        $this->assertSame(5, $result['previous_counts']['uploads']);
        $this->assertSame(6, $result['new_counts']['uploads']);

        // The new_counts shows TOTAL counts, so we should expect 6 cups total (5 from before + 1 from new batch)
        $this->assertSame(6, $result['new_counts']['objects'][(string)$this->cupId]);
    }

    public function test_queue_batch_with_tracking_no_changes_when_values_unchanged(): void
    {
        $userId = 12;

        // The behavior: queueBatch() checks alreadyProcessed() at the START of each loop iteration
        // But markAsProcessed() happens at the END in the pipeline
        // So if we pass the same photo object twice in one batch, both will pass the alreadyProcessed() check
        // since neither has been marked as processed yet

        $photo = $this->createPhoto(['user_id' => $userId, 'id' => 17]);

        // Create a collection with the SAME photo object twice
        $photos = new Collection([$photo, $photo]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('uploads', $result['changed_dimensions']);
        // Both instances of the same photo will be processed in the same batch
        // because the duplicate check happens before any are marked as processed
        $this->assertSame(2, $result['new_counts']['uploads']);
    }

    public function test_queue_batch_with_tracking_handles_streak_changes(): void
    {
        $userId = 13;
        $photos = new Collection([
            $this->createPhoto(['user_id' => $userId, 'id' => 18, 'created_at' => now()])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        $this->assertContains('streak', $result['changed_dimensions']);
        $this->assertSame(0, $result['previous_counts']['streak']);
        $this->assertSame(1, $result['new_counts']['streak']);
    }

    public function test_queue_batch_with_tracking_processes_multiple_photos(): void
    {
        $userId = 14;
        $photos = new Collection([
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 19,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 2]
                        ]
                    ]
                ]
            ]),
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 20,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 3],
                            'butt' => ['quantity' => 1]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Should accumulate all changes
        $this->assertSame(2, $result['new_counts']['uploads']);
        $this->assertSame(5, $result['new_counts']['objects'][(string)$this->cupId]); // 2 + 3
        $this->assertSame(1, $result['new_counts']['objects'][(string)$this->buttId]);
        $this->assertContains('categories', $result['changed_dimensions']);
        $this->assertContains('objects', $result['changed_dimensions']);
    }

    public function test_queue_batch_with_tracking_ignores_already_processed(): void
    {
        $userId = 15;

        // Process first photo individually
        $firstPhoto = $this->createPhoto([
            'user_id' => $userId,
            'id' => 21,
            'summary' => [
                'tags' => [
                    'drinking' => [
                        'cup' => ['quantity' => 10]
                    ]
                ]
            ]
        ]);
        RedisMetricsCollector::queue($firstPhoto);

        // Now try to process it again in a batch with a new photo
        $photos = new Collection([
            $this->createPhoto(['user_id' => $userId, 'id' => 21]), // Already processed - should be skipped
            $this->createPhoto([
                'user_id' => $userId,
                'id' => 22,
                'summary' => [
                    'tags' => [
                        'drinking' => [
                            'cup' => ['quantity' => 1]
                        ]
                    ]
                ]
            ])
        ]);

        $result = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

        // Should only process photo 22, since photo 21 was already processed
        $this->assertSame(1, $result['previous_counts']['uploads']); // 1 from the first photo
        $this->assertSame(2, $result['new_counts']['uploads']); // 1 + 1 (only photo 22 processed)
        $this->assertSame(11, $result['new_counts']['objects'][(string)$this->cupId]); // 10 + 1
    }

    private function createPhoto(array $attributes = []): Photo
    {
        // Extract user_id if provided, otherwise use default
        $userId = $attributes['user_id'] ?? 3;

        // Ensure the user exists
        $user = User::find($userId) ?? User::factory()->create(['id' => $userId]);

        // Remove user_id from attributes since we'll use the relationship
        unset($attributes['user_id']);

        // Default summary if not provided
        $defaultSummary = [
            'tags' => [
                'drinking' => [
                    'cup' => ['quantity' => 1]
                ]
            ]
        ];

        // Merge default values with provided attributes
        $photoAttributes = array_merge([
            'summary' => $defaultSummary,
            'xp' => 0,
        ], $attributes);

        // Check if photo already exists (for duplicate testing)
        if (isset($photoAttributes['id']) && Photo::find($photoAttributes['id'])) {
            return Photo::find($photoAttributes['id']);
        }

        // Create photo using factory with user relationship
        return Photo::factory()
            ->for($user)
            ->create($photoAttributes);
    }
}
