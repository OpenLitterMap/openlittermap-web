<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Database\Seeders\AchievementsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    private AchievementEngine $engine;
    private AchievementRepository $repository;

    // Common tag IDs used across tests
    private array $tagIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Clear caches
        Redis::flushDB();
        Cache::flush();
        TagKeyCache::forgetAll();

        // Run seeders
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
            AchievementsSeeder::class,
        ]);

        // Preload tag cache and store commonly used IDs
        TagKeyCache::preloadAll();
        $this->tagIds = [
            'objects' => [
                'wrapper' => TagKeyCache::getOrCreateId('object', 'wrapper'),
                'cup' => TagKeyCache::getOrCreateId('object', 'cup'),
                'water_bottle' => TagKeyCache::getOrCreateId('object', 'water_bottle'),
            ],
            'categories' => [
                'food' => TagKeyCache::getOrCreateId('category', 'food'),
                'softdrinks' => TagKeyCache::getOrCreateId('category', 'softdrinks'),
            ],
            'materials' => [
                'plastic' => TagKeyCache::getOrCreateId('material', 'plastic'),
                'paper' => TagKeyCache::getOrCreateId('material', 'paper'),
            ],
            'brands' => [
                'coca_cola' => TagKeyCache::getOrCreateId('brand', 'coca_cola'),
                'starbucks' => TagKeyCache::getOrCreateId('brand', 'starbucks'),
            ],
        ];

        // Get services
        $this->engine = app(AchievementEngine::class);
        $this->repository = app(AchievementRepository::class);
    }

    /**
     * Helper to create a photo with proper Redis metrics tracking
     */
    private function createAndProcessPhoto(User $user, array $tags, ?string $createdAt = null): Photo
    {
        $photo = Photo::factory()->for($user)->create([
            'summary' => ['tags' => $tags],
            'created_at' => $createdAt ?? now(),
        ]);

        RedisMetricsCollector::queue($photo);

        return $photo;
    }

    /**
     * Helper to assert an achievement was unlocked
     */
    private function assertAchievementUnlocked(User $user, string $type, ?int $tagId, int $threshold): void
    {
        $achievement = Achievement::where('type', $type)
            ->where('threshold', $threshold)
            ->when($tagId !== null, fn($q) => $q->where('tag_id', $tagId))
            ->when($tagId === null, fn($q) => $q->whereNull('tag_id'))
            ->first();

        $this->assertNotNull($achievement, "Achievement {$type}-{$tagId}-{$threshold} not found");

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /**
     * Helper to assert an achievement was NOT unlocked
     */
    private function assertAchievementNotUnlocked(User $user, string $type, ?int $tagId, int $threshold): void
    {
        $achievement = Achievement::where('type', $type)
            ->where('threshold', $threshold)
            ->when($tagId !== null, fn($q) => $q->where('tag_id', $tagId))
            ->when($tagId === null, fn($q) => $q->whereNull('tag_id'))
            ->first();

        if (!$achievement) {
            $this->assertTrue(true); // Achievement doesn't exist, so it can't be unlocked
            return;
        }

        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /**
     * @test
     * @group achievements
     */
    public function first_upload_unlocks_basic_achievements(): void
    {
        $user = User::factory()->create();

        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should unlock: uploads-1, categories-1, objects-1
        $this->assertGreaterThanOrEqual(3, $unlocked->count());
        $this->assertAchievementUnlocked($user, 'uploads', null, 1);
        $this->assertAchievementUnlocked($user, 'categories', null, 1);
        $this->assertAchievementUnlocked($user, 'objects', null, 1);
    }

    /**
     * @test
     * @group achievements
     */
    public function per_tag_achievements_unlock_at_thresholds(): void
    {
        $user = User::factory()->create();

        // Upload exactly 42 wrappers
        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 42]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should unlock wrapper achievements for thresholds <= 42
        $wrapperAchievements = Achievement::where('type', 'object')
            ->where('tag_id', $this->tagIds['objects']['wrapper'])
            ->where('threshold', '<=', 42)
            ->pluck('threshold')
            ->toArray();

        foreach ($wrapperAchievements as $threshold) {
            $this->assertAchievementUnlocked($user, 'object', $this->tagIds['objects']['wrapper'], $threshold);
        }

        // Should NOT unlock wrapper-69 (if it exists)
        $this->assertAchievementNotUnlocked($user, 'object', $this->tagIds['objects']['wrapper'], 69);
    }

    /**
     * @test
     * @group achievements
     */
    public function category_achievements_count_total_items(): void
    {
        $user = User::factory()->create();

        // 30 wrappers + 12 cups = 42 total in food category
        $this->createAndProcessPhoto($user, [
            'food' => [
                'wrapper' => ['quantity' => 30],
                'cup' => ['quantity' => 12],
            ]
        ]);

        $this->engine->evaluate($user->id);

        // Verify Redis counts
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(42, $counts['categories'][(string)$this->tagIds['categories']['food']]);

        // Should unlock food category achievements up to 42
        $foodAchievements = Achievement::where('type', 'category')
            ->where('tag_id', $this->tagIds['categories']['food'])
            ->where('threshold', '<=', 42)
            ->pluck('threshold')
            ->toArray();

        foreach ($foodAchievements as $threshold) {
            $this->assertAchievementUnlocked($user, 'category', $this->tagIds['categories']['food'], $threshold);
        }
    }

    /**
     * @test
     * @group achievements
     */
    public function dimension_wide_achievements_track_totals(): void
    {
        $user = User::factory()->create();

        $this->createAndProcessPhoto($user, [
            'softdrinks' => [
                'water_bottle' => [
                    'quantity' => 30,
                    'materials' => ['plastic' => 30],
                    'brands' => ['coca_cola' => 30],
                ]
            ],
            'food' => [
                'wrapper' => [
                    'quantity' => 12,
                    'materials' => ['paper' => 12],
                    'brands' => ['starbucks' => 12],
                ]
            ]
        ]);

        $this->engine->evaluate($user->id);

        // Total objects: 42 (30 + 12)
        // Total materials: 42 (30 + 12)
        // Total brands: 42 (30 + 12)
        // Categories: 2 (food, softdrinks)

        $this->assertAchievementUnlocked($user, 'objects', null, 42);
        $this->assertAchievementUnlocked($user, 'materials', null, 42);
        $this->assertAchievementUnlocked($user, 'brands', null, 42);
        $this->assertAchievementUnlocked($user, 'categories', null, 1);
    }

    /**
     * @test
     * @group achievements
     */
    public function achievements_are_idempotent(): void
    {
        $user = User::factory()->create();

        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 10]]
        ]);

        // Evaluate multiple times
        $first = $this->engine->evaluate($user->id);
        $second = $this->engine->evaluate($user->id);
        $third = $this->engine->evaluate($user->id);

        // First should unlock achievements
        $this->assertNotEmpty($first);

        // Subsequent evaluations should return empty
        $this->assertEmpty($second);
        $this->assertEmpty($third);

        // No duplicates in database
        $duplicates = DB::table('user_achievements')
            ->select('achievement_id', DB::raw('COUNT(*) as count'))
            ->where('user_id', $user->id)
            ->groupBy('achievement_id')
            ->having('count', '>', 1)
            ->get();

        $this->assertEmpty($duplicates);
    }

    /**
     * @test
     * @group achievements
     * @dataProvider edgeQuantityProvider
     */
    public function handles_edge_case_quantities($quantity, int $expectedCount): void
    {
        $user = User::factory()->create();

        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => $quantity]]
        ]);

        $this->engine->evaluate($user->id);

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $actualCount = $counts['objects'][(string)$this->tagIds['objects']['wrapper']] ?? 0;

        $this->assertEquals($expectedCount, $actualCount);
    }

    public static function edgeQuantityProvider(): array
    {
        return [
            'negative quantity' => [-5, 0],
            'zero quantity' => [0, 0],
            'float truncates' => [3.7, 3],
            'string coerces' => ['10', 10],
            'null treated as zero' => [null, 0],
            'very large number' => [1000000, 1000000], // 1 million
        ];
    }

    /**
     * @test
     * @group achievements
     */
    public function streak_tracks_consecutive_days(): void
    {
        $user = User::factory()->create();

        // Day 1
        $this->createAndProcessPhoto($user,
            ['food' => ['wrapper' => ['quantity' => 1]]],
            '2025-01-20 12:00:00'
        );

        // Day 2 (consecutive)
        $this->createAndProcessPhoto($user,
            ['food' => ['wrapper' => ['quantity' => 1]]],
            '2025-01-21 12:00:00'
        );

        $this->engine->evaluate($user->id);

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(2, $counts['streak']);

        // Day 4 (gap)
        $this->createAndProcessPhoto($user,
            ['food' => ['wrapper' => ['quantity' => 1]]],
            '2025-01-23 12:00:00'
        );

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(1, $counts['streak']);
    }

    /**
     * @test
     * @group achievements
     */
    public function streak_achievements_unlock_at_milestones(): void
    {
        // Check if streak achievements are implemented
        $streakAchievementCount = Achievement::where('type', 'streak')->count();
        if ($streakAchievementCount === 0) {
            $this->markTestSkipped(
                'Streak achievements are not implemented yet. ' .
                'Add "streak" to the $types array in AchievementsSeeder::seedDimensionWide() to enable them.'
            );
        }

        $user = User::factory()->create();

        // Create photos for 7 consecutive days
        for ($i = 6; $i >= 0; $i--) {
            $this->createAndProcessPhoto($user,
                ['food' => ['wrapper' => ['quantity' => 1]]],
                now()->subDays($i)->format('Y-m-d 12:00:00')
            );
        }

        // Evaluate achievements
        $this->engine->evaluate($user->id);

        // Verify streak count
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(7, $counts['streak']);

        // Check if user unlocked any streak achievements
        $userStreakAchievements = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $user->id)
            ->where('achievements.type', 'streak')
            ->count();

        $this->assertGreaterThan(0, $userStreakAchievements,
            'User should have unlocked at least one streak achievement with a 7-day streak');
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_unknown_tags_gracefully(): void
    {
        $user = User::factory()->create();

        $this->createAndProcessPhoto($user, [
            'unknown_category' => ['unknown_object' => ['quantity' => 10]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should still unlock dimension-wide achievements
        $this->assertAchievementUnlocked($user, 'uploads', null, 1);
        $this->assertAchievementUnlocked($user, 'categories', null, 1);
        $this->assertAchievementUnlocked($user, 'objects', null, 1);

        // Unknown tags get IDs from TagKeyCache
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(1, count($counts['categories']));
        $this->assertEquals(10, array_sum($counts['objects']));
    }

    /**
     * @test
     * @group achievements
     */
    public function cache_invalidates_on_unlock(): void
    {
        $user = User::factory()->create();

        // Check cache is empty
        $cached1 = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertEmpty($cached1);

        // Unlock achievement
        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);
        $this->engine->evaluate($user->id);

        // Cache should reflect new unlocks
        $cached2 = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertNotEmpty($cached2);
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_database_errors_gracefully(): void
    {
        $user = User::factory()->create();

        // Create a photo that would unlock achievements
        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 100]]
        ]);

        // Mock repository to throw exception during unlock
        $mockRepo = $this->createMock(AchievementRepository::class);
        $mockRepo->method('getUnlockedAchievementIds')->willReturn([]);
        $mockRepo->method('getAchievementDefinitions')->willReturn(
            $this->repository->getAchievementDefinitions()
        );
        $mockRepo->method('unlockAchievements')
            ->willThrowException(new \Exception('Database error'));

        // Create engine with mocked repository
        $engine = new AchievementEngine($mockRepo, app()->tagged('achievement.checkers'));

        // Should handle exception gracefully
        $result = $engine->evaluate($user->id);
        $this->assertEmpty($result);
    }

    /**
     * @test
     * @group achievements
     */
    public function single_photo_can_unlock_multiple_achievements(): void
    {
        $user = User::factory()->create();

        // One photo with 69 items
        $this->createAndProcessPhoto($user, [
            'food' => [
                'wrapper' => [
                    'quantity' => 69,
                    'materials' => ['plastic' => 69],
                    'brands' => ['coca_cola' => 69],
                ]
            ]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should unlock multiple thresholds: 1, 42, 69 for multiple dimensions
        $types = $unlocked->pluck('type')->unique();
        $this->assertContains('uploads', $types);
        $this->assertContains('objects', $types);
        $this->assertContains('materials', $types);
        $this->assertContains('brands', $types);

        // Verify specific milestones
        $objectMilestones = $unlocked
            ->where('type', 'objects')
            ->whereNull('tag_id')
            ->pluck('threshold')
            ->sort()
            ->values()
            ->toArray();

        $this->assertContains(1, $objectMilestones);
        if (in_array(42, config('achievements.milestones', []))) {
            $this->assertContains(42, $objectMilestones);
        }
        if (in_array(69, config('achievements.milestones', []))) {
            $this->assertContains(69, $objectMilestones);
        }
    }

    /**
     * @test
     * @group achievements
     */
    public function batch_processing_counts_correctly(): void
    {
        $user = User::factory()->create();
        $photoCount = 50;

        // Create photos
        $photos = collect();
        for ($i = 0; $i < $photoCount; $i++) {
            $photos->push(Photo::factory()->for($user)->create([
                'summary' => ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]]
            ]));
        }

        // Process as batch
        RedisMetricsCollector::queueBatch($user->id, $photos);
        $unlocked = $this->engine->evaluate($user->id);

        // Verify counts
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals($photoCount, $counts['uploads']);
        $this->assertEquals($photoCount, $counts['objects'][(string)$this->tagIds['objects']['wrapper']]);

        // Should have unlocked some achievements
        $this->assertNotEmpty($unlocked);
    }

    /**
     * @test
     * @group achievements
     */
    public function achievements_respect_user_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 uploads 42 items
        $this->createAndProcessPhoto($user1, [
            'food' => ['wrapper' => ['quantity' => 42]]
        ]);
        $this->engine->evaluate($user1->id);

        // User 2 uploads 1 item
        $this->createAndProcessPhoto($user2, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);
        $this->engine->evaluate($user2->id);

        // User 1 should have 42-threshold achievements
        $this->assertAchievementUnlocked($user1, 'objects', null, 42);

        // User 2 should NOT have 42-threshold achievements
        $this->assertAchievementNotUnlocked($user2, 'objects', null, 42);

        // Verify counts are isolated
        $counts1 = RedisMetricsCollector::getUserCounts($user1->id);
        $counts2 = RedisMetricsCollector::getUserCounts($user2->id);

        $this->assertEquals(42, array_sum($counts1['objects']));
        $this->assertEquals(1, array_sum($counts2['objects']));
    }

    /**
     * @test
     * @group achievements
     */
    public function progressive_unlocking_works_correctly(): void
    {
        $user = User::factory()->create();
        $milestones = [1, 42, 69];
        $unlockedMilestones = [];

        // Upload items one by one
        for ($i = 1; $i <= 70; $i++) {
            $this->createAndProcessPhoto($user, [
                'food' => ['wrapper' => ['quantity' => 1]]
            ]);

            $unlocked = $this->engine->evaluate($user->id);

            foreach ($unlocked as $achievement) {
                if ($achievement->type === 'objects' && !$achievement->tag_id) {
                    $unlockedMilestones[] = $achievement->threshold;
                }
            }
        }

        // Should have unlocked milestones in order
        $unlockedMilestones = array_unique($unlockedMilestones);
        sort($unlockedMilestones);

        foreach ($milestones as $milestone) {
            if (in_array($milestone, config('achievements.milestones', []))) {
                $this->assertContains($milestone, $unlockedMilestones);
            }
        }
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_partial_tag_data(): void
    {
        $user = User::factory()->create();

        // Photo with materials but no brands
        $this->createAndProcessPhoto($user, [
            'food' => [
                'wrapper' => [
                    'quantity' => 10,
                    'materials' => ['plastic' => 10],
                    // No brands
                ]
            ]
        ]);

        $unlocked1 = $this->engine->evaluate($user->id);

        // Should have material achievements but not brand achievements
        $materialAchievements = $unlocked1->where('type', 'materials');
        $brandAchievements = $unlocked1->where('type', 'brands');

        $this->assertNotEmpty($materialAchievements);
        $this->assertEmpty($brandAchievements);

        // Photo with brands but no materials
        $this->createAndProcessPhoto($user, [
            'softdrinks' => [
                'cup' => [
                    'quantity' => 5,
                    'brands' => ['coca_cola' => 5],
                    // No materials
                ]
            ]
        ]);

        $unlocked2 = $this->engine->evaluate($user->id);

        // Now should have brand achievements
        $this->assertAchievementUnlocked($user, 'brands', null, 1);
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_missing_user_gracefully(): void
    {
        // Evaluate for non-existent user
        $result = $this->engine->evaluate(999999);

        $this->assertEmpty($result);
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @test
     * @group achievements
     */
    public function achievement_definitions_are_cached(): void
    {
        // First call should cache
        $definitions1 = $this->repository->getAchievementDefinitions();

        // Clear query log and make second call
        DB::flushQueryLog();
        DB::enableQueryLog();

        $definitions2 = $this->repository->getAchievementDefinitions();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should not hit database on second call
        $this->assertEmpty($queries);
        $this->assertEquals($definitions1->count(), $definitions2->count());
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_timezone_edge_cases(): void
    {
        $user = User::factory()->create();

        // Upload at 23:59:30 UTC
        $this->createAndProcessPhoto($user,
            ['food' => ['wrapper' => ['quantity' => 1]]],
            '2025-01-20 23:59:30'
        );

        // Upload at 00:00:30 UTC next day
        $this->createAndProcessPhoto($user,
            ['food' => ['wrapper' => ['quantity' => 1]]],
            '2025-01-21 00:00:30'
        );

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(2, $counts['streak'], 'Uploads spanning midnight should count as 2-day streak');
    }

    /**
     * @test
     * @group achievements
     */
    public function unlocked_achievements_match_database(): void
    {
        $user = User::factory()->create();

        // Create a photo that will unlock multiple achievements
        $this->createAndProcessPhoto($user, [
            'food' => [
                'wrapper' => [
                    'quantity' => 42,
                    'materials' => ['plastic' => 42],
                    'brands' => ['coca_cola' => 42],
                ]
            ]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Every achievement in $unlocked should be in the database
        foreach ($unlocked as $achievement) {
            $this->assertDatabaseHas('user_achievements', [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ]);
        }

        // Total count should match
        $dbCount = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->count();

        $this->assertEquals($unlocked->count(), $dbCount,
            'Unlocked collection count should match database count');
    }

    /**
     * @test
     * @group achievements
     */
    public function handles_very_large_quantities_safely(): void
    {
        $user = User::factory()->create();

        // Test with maximum safe integer for Redis (2^53 - 1 for JavaScript compatibility)
        $maxSafeInt = 9007199254740991;

        $this->createAndProcessPhoto($user, [
            'food' => ['wrapper' => ['quantity' => $maxSafeInt]]
        ]);

        $counts = RedisMetricsCollector::getUserCounts($user->id);

        // Should store the value correctly
        $this->assertEquals($maxSafeInt, $counts['objects'][(string)$this->tagIds['objects']['wrapper']]);

        // Should unlock all available achievements
        $unlocked = $this->engine->evaluate($user->id);
        $this->assertNotEmpty($unlocked);

        // Verify highest threshold achievements are unlocked
        $highestObjectAchievement = Achievement::where('type', 'objects')
            ->whereNull('tag_id')
            ->orderBy('threshold', 'desc')
            ->first();

        if ($highestObjectAchievement) {
            $this->assertAchievementUnlocked($user, 'objects', null, $highestObjectAchievement->threshold);
        }
    }
}
