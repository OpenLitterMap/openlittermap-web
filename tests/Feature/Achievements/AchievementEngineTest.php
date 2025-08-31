<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    private AchievementEngine $engine;
    private AchievementRepository $repository;
    private array $tagIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed once per test
        $this->seedOnce();

        // Clear user-specific data only
        $this->clearUserData();

        // Initialize services
        $this->engine = app(AchievementEngine::class);
        $this->repository = app(AchievementRepository::class);

        // Cache tag IDs once
        $this->tagIds = [
            'wrapper' => TagKeyCache::getOrCreateId('object', 'wrapper'),
            'food' => TagKeyCache::getOrCreateId('category', 'food'),
            'plastic' => TagKeyCache::getOrCreateId('material', 'plastic'),
            'coca_cola' => TagKeyCache::getOrCreateId('brand', 'coca_cola'),
        ];
    }

    private function seedOnce(): void
    {
        // Clear in correct order to respect foreign keys
        DB::table('user_achievements')->delete();
        DB::table('achievements')->delete();

        // Create minimal test data
        $this->createTestAchievements();
        TagKeyCache::preloadAll();
    }

    private function clearUserData(): void
    {
        // Clear user-specific Redis data
        $userKeys = Redis::keys('user:*');
        if ($userKeys && is_array($userKeys)) {
            Redis::del($userKeys);
        }

        $userKeys = Redis::keys('{u:*');
        if ($userKeys && is_array($userKeys)) {
            Redis::del($userKeys);
        }

        Cache::flush(); // More efficient than selective forget
    }

    private function createTestAchievements(): void
    {
        $achievements = [
            // Dimension-wide achievements
            ['type' => 'uploads', 'threshold' => 1, 'tag_id' => null],
            ['type' => 'uploads', 'threshold' => 10, 'tag_id' => null],
            ['type' => 'objects', 'threshold' => 1, 'tag_id' => null],
            ['type' => 'objects', 'threshold' => 10, 'tag_id' => null],
            ['type' => 'categories', 'threshold' => 1, 'tag_id' => null],
            ['type' => 'materials', 'threshold' => 10, 'tag_id' => null],
            ['type' => 'brands', 'threshold' => 10, 'tag_id' => null],
        ];

        // Bulk insert matching actual schema
        $data = array_map(fn($a) => array_merge($a, [
            'metadata' => json_encode(['xp' => $a['threshold'] * 10]),
            'created_at' => now(),
            'updated_at' => now(),
        ]), $achievements);

        Achievement::insert($data);
    }

    private function createPhoto(User $user, array $tags, ?string $createdAt = null): Photo
    {
        $photo = Photo::factory()->for($user)->create([
            'summary' => ['tags' => $tags],
            'created_at' => $createdAt ?? now(),
            'lat' => 51.5074,  // Add required lat
            'lon' => -0.1278,  // Add required lon
        ]);

        $metrics = [
            'litter' => $this->calculateLitterCount($tags),
            'xp' => 1,
            'tags' => $this->extractTags($tags),
        ];

        RedisMetricsCollector::processPhoto($photo, $metrics, 'create');
        return $photo;
    }

    private function calculateLitterCount(array $tags): int
    {
        $count = 0;
        foreach ($tags as $objects) {
            foreach ($objects as $data) {
                $count += max(0, (int)($data['quantity'] ?? 0));
            }
        }
        return $count;
    }

    private function extractTags(array $tags): array
    {
        $result = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];

        foreach ($tags as $catKey => $objects) {
            $catId = (string)TagKeyCache::getOrCreateId('category', $catKey);
            $catTotal = 0;

            foreach ($objects as $objKey => $data) {
                $objId = (string)TagKeyCache::getOrCreateId('object', $objKey);
                $qty = max(0, (int)($data['quantity'] ?? 0));

                $result['objects'][$objId] = ($result['objects'][$objId] ?? 0) + $qty;
                $catTotal += $qty;

                // Process materials
                foreach ($data['materials'] ?? [] as $matKey => $matQty) {
                    $matId = (string)TagKeyCache::getOrCreateId('material', $matKey);
                    $result['materials'][$matId] = ($result['materials'][$matId] ?? 0) + max(0, (int)$matQty);
                }

                // Process brands
                foreach ($data['brands'] ?? [] as $brandKey => $brandQty) {
                    $brandId = (string)TagKeyCache::getOrCreateId('brand', $brandKey);
                    $result['brands'][$brandId] = ($result['brands'][$brandId] ?? 0) + max(0, (int)$brandQty);
                }
            }

            $result['categories'][$catId] = $catTotal;
        }

        return $result;
    }

    private function assertUnlocked(User $user, string $type, ?int $tagId, int $threshold): void
    {
        $achievement = Achievement::where('type', $type)
            ->where('threshold', $threshold)
            ->when($tagId !== null, fn($q) => $q->where('tag_id', $tagId))
            ->when($tagId === null, fn($q) => $q->whereNull('tag_id'))
            ->first();

        $this->assertNotNull($achievement, "Achievement {$type}-{$threshold} not found");

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /** @test */
    public function first_upload_unlocks_basic_achievements(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        $this->assertGreaterThanOrEqual(3, $unlocked->count());
        $this->assertUnlocked($user, 'uploads', null, 1);
        $this->assertUnlocked($user, 'categories', null, 1);
        $this->assertUnlocked($user, 'objects', null, 1);
    }

    /** @test */
    public function per_tag_achievements_unlock_at_thresholds(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 10]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        $this->assertNotEmpty($unlocked);

        // Check objects achievements
        $objectAchievements = $unlocked->where('type', 'objects');
        $this->assertNotEmpty($objectAchievements);

        // Should unlock both 1 and 10 thresholds
        $thresholds = $objectAchievements->pluck('threshold')->toArray();
        $this->assertContains(1, $thresholds);
        $this->assertContains(10, $thresholds);
    }

    /** @test */
    public function achievements_are_idempotent(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        $first = $this->engine->evaluate($user->id);
        $second = $this->engine->evaluate($user->id);

        $this->assertNotEmpty($first);
        $this->assertEmpty($second);

        // Check for duplicates
        $duplicates = DB::table('user_achievements')
            ->select('achievement_id', DB::raw('COUNT(*) as count'))
            ->where('user_id', $user->id)
            ->groupBy('achievement_id')
            ->having('count', '>', 1)
            ->count();

        $this->assertEquals(0, $duplicates);
    }

    /**
     * @test
     * @dataProvider edgeQuantityProvider
     */
    public function handles_edge_case_quantities($quantity, int $expectedCount): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => $quantity]]
        ]);

        $counts = RedisMetricsCollector::getUserMetrics($user->id);
        $wrapperId = (string)$this->tagIds['wrapper'];
        $actualCount = $counts['objects'][$wrapperId] ?? 0;

        $this->assertEquals($expectedCount, $actualCount);
    }

    public static function edgeQuantityProvider(): array
    {
        return [
            'negative' => [-5, 0],
            'zero' => [0, 0],
            'float' => [3.7, 3],
            'string' => ['10', 10],
            'null' => [null, 0],
        ];
    }

    /** @test */
    public function handles_missing_user_gracefully(): void
    {
        $result = $this->engine->evaluate(999999);
        $this->assertEmpty($result);
    }

    /** @test */
    public function respects_user_isolation(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->createPhoto($user1, [
            'food' => ['wrapper' => ['quantity' => 42]]
        ]);

        $this->createPhoto($user2, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        $this->engine->evaluate($user1->id);
        $this->engine->evaluate($user2->id);

        $counts1 = RedisMetricsCollector::getUserMetrics($user1->id);
        $counts2 = RedisMetricsCollector::getUserMetrics($user2->id);

        $this->assertEquals(42, array_sum($counts1['objects']));
        $this->assertEquals(1, array_sum($counts2['objects']));
    }

    /** @test */
    public function handles_concurrent_evaluations_safely(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 5]]
        ]);

        // Simulate concurrent evaluations
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->engine->evaluate($user->id);
        }

        // Only first should unlock achievements
        $this->assertNotEmpty($results[0]);
        $this->assertEmpty($results[1]);
        $this->assertEmpty($results[2]);

        // No duplicates in database
        $count = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->count();

        $this->assertEquals($results[0]->count(), $count);
    }

    /** @test */
    public function tracks_multiple_tag_dimensions(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => [
                'wrapper' => [
                    'quantity' => 10,
                    'materials' => ['plastic' => 10],
                    'brands' => ['coca_cola' => 10]
                ]
            ]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should unlock achievements across multiple dimensions
        $types = $unlocked->pluck('type')->unique()->values()->toArray();

        $this->assertContains('uploads', $types);
        $this->assertContains('objects', $types);
        $this->assertContains('categories', $types);

        // Check if materials/brands achievements exist and were unlocked
        if (Achievement::where('type', 'materials')->exists()) {
            $this->assertContains('materials', $types);
        }
        if (Achievement::where('type', 'brands')->exists()) {
            $this->assertContains('brands', $types);
        }
    }

    /** @test */
    public function handles_photo_updates_correctly(): void
    {
        $user = User::factory()->create();

        // Initial photo
        $photo = $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 5]]
        ]);

        $first = $this->engine->evaluate($user->id);
        $this->assertNotEmpty($first);

        // Simulate photo update with more items
        RedisMetricsCollector::processPhoto($photo, [
            'litter' => 5,
            'xp' => 5,
            'tags' => $this->extractTags([
                'food' => ['wrapper' => ['quantity' => 5]]
            ])
        ], 'update');

        $second = $this->engine->evaluate($user->id);

        // Should unlock 10-threshold achievements now
        $tenThreshold = $second->where('threshold', 10);
        $this->assertNotEmpty($tenThreshold);
    }

    /** @test */
    public function caches_unlocked_achievements(): void
    {
        $user = User::factory()->create();

        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        // First evaluation
        $unlocked = $this->engine->evaluate($user->id);
        $this->assertNotEmpty($unlocked);

        // Get achievements from repository (which uses cache)
        $cached = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertNotEmpty($cached);

        // Clear database but not cache
        DB::table('user_achievements')->where('user_id', $user->id)->delete();

        // Repository should still return cached results
        $fromCache = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertEquals($cached, $fromCache);
    }

    /** @test */
    public function handles_invalid_tag_types_gracefully(): void
    {
        $user = User::factory()->create();

        // Create photo with invalid/unknown category
        $photo = Photo::factory()->for($user)->create([
            'summary' => ['tags' => ['unknown_category' => ['unknown_object' => ['quantity' => 1]]]],
            'created_at' => now(),
            'lat' => 51.5074,
            'lon' => -0.1278,
        ]);

        // Process with minimal metrics to avoid errors
        RedisMetricsCollector::processPhoto($photo, [
            'litter' => 1,
            'xp' => 1,
            'tags' => [
                'categories' => [],
                'objects' => [],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]
        ], 'create');

        // Should not throw exception
        $result = $this->engine->evaluate($user->id);
        $this->assertNotNull($result);
    }

    /** @test */
    public function respects_achievement_thresholds_strictly(): void
    {
        $user = User::factory()->create();

        // Create photo with quantity just below threshold
        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 9]]
        ]);

        $unlocked = $this->engine->evaluate($user->id);

        // Should unlock 1-threshold but not 10-threshold
        $objectAchievements = $unlocked->where('type', 'objects')->whereNull('tag_id');

        $this->assertTrue($objectAchievements->contains('threshold', 1));
        $this->assertFalse($objectAchievements->contains('threshold', 10));

        // Add one more item
        $this->createPhoto($user, [
            'food' => ['wrapper' => ['quantity' => 1]]
        ]);

        $newUnlocked = $this->engine->evaluate($user->id);

        // Now should unlock 10-threshold
        $this->assertTrue($newUnlocked->contains(fn($a) =>
            $a->type === 'objects' && $a->threshold === 10 && $a->tag_id === null
        ));
    }
}
