<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\{BrandList, Category, CustomTagNew, LitterObject, Materials};
use App\Models\Location\{Country, State, City};
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Carbon\CarbonImmutable;
use Database\Seeders\AchievementsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Redis};
use Tests\TestCase;

class LongTermAchievementsTest extends TestCase
{
    use RefreshDatabase;

    private AchievementEngine $engine;
    private array $tags = [];
    private array $locations = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Complete Redis flush
        Redis::flushall();
        Cache::flush();
        TagKeyCache::forgetAll();

        $this->setupLocationData();
        $this->setupTagUniverse();

        // Configure achievements with more milestones for test 1
        config(['achievements.milestones' => [1, 5, 10, 20, 30, 42, 50, 69, 75, 100, 150, 200, 250, 300, 350, 420, 500, 1000, 1337]]);
        $this->seed(AchievementsSeeder::class);

        $this->engine = app(AchievementEngine::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function setupLocationData(): void
    {
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => $state->id]);

        $this->locations = compact('country', 'state', 'city');
    }

    private function setupTagUniverse(): void
    {
        // Objects
        $this->tags['objects'] = [
            'plastic_bottle' => LitterObject::firstOrCreate(['key' => 'plastic_bottle']),
            'can' => LitterObject::firstOrCreate(['key' => 'can']),
            'plastic_bag' => LitterObject::firstOrCreate(['key' => 'plastic_bag']),
            'mask' => LitterObject::firstOrCreate(['key' => 'mask']),
            'paper_cup' => LitterObject::firstOrCreate(['key' => 'paper_cup']),
            'straw' => LitterObject::firstOrCreate(['key' => 'straw']),
            'wrapper' => LitterObject::firstOrCreate(['key' => 'wrapper']),
            'cigarette_butt' => LitterObject::firstOrCreate(['key' => 'cigarette_butt']),
        ];

        // Categories
        $this->tags['categories'] = [
            'food' => Category::firstOrCreate(['key' => 'food']),
            'beverage' => Category::firstOrCreate(['key' => 'beverage']),
            'medical' => Category::firstOrCreate(['key' => 'medical']),
            'general' => Category::firstOrCreate(['key' => 'general']),
            'smoking' => Category::firstOrCreate(['key' => 'smoking']),
        ];

        // Materials
        $this->tags['materials'] = [
            'glass' => Materials::firstOrCreate(['key' => 'glass']),
            'plastic' => Materials::firstOrCreate(['key' => 'plastic']),
            'paper' => Materials::firstOrCreate(['key' => 'paper']),
            'metal' => Materials::firstOrCreate(['key' => 'metal']),
        ];

        // Brands
        $this->tags['brands'] = [
            'coca_cola' => BrandList::firstOrCreate(['key' => 'coca_cola']),
            'pepsi' => BrandList::firstOrCreate(['key' => 'pepsi']),
            'heineken' => BrandList::firstOrCreate(['key' => 'heineken']),
            'budweiser' => BrandList::firstOrCreate(['key' => 'budweiser']),
            'mcdonalds' => BrandList::firstOrCreate(['key' => 'mcdonalds']),
        ];

        // Custom tags
        $this->tags['custom'] = [
            'beach' => CustomTagNew::firstOrCreate(['key' => 'beach']),
            'park' => CustomTagNew::firstOrCreate(['key' => 'park']),
            'street' => CustomTagNew::firstOrCreate(['key' => 'street']),
            'forest' => CustomTagNew::firstOrCreate(['key' => 'forest']),
        ];

        TagKeyCache::forgetAll();
    }

    private function createRealisticPhoto(User $user, $timestamp, int $seed): Photo
    {
        $photo = new Photo();
        $photo->user_id = $user->id;
        $photo->created_at = $timestamp;
        $photo->country_id = $this->locations['country']->id;
        $photo->state_id = $this->locations['state']->id;
        $photo->city_id = $this->locations['city']->id;
        $photo->filename = "test_" . uniqid() . ".png";
        $photo->model = "iphone";
        $photo->datetime = $timestamp;
        $photo->lat = 51.5074;  // Add required lat
        $photo->lon = -0.1278;  // Add required lon

        // Create varied but realistic litter data
        $objectKeys = array_keys($this->tags['objects']);
        $categoryKeys = array_keys($this->tags['categories']);
        $materialKeys = array_keys($this->tags['materials']);
        $brandKeys = array_keys($this->tags['brands']);
        $customKeys = array_keys($this->tags['custom']);

        // Pick 1-3 categories
        $numCategories = ($seed % 3) + 1;
        $selectedCategories = array_slice($categoryKeys, $seed % count($categoryKeys), $numCategories);

        $tags = [];
        foreach ($selectedCategories as $catKey) {
            $tags[$catKey] = [];

            // Pick 1-4 objects per category
            $numObjects = ($seed % 4) + 1;
            $selectedObjects = array_slice($objectKeys, ($seed * 2) % count($objectKeys), $numObjects);

            foreach ($selectedObjects as $objKey) {
                $quantity = (($seed + 1) % 5) + 1; // 1-5 items

                $tags[$catKey][$objKey] = [
                    'quantity' => $quantity,
                    'materials' => [
                        $materialKeys[$seed % count($materialKeys)] => $quantity
                    ],
                    'brands' => [
                        $brandKeys[$seed % count($brandKeys)] => $quantity
                    ],
                    'custom_tags' => [
                        $customKeys[$seed % count($customKeys)] => $quantity
                    ]
                ];
            }
        }

        $photo->summary = ['tags' => $tags];
        $photo->save();

        // Process photo with metrics
        $metrics = $this->extractMetrics($tags);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        return $photo;
    }

    private function createPhotoWithNewTags(User $user, $timestamp, $brand, $material, $object): Photo
    {
        $photo = new Photo();
        $photo->user_id = $user->id;
        $photo->created_at = $timestamp;
        $photo->country_id = $this->locations['country']->id;
        $photo->state_id = $this->locations['state']->id;
        $photo->city_id = $this->locations['city']->id;
        $photo->filename = "test_" . uniqid() . ".png";
        $photo->model = "iphone";
        $photo->datetime = $timestamp;
        $photo->lat = 51.5074;  // Add required lat
        $photo->lon = -0.1278;  // Add required lon

        $tags = [
            'beverage' => [
                $object->key => [
                    'quantity' => 2,
                    'materials' => [$material->key => 2],
                    'brands' => [$brand->key => 2],
                ],
                'plastic_bottle' => [
                    'quantity' => 1,
                    'materials' => ['plastic' => 1],
                    'brands' => ['coca_cola' => 1],
                ]
            ]
        ];

        $photo->summary = ['tags' => $tags];
        $photo->save();

        // Process photo with metrics
        $metrics = $this->extractMetrics($tags);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        return $photo;
    }

    private function createPhotoWithQuantity(User $user, int $quantity): Photo
    {
        $photo = new Photo();
        $photo->user_id = $user->id;
        $photo->created_at = now();
        $photo->country_id = $this->locations['country']->id;
        $photo->state_id = $this->locations['state']->id;
        $photo->city_id = $this->locations['city']->id;
        $photo->filename = "test_" . uniqid() . ".png";
        $photo->model = "iphone";
        $photo->datetime = now();
        $photo->lat = 51.5074;  // Add required lat
        $photo->lon = -0.1278;  // Add required lon

        $tags = [
            'general' => [
                'plastic_bottle' => [
                    'quantity' => $quantity,
                    'materials' => ['plastic' => $quantity],
                    'brands' => ['coca_cola' => $quantity],
                ]
            ]
        ];

        $photo->summary = ['tags' => $tags];
        $photo->save();

        // Process photo with metrics
        $metrics = $this->extractMetrics($tags);
        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');

        return $photo;
    }

    private function extractMetrics(array $tags): array
    {
        $result = [
            'litter' => 0,
            'xp' => 1,
            'tags' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]
        ];

        foreach ($tags as $catKey => $objects) {
            $catId = (string)TagKeyCache::getOrCreateId('category', $catKey);
            $catTotal = 0;

            foreach ($objects as $objKey => $data) {
                $objId = (string)TagKeyCache::getOrCreateId('object', $objKey);
                $qty = max(0, (int)($data['quantity'] ?? 0));

                $result['litter'] += $qty;
                $result['tags']['objects'][$objId] = ($result['tags']['objects'][$objId] ?? 0) + $qty;
                $catTotal += $qty;

                // Process materials
                foreach ($data['materials'] ?? [] as $matKey => $matQty) {
                    $matId = (string)TagKeyCache::getOrCreateId('material', $matKey);
                    $result['tags']['materials'][$matId] = ($result['tags']['materials'][$matId] ?? 0) + max(0, (int)$matQty);
                }

                // Process brands
                foreach ($data['brands'] ?? [] as $brandKey => $brandQty) {
                    $brandId = (string)TagKeyCache::getOrCreateId('brand', $brandKey);
                    $result['tags']['brands'][$brandId] = ($result['tags']['brands'][$brandId] ?? 0) + max(0, (int)$brandQty);
                }

                // Process custom tags
                foreach ($data['custom_tags'] ?? [] as $customKey => $customQty) {
                    $customId = (string)TagKeyCache::getOrCreateId('customTag', $customKey);
                    $result['tags']['custom_tags'][$customId] = ($result['tags']['custom_tags'][$customId] ?? 0) + max(0, (int)$customQty);
                }
            }

            $result['tags']['categories'][$catId] = $catTotal;
        }

        return $result;
    }

    /** @test */
    public function engine_handles_heavy_production_load_over_six_months(): void
    {
        $user = User::factory()->create(['level' => 1]);
        $start = CarbonImmutable::parse('2025-01-01 10:00:00');
        $photosPerDay = 2;
        $totalDays = 180; // 6 months

        $unlockedAchievements = collect();
        $processingTimes = [];
        $photosProcessed = 0;
        $daysWithBreaks = 0;

        // Simulate 6 months of daily uploads
        for ($day = 0; $day < $totalDays; $day++) {
            // Skip every 7th day to add variety
            if ($day % 7 === 6) {
                $daysWithBreaks++;
                continue;
            }

            $currentDate = $start->addDays($day);

            for ($upload = 0; $upload < $photosPerDay; $upload++) {
                $startTime = microtime(true);

                $photo = $this->createRealisticPhoto($user, $currentDate, $photosProcessed);
                $unlocked = $this->engine->evaluate($user->id);

                if ($unlocked->isNotEmpty()) {
                    $unlockedAchievements = $unlockedAchievements->merge($unlocked);
                }

                $processingTimes[] = microtime(true) - $startTime;
                $photosProcessed++;
            }
        }

        // Calculate expected photos: (totalDays - daysWithBreaks) * photosPerDay
        $expectedPhotos = ($totalDays - $daysWithBreaks) * $photosPerDay;

        // Verify correct number of photos processed
        $actualUploads = (int) Redis::hGet("{u:{$user->id}}:stats", 'uploads');
        $this->assertEquals($expectedPhotos, $actualUploads, "Should have processed exactly {$expectedPhotos} photos");

        // Updated assertion - with more realistic expectations
        $this->assertGreaterThan(20, $unlockedAchievements->count(), 'Should unlock many achievements over 6 months');

        // Check major milestones were hit
        $this->assertAchievementUnlocked($user, 'uploads', null, 100);
        $this->assertAchievementUnlocked($user, 'uploads', null, 42);
        $this->assertAchievementUnlocked($user, 'objects', null, 100);

        // Count unique achievement types
        $achievementTypes = $unlockedAchievements->groupBy('type')->keys();
        $this->assertGreaterThanOrEqual(4, $achievementTypes->count(), 'Should unlock achievements across multiple dimensions');

        // Performance check
        $avgProcessingTime = array_sum($processingTimes) / count($processingTimes);
        $this->assertLessThan(0.1, $avgProcessingTime, 'Average processing time should be under 100ms');

        // Memory usage check
        $peakMemory = memory_get_peak_usage() / 1024 / 1024;
        $this->assertLessThan(256, $peakMemory, 'Peak memory should be under 256MB');
    }

    /** @test */
    public function handles_tag_additions_during_migration(): void
    {
        $user = User::factory()->create();
        $achievementsBefore = collect();
        $achievementsAfter = collect();

        // Process 50 photos with initial tags
        for ($i = 0; $i < 50; $i++) {
            $photo = $this->createRealisticPhoto($user, now()->addDays($i), $i);
            $unlocked = $this->engine->evaluate($user->id);
            $achievementsBefore = $achievementsBefore->merge($unlocked);
        }

        // Add new tags mid-migration (simulating new content)
        $newBrand = BrandList::firstOrCreate(['key' => 'starbucks']);
        $newMaterial = Materials::firstOrCreate(['key' => 'aluminium']);
        $newObject = LitterObject::firstOrCreate(['key' => 'coffee_cup']);

        // Clear caches to pick up new tags
        TagKeyCache::forgetAll();
        Cache::forget('achievements.definitions.v2');

        // Get the IDs that TagKeyCache will actually use
        $brandCacheId = TagKeyCache::getOrCreateId('brand', 'starbucks');
        $materialCacheId = TagKeyCache::getOrCreateId('material', 'aluminium');
        $objectCacheId = TagKeyCache::getOrCreateId('object', 'coffee_cup');

        // Create achievements for the new tags manually
        $milestones = config('achievements.milestones', [1, 10, 42, 69, 100, 420, 1337]);

        // Create brand achievements
        foreach ($milestones as $milestone) {
            Achievement::firstOrCreate([
                'type' => 'brand',
                'tag_id' => $brandCacheId,
                'threshold' => $milestone,
            ], [
                'metadata' => json_encode(['xp' => $milestone * 10])
            ]);
        }

        // Create material achievements
        foreach ($milestones as $milestone) {
            Achievement::firstOrCreate([
                'type' => 'material',
                'tag_id' => $materialCacheId,
                'threshold' => $milestone,
            ], [
                'metadata' => json_encode(['xp' => $milestone * 10])
            ]);
        }

        // Create object achievements
        foreach ($milestones as $milestone) {
            Achievement::firstOrCreate([
                'type' => 'object',
                'tag_id' => $objectCacheId,
                'threshold' => $milestone,
            ], [
                'metadata' => json_encode(['xp' => $milestone * 10])
            ]);
        }

        // Clear achievement cache to pick up new achievements
        Cache::forget('achievements.all');

        // Recreate engine to pick up changes
        $this->engine = app(AchievementEngine::class);

        // Process 50 more photos including new tags
        for ($i = 50; $i < 100; $i++) {
            $photo = $this->createPhotoWithNewTags($user, now()->addDays($i), $newBrand, $newMaterial, $newObject);
            $unlocked = $this->engine->evaluate($user->id);
            $achievementsAfter = $achievementsAfter->merge($unlocked);
        }

        // Should have achievements for new tags
        $this->assertAchievementUnlocked($user, 'brand', $brandCacheId, 1);
        $this->assertAchievementUnlocked($user, 'material', $materialCacheId, 1);
        $this->assertAchievementUnlocked($user, 'object', $objectCacheId, 1);
    }

    /** @test */
    public function handles_edge_cases_and_data_anomalies(): void
    {
        $user = User::factory()->create();

        // Test 1: Empty photo
        $emptyPhoto = new Photo();
        $emptyPhoto->user_id = $user->id;
        $emptyPhoto->summary = ['tags' => [], 'totals' => []];
        $emptyPhoto->created_at = now();
        $emptyPhoto->filename = "test_" . uniqid() . ".png";
        $emptyPhoto->model = "iphone";
        $emptyPhoto->datetime = now();
        $emptyPhoto->country_id = $this->locations['country']->id;
        $emptyPhoto->state_id = $this->locations['state']->id;
        $emptyPhoto->city_id = $this->locations['city']->id;
        $emptyPhoto->lat = 51.5074;  // Add required lat
        $emptyPhoto->lon = -0.1278;  // Add required lon
        $emptyPhoto->save();

        $metrics = ['litter' => 0, 'xp' => 1, 'tags' => [
            'categories' => [], 'objects' => [], 'materials' => [], 'brands' => [], 'custom_tags' => []
        ]];
        RedisMetricsCollector::processPhoto($emptyPhoto, $metrics, 'create');
        $unlocked = $this->engine->evaluate($user->id);
        $this->assertNotEmpty($unlocked); // Should unlock uploads-1
        $this->assertTrue($unlocked->where('type', 'uploads')->isNotEmpty());

        // Test 2: Photo with very large quantities
        $largePhoto = $this->createPhotoWithQuantity($user, 1000);
        $unlocked = $this->engine->evaluate($user->id);
        $this->assertGreaterThan(5, $unlocked->count()); // Should unlock multiple milestones

        // Test 3: Photo with unknown tags
        $unknownPhoto = new Photo();
        $unknownPhoto->user_id = $user->id;
        $unknownPhoto->summary = [
            'tags' => [
                'unknown_category' => [
                    'unknown_object' => ['quantity' => 10]
                ]
            ]
        ];
        $unknownPhoto->created_at = now();
        $unknownPhoto->filename = "test_" . uniqid() . ".png";
        $unknownPhoto->model = "iphone";
        $unknownPhoto->datetime = now();
        $unknownPhoto->country_id = $this->locations['country']->id;
        $unknownPhoto->state_id = $this->locations['state']->id;
        $unknownPhoto->city_id = $this->locations['city']->id;
        $unknownPhoto->lat = 51.5074;  // Add required lat
        $unknownPhoto->lon = -0.1278;  // Add required lon
        $unknownPhoto->save();

        $metrics = $this->extractMetrics($unknownPhoto->summary['tags']);
        RedisMetricsCollector::processPhoto($unknownPhoto, $metrics, 'create');
        $unlocked = $this->engine->evaluate($user->id);

        // Test 4: Rapid successive photos
        for ($i = 0; $i < 10; $i++) {
            $photo = $this->createRealisticPhoto($user, now()->addSeconds($i), 1000 + $i);
            $this->engine->evaluate($user->id);
        }

        // Verify no data corruption
        $counts = RedisMetricsCollector::getUserMetrics($user->id);
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('uploads', $counts);
        $this->assertGreaterThan(10, $counts['uploads']);
    }

    private function assertAchievementUnlocked(User $user, string $type, ?int $tagId, int $threshold): void
    {
        $query = Achievement::where('type', $type)
            ->where('threshold', $threshold);

        if ($tagId !== null) {
            $query->where('tag_id', $tagId);
        } else {
            $query->whereNull('tag_id');
        }

        $achievement = $query->first();
        $this->assertNotNull($achievement, "Achievement {$type}-{$tagId}-{$threshold} not found");

        // Check if user has this achievement by joining tables
        $hasAchievement = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $user->id)
            ->where('achievements.type', $type)
            ->where('achievements.threshold', $threshold)
            ->where(function($query) use ($tagId) {
                if ($tagId !== null) {
                    $query->where('achievements.tag_id', $tagId);
                } else {
                    $query->whereNull('achievements.tag_id');
                }
            })
            ->exists();

        $this->assertTrue($hasAchievement,
            "User {$user->id} should have achievement {$type}-{$tagId}-{$threshold}");
    }

    private function verifyAchievementsMatchCounts(User $user, array $counts): void
    {
        // Check uploads achievements
        $uploadsCount = $counts['uploads'];
        foreach ([1, 10, 42] as $milestone) {
            if ($uploadsCount >= $milestone) {
                $this->assertAchievementUnlocked($user, 'uploads', null, $milestone);
            }
        }

        // Check objects achievements
        $objectsCount = array_sum($counts['objects']);
        foreach ([1, 10, 42] as $milestone) {
            if ($objectsCount >= $milestone) {
                $this->assertAchievementUnlocked($user, 'objects', null, $milestone);
            }
        }

        // Check categories achievements
        $categoriesCount = count($counts['categories']);
        if ($categoriesCount >= 1) {
            $this->assertAchievementUnlocked($user, 'categories', null, 1);
        }
    }
}
