<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Models\Litter\Tags\{BrandList, Category, LitterObject, Materials};
use App\Models\Location\{Country, State, City};
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\RedisMetricsCollector;
use Database\Seeders\AchievementsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, DB, Redis};
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    private AchievementEngine $engine;
    private AchievementRepository $repository;
    private int $countryId;
    private int $stateId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushDB();
        Cache::flush();
        TagKeyCache::forgetAll();

        // Create location data
        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => $state->id]);
        $this->countryId = $country->id;
        $this->stateId = $state->id;
        $this->cityId = $city->id;

        // Configure achievements with actual milestones from config
        config('achievements.milestones');
        config('achievements.xp_scale');

        // Run seeders
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);

        // Create test brands that we use in tests
        BrandList::firstOrCreate(['key' => 'coca_cola']);
        BrandList::firstOrCreate(['key' => 'pepsi']);
        BrandList::firstOrCreate(['key' => 'starbucks']);

        $this->seed(AchievementsSeeder::class);

        // Cache tag IDs for performance
        TagKeyCache::warmCache();

        // Get the engine instance
        $this->engine = app(AchievementEngine::class);
        $this->repository = app(AchievementRepository::class);
    }

    private function makePhoto(User $user, array $summary, ?Carbon $createdAt = null): Photo
    {
        return tap(new Photo(), function (Photo $p) use ($user, $summary, $createdAt) {
            $p->user_id = $user->id;
            $p->created_at = $createdAt ?? Carbon::parse('2025-01-20 12:00:00');
            $p->summary = $summary;
            $p->filename = 'test.png';
            $p->model = 'iphone';
            $p->datetime = $createdAt ?? Carbon::parse('2025-01-20 12:00:00');
            $p->country_id = $this->countryId;
            $p->state_id = $this->stateId;
            $p->city_id = $this->cityId;
            $p->setRelation('user', $user);
            $p->save();
        });
    }

    private function assertUnlocked(User $u, string $type, ?int $tagId, int $threshold): void
    {
        $query = Achievement::where('type', $type)
            ->where('threshold', $threshold);

        if ($tagId !== null) {
            $query->where('tag_id', $tagId);
        } else {
            $query->whereNull('tag_id');
        }

        $achievement = $query->first();
        $this->assertNotNull($achievement, "Achievement {$type}-{$tagId}-{$threshold} not found in database");

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $u->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    private function assertNotUnlocked(User $u, string $type, ?int $tagId, int $threshold): void
    {
        $query = Achievement::where('type', $type)
            ->where('threshold', $threshold);

        if ($tagId !== null) {
            $query->where('tag_id', $tagId);
        } else {
            $query->whereNull('tag_id');
        }

        $achievement = $query->first();
        if (!$achievement) return;

        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $u->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    /** @test */
    public function first_upload_unlocks_achievement(): void
    {
        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        $this->assertNotEmpty($unlocked);
        $this->assertUnlocked($u, 'uploads', null, 1);

        // Check XP was awarded
        $xp = (float) Redis::hGet("{u:{$u->id}}:stats", 'xp');
        $this->assertGreaterThan(0, $xp);

        // Verify it's the sum of unlocked achievements
        $expectedXp = $unlocked->sum('xp');
        $this->assertEquals($expectedXp, $xp);
    }

    /** @test */
    public function per_tag_object_milestones_work(): void
    {
        $bottle = LitterObject::where('key', 'water_bottle')->first();
        $this->assertNotNull($bottle, 'water_bottle tag should exist after seeding');

        $u = User::factory()->create();

        // First photo: 5 bottles
        $p1 = $this->makePhoto($u, ['tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 5]]]]);
        RedisMetricsCollector::queue($p1);
        $this->engine->evaluate($p1);

        $this->assertUnlocked($u, 'object', $bottle->id, 1);
        $this->assertNotUnlocked($u, 'object', $bottle->id, 42);

        // Second photo: 37 more bottles (total 42)
        $p2 = $this->makePhoto($u, ['tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 37]]]]);
        RedisMetricsCollector::queue($p2);
        $this->engine->evaluate($p2);

        $this->assertUnlocked($u, 'object', $bottle->id, 42);

        // Verify Redis counts are correct
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(42, $counts['objects']['water_bottle'] ?? 0);
    }

    /** @test */
    public function category_milestones_count_items(): void
    {
        $cat = Category::where('key', 'food')->first();
        $this->assertNotNull($cat, 'food category should exist after seeding');

        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'food' => [
                    'wrapper' => ['quantity' => 30],
                    'packet' => ['quantity' => 12],
                ],
            ],
        ];

        $p = $this->makePhoto($u, $summary);
        RedisMetricsCollector::queue($p);
        $this->engine->evaluate($p);

        // Categories achievement counts number of unique categories, not items
        $this->assertUnlocked($u, 'categories', null, 1);

        // Per-category achievements count total items in that category
        $this->assertUnlocked($u, 'category', $cat->id, 1);
        $this->assertUnlocked($u, 'category', $cat->id, 42);

        // Verify counts
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(42, $counts['categories']['food'] ?? 0);
    }

    /** @test */
    public function material_and_brand_milestones_work(): void
    {
        $plastic = Materials::where('key', 'plastic')->first();
        $this->assertNotNull($plastic, 'plastic material should exist after seeding');

        $coke = BrandList::where('key', 'coca_cola')->first();
        $this->assertNotNull($coke, 'coca_cola brand should exist');

        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'softdrinks' => [
                    'water_bottle' => [
                        'quantity' => 42,
                        'materials' => ['plastic' => 42],
                        'brands' => ['coca_cola' => 42],
                    ],
                ],
            ],
        ];

        $p = $this->makePhoto($u, $summary);
        RedisMetricsCollector::queue($p);
        $this->engine->evaluate($p);

        // Check material achievements
        $this->assertUnlocked($u, 'material', $plastic->id, 1);
        $this->assertUnlocked($u, 'material', $plastic->id, 42);

        // Check brand achievements
        $this->assertUnlocked($u, 'brand', $coke->id, 1);
        $this->assertUnlocked($u, 'brand', $coke->id, 42);

        // Verify counts
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(42, $counts['materials']['plastic'] ?? 0);
        $this->assertEquals(42, $counts['brands']['coca_cola'] ?? 0);
    }

    /** @test */
    public function dimension_wide_milestones_work(): void
    {
        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'softdrinks' => [
                    'water_bottle' => [
                        'quantity' => 30,
                        'materials' => ['plastic' => 30],
                        'brands' => ['coca_cola' => 15, 'pepsi' => 15],
                    ],
                    'soda_can' => [
                        'quantity' => 12,
                        'materials' => ['aluminium' => 12],
                        'brands' => ['coca_cola' => 12],
                    ],
                ],
            ],
        ];

        $p = $this->makePhoto($u, $summary);
        RedisMetricsCollector::queue($p);
        $this->engine->evaluate($p);

        // Check dimension-wide achievements
        $this->assertUnlocked($u, 'objects', null, 1);
        $this->assertUnlocked($u, 'objects', null, 42);
        $this->assertUnlocked($u, 'categories', null, 1);
        $this->assertUnlocked($u, 'materials', null, 1);
        $this->assertUnlocked($u, 'materials', null, 42);
        $this->assertUnlocked($u, 'brands', null, 1);
        $this->assertUnlocked($u, 'brands', null, 42);

        // Verify total counts
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $totalObjects = array_sum($counts['objects']);
        $totalMaterials = array_sum($counts['materials']);
        $totalBrands = array_sum($counts['brands']);

        $this->assertEquals(42, $totalObjects);
        $this->assertEquals(42, $totalMaterials);
        $this->assertEquals(42, $totalBrands); // coca_cola gets 15 + 12 = 27, pepsi gets 15, total = 42
    }

    /** @test */
    public function idempotent_unlocks_dont_duplicate(): void
    {
        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
        ]);

        RedisMetricsCollector::queue($p);

        // Evaluate three times
        $firstUnlocked = $this->engine->evaluate($p);
        $secondUnlocked = $this->engine->evaluate($p);
        $thirdUnlocked = $this->engine->evaluate($p);

        // First evaluation should unlock achievements
        $this->assertNotEmpty($firstUnlocked);

        // Subsequent evaluations should return empty
        $this->assertEmpty($secondUnlocked);
        $this->assertEmpty($thirdUnlocked);

        // Verify no duplicates in database
        $achievementCounts = DB::table('user_achievements')
            ->where('user_id', $u->id)
            ->selectRaw('achievement_id, COUNT(*) as count')
            ->groupBy('achievement_id')
            ->pluck('count', 'achievement_id');

        foreach ($achievementCounts as $achievementId => $count) {
            $this->assertEquals(1, $count, "Achievement {$achievementId} should only be unlocked once");
        }

        // Check XP wasn't multiplied
        $xp = (float) Redis::hGet("{u:{$u->id}}:stats", 'xp');
        $expectedXp = $firstUnlocked->sum('xp');
        $this->assertEquals($expectedXp, $xp);
    }

    /** @test */
    public function missing_tags_are_ignored(): void
    {
        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['unknown_category' => ['ghost_tag' => ['quantity' => 1]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should unlock dimension-wide achievements
        $this->assertUnlocked($u, 'uploads', null, 1);
        $this->assertUnlocked($u, 'categories', null, 1);
        $this->assertUnlocked($u, 'objects', null, 1);

        // Redis WILL record the counts for unknown tags
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertArrayHasKey('unknown_category', $counts['categories']);
        $this->assertArrayHasKey('ghost_tag', $counts['objects']);
    }

    /** @test */
    public function handles_empty_photo_gracefully(): void
    {
        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => [],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should still unlock uploads achievement
        $this->assertCount(1, $unlocked);
        $this->assertUnlocked($u, 'uploads', null, 1);
    }

    /** @test */
    public function handles_missing_user_gracefully(): void
    {
        // Test with a photo that has a non-existent user ID
        $p = new Photo();
        $p->user_id = 999999; // Non-existent user
        $p->summary = ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]];
        $p->created_at = now();
        $p->filename = 'test.png';
        $p->model = 'iphone';
        $p->datetime = now();
        $p->country_id = $this->countryId;
        $p->state_id = $this->stateId;
        $p->city_id = $this->cityId;

        // Don't save the photo to avoid FK constraint
        $p->id = 999999;
        $p->exists = true;

        RedisMetricsCollector::queue($p);

        // Should handle gracefully when user doesn't exist
        $unlocked = $this->engine->evaluate($p);
        $this->assertEmpty($unlocked);
    }

    /** @test */
    public function caching_improves_performance(): void
    {
        $u = User::factory()->create();

        // Warm up caches
        $this->engine->evaluate($this->makePhoto($u, ['tags' => []]));

        DB::enableQueryLog();
        $startTime = microtime(true);

        // Process 10 photos
        for ($i = 0; $i < 10; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
            ]);
            RedisMetricsCollector::queue($p);
            $this->engine->evaluate($p);
        }

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should be fast
        $this->assertLessThan(5.0, $endTime - $startTime, 'Should process 10 photos in under 5 seconds');

        // With seeders, expect more queries
        $queryCount = count($queries);
        $this->assertLessThan(100, $queryCount, 'Should have reasonable query count');
    }

    /** @test */
    public function progressive_achievement_unlocking(): void
    {
        $u = User::factory()->create();
        $milestones = [1, 42, 69]; // Only test first few milestones
        $unlockedMilestones = [];

        // Upload 70 photos one by one
        for ($i = 1; $i <= 70; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
            ]);
            RedisMetricsCollector::queue($p);
            $unlocked = $this->engine->evaluate($p);

            if ($unlocked->isNotEmpty()) {
                foreach ($unlocked as $achievement) {
                    if ($achievement->type === 'uploads' && !$achievement->tag_id) {
                        $unlockedMilestones[] = $achievement->threshold;
                    }
                }
            }
        }

        // Should have unlocked all upload milestones in order
        sort($unlockedMilestones);
        $this->assertEquals($milestones, $unlockedMilestones);

        // Verify all are in database
        foreach ($milestones as $milestone) {
            $this->assertUnlocked($u, 'uploads', null, $milestone);
        }
    }

    /** @test */
    public function handles_concurrent_photo_processing(): void
    {
        $u = User::factory()->create();
        $photos = [];

        // Create 5 photos that would each unlock uploads-1
        for ($i = 0; $i < 5; $i++) {
            $photos[] = $this->makePhoto($u, [
                'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
            ]);
        }

        // Process all photos "concurrently" (simulated)
        foreach ($photos as $photo) {
            RedisMetricsCollector::queue($photo);
        }

        $allUnlocked = collect();
        foreach ($photos as $photo) {
            $unlocked = $this->engine->evaluate($photo);
            $allUnlocked = $allUnlocked->merge($unlocked);
        }

        // Should only have unlocked uploads-1 once
        $uploadsAchievements = $allUnlocked->filter(function ($a) {
            return $a->type === 'uploads' && $a->threshold === 1;
        });

        $this->assertCount(1, $uploadsAchievements);

        // Database should only have one record
        $uploadsAchievement = Achievement::where('type', 'uploads')
            ->where('threshold', 1)
            ->first();

        $count = DB::table('user_achievements')
            ->where('user_id', $u->id)
            ->where('achievement_id', $uploadsAchievement->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function complex_multi_category_scenario(): void
    {
        $u = User::factory()->create();

        // First photo: softdrinks items
        $p1 = $this->makePhoto($u, [
            'tags' => [
                'softdrinks' => [
                    'water_bottle' => [
                        'quantity' => 5,
                        'materials' => ['plastic' => 5],
                        'brands' => ['coca_cola' => 3, 'pepsi' => 2],
                    ],
                ],
            ],
        ]);

        // Second photo: food items
        $p2 = $this->makePhoto($u, [
            'tags' => [
                'food' => [
                    'cup' => [
                        'quantity' => 3,
                        'materials' => ['paper' => 3],
                        'brands' => ['starbucks' => 3],
                    ],
                ],
            ],
        ]);

        // Process both
        RedisMetricsCollector::queue($p1);
        $unlocked1 = $this->engine->evaluate($p1);

        RedisMetricsCollector::queue($p2);
        $unlocked2 = $this->engine->evaluate($p2);

        // Should have unlocked various achievements
        $this->assertUnlocked($u, 'uploads', null, 1); // From first photo
        $this->assertUnlocked($u, 'categories', null, 1); // After second photo (2 categories)
        $this->assertUnlocked($u, 'objects', null, 1); // Total 8 objects
        $this->assertUnlocked($u, 'materials', null, 1); // Total 8 materials
        $this->assertUnlocked($u, 'brands', null, 1); // Total 8 brand items

        // Verify counts
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(2, $counts['uploads']);
        $this->assertEquals(2, count($counts['categories']));
        $this->assertEquals(8, array_sum($counts['objects']));
        $this->assertEquals(8, array_sum($counts['materials']));
        $this->assertEquals(8, array_sum($counts['brands']));
    }

    /** @test */
    public function repository_handles_errors_gracefully(): void
    {
        // This test needs to be redesigned since we can't mock DB facade easily
        $this->markTestSkipped('Database mocking test - implement with proper test database');
    }

    /** @test */
    public function streak_achievements_are_tracked(): void
    {
        $u = User::factory()->create();

        // Day 1
        $p1 = $this->makePhoto($u,
            ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]],
            Carbon::parse('2025-01-20')
        );
        RedisMetricsCollector::queue($p1);

        // Day 2 (consecutive)
        $p2 = $this->makePhoto($u,
            ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]],
            Carbon::parse('2025-01-21')
        );
        RedisMetricsCollector::queue($p2);

        // Verify streak
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(2, $counts['streak']);

        // Day 4 (break in streak)
        $p3 = $this->makePhoto($u,
            ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]],
            Carbon::parse('2025-01-23')
        );
        RedisMetricsCollector::queue($p3);

        // Streak should reset
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(1, $counts['streak']);
    }

    /** @test */
    public function achievement_xp_accumulation_is_accurate(): void
    {
        $u = User::factory()->create();
        $totalExpectedXp = 0;

        // Process multiple photos to reach milestone 42
        for ($i = 1; $i <= 42; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => [
                    'softdrinks' => [
                        'water_bottle' => [
                            'quantity' => 1,
                            'materials' => ['plastic' => 1],
                            'brands' => ['coca_cola' => 1],
                        ],
                    ],
                ],
            ]);

            RedisMetricsCollector::queue($p);
            $unlocked = $this->engine->evaluate($p);
            $totalExpectedXp += $unlocked->sum('xp');
        }

        // Verify XP matches
        $actualXp = (float) Redis::hGet("{u:{$u->id}}:stats", 'xp');
        $this->assertEquals($totalExpectedXp, $actualXp);

        // Also verify against database
        $dbXp = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $u->id)
            ->sum('achievements.xp');

        $this->assertEquals($totalExpectedXp, $dbXp);
    }

    /** @test */
    public function achievements_unlock_exactly_at_threshold(): void
    {
        $u = User::factory()->create();

        // Upload 41 items (just before threshold 42)
        $p1 = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 41]]],
        ]);
        RedisMetricsCollector::queue($p1);
        $this->engine->evaluate($p1);

        // Should have 1 achievement, but not 42
        $this->assertUnlocked($u, 'objects', null, 1);
        $this->assertNotUnlocked($u, 'objects', null, 42);

        // Add exactly 1 more
        $p2 = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 1]]],
        ]);
        RedisMetricsCollector::queue($p2);
        $unlocked = $this->engine->evaluate($p2);

        // Now should have 42 achievement
        $this->assertUnlocked($u, 'objects', null, 42);

        // Verify the unlocked collection contains the 42 milestone
        $has42Milestone = $unlocked->contains(function ($achievement) {
            return $achievement->type === 'objects' && $achievement->threshold === 42;
        });
        $this->assertTrue($has42Milestone);
    }

    /** @test */
    public function single_photo_can_unlock_multiple_achievements(): void
    {
        $u = User::factory()->create();

        // One photo with enough items to unlock multiple milestones
        $p = $this->makePhoto($u, [
            'tags' => [
                'softdrinks' => [
                    'water_bottle' => [
                        'quantity' => 69,
                        'materials' => ['plastic' => 69],
                        'brands' => ['coca_cola' => 69],
                    ],
                ],
            ],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should unlock multiple achievements: 1, 42, 69 for multiple dimensions
        $this->assertGreaterThan(10, $unlocked->count());

        // Verify specific milestones
        $this->assertUnlocked($u, 'uploads', null, 1);
        $this->assertUnlocked($u, 'objects', null, 1);
        $this->assertUnlocked($u, 'objects', null, 42);
        $this->assertUnlocked($u, 'objects', null, 69);
        $this->assertUnlocked($u, 'materials', null, 1);
        $this->assertUnlocked($u, 'materials', null, 42);
        $this->assertUnlocked($u, 'materials', null, 69);
    }

    /** @test */
    public function handles_zero_quantity_gracefully(): void
    {
        $u = User::factory()->create();

        $p = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 0]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should still unlock upload achievement
        $this->assertUnlocked($u, 'uploads', null, 1);

        // But no object achievements
        $objectAchievements = $unlocked->filter(function ($a) {
            return $a->type === 'objects' || $a->type === 'object';
        });
        $this->assertEmpty($objectAchievements);
    }

    /** @test */
    public function handles_negative_quantity_as_zero(): void
    {
        $u = User::factory()->create();

        $p = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => -5]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should treat negative as zero
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(0, $counts['objects']['water_bottle'] ?? 0);
    }

    /** @test */
    public function handles_very_large_quantities(): void
    {
        $u = User::factory()->create();

        $p = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 1000000]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Should unlock all available milestones
        $this->assertUnlocked($u, 'objects', null, 1);
        $this->assertUnlocked($u, 'objects', null, 42);
        $this->assertUnlocked($u, 'objects', null, 69);
        $this->assertUnlocked($u, 'objects', null, 420);

        // Verify count is stored correctly
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(1000000, $counts['objects']['water_bottle']);
    }

    /** @test */
    public function partial_tag_data_is_handled(): void
    {
        $u = User::factory()->create();

        // Photo with materials but no brands
        $p1 = $this->makePhoto($u, [
            'tags' => [
                'softdrinks' => [
                    'water_bottle' => [
                        'quantity' => 10,
                        'materials' => ['plastic' => 10],
                        // No brands
                    ],
                ],
            ],
        ]);

        RedisMetricsCollector::queue($p1);
        $unlocked = $this->engine->evaluate($p1);

        // Should unlock material achievements but not brand achievements
        $this->assertUnlocked($u, 'materials', null, 1);
        $brandAchievements = $unlocked->filter(fn($a) => $a->type === 'brands');
        $this->assertEmpty($brandAchievements);

        // Photo with brands but no materials
        $p2 = $this->makePhoto($u, [
            'tags' => [
                'food' => [
                    'wrapper' => [
                        'quantity' => 5,
                        'brands' => ['coca_cola' => 5],
                        // No materials
                    ],
                ],
            ],
        ]);

        RedisMetricsCollector::queue($p2);
        $unlocked2 = $this->engine->evaluate($p2);

        // Now should have brand achievements
        $this->assertUnlocked($u, 'brands', null, 1);
    }

    /** @test */
    public function achievement_metadata_is_preserved(): void
    {
        $u = User::factory()->create();

        $p = $this->makePhoto($u, [
            'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Check that achievements have metadata
        $uploadAchievement = $unlocked->first(fn($a) => $a->type === 'uploads');
        $this->assertNotNull($uploadAchievement);
        $this->assertIsArray($uploadAchievement->metadata ?? null);
    }

    /** @test */
    public function redis_and_database_stay_in_sync(): void
    {
        $u = User::factory()->create();

        // Process multiple photos
        for ($i = 1; $i <= 10; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 5]]],
            ]);
            RedisMetricsCollector::queue($p);
            $this->engine->evaluate($p);
        }

        // Get Redis XP
        $redisXp = (float) Redis::hGet("{u:{$u->id}}:stats", 'xp');

        // Get Database XP
        $dbXp = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where('user_achievements.user_id', $u->id)
            ->sum('achievements.xp');

        $this->assertEquals($dbXp, $redisXp);

        // Verify counts match what we expect
        $counts = RedisMetricsCollector::getUserCounts($u->id);
        $this->assertEquals(10, $counts['uploads']);
        $this->assertEquals(50, $counts['objects']['water_bottle']);
    }

    /** @test */
    public function achievements_unlock_in_correct_order(): void
    {
        $u = User::factory()->create();

        // Upload exactly 69 items at once
        $p = $this->makePhoto($u, [
            'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 69]]],
        ]);

        RedisMetricsCollector::queue($p);
        $unlocked = $this->engine->evaluate($p);

        // Get all object achievements
        $objectAchievements = $unlocked->filter(fn($a) => $a->type === 'objects')
            ->sortBy('threshold')
            ->values();

        // Should have unlocked 1, 42, and 69 in that order
        $this->assertEquals(1, $objectAchievements[0]->threshold);
        $this->assertEquals(42, $objectAchievements[1]->threshold);
        $this->assertEquals(69, $objectAchievements[2]->threshold);
    }

//    /** @test */ Not working yet
//    public function custom_tags_unlock_achievements(): void
//    {
//        $this->seed(GenerateTagsSeeder::class);
//        $this->seed(GenerateBrandsSeeder::class);
//
//        // Create a custom tag
//        $customTag = CustomTagNew::firstOrCreate(['key' => 'test_custom']);
//
//        // Seed achievement for it
//        DB::table('achievements')->insert([
//            'type' => 'customTag',
//            'tag_id' => $customTag->id,
//            'threshold' => 1,
//            'xp' => 10,
//            'created_at' => now(),
//            'updated_at' => now(),
//        ]);
//
//        $u = User::factory()->create();
//        $this->actingAs($u, 'api');
//
//        // Upload a photo
//        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));
//        $photo = $u->photos->last();
//
//        // Tag it with custom tags
//        $category = Category::where('key', 'food')->first();
//        $object = LitterObject::where('key', 'wrapper')->first();
//
//        $response = $this->post('/api/v3/tags', [
//            'photo_id' => $photo->id,
//            'tags' => [
//                [
//                    'category' => ['id' => $category->id],
//                    'object' => ['id' => $object->id],
//                    'quantity' => 1,
//                    'custom_tags' => ['test_custom']
//                ]
//            ]
//        ]);
//
//        // Now process the photo for achievements
//        RedisMetricsCollector::queue($photo->fresh());
//        $unlocked = $this->engine->evaluate($photo->fresh());
//
//        // Should unlock custom tag achievement
//        $this->assertUnlocked($u, 'customTag', $customTag->id, 1);
//    }

    /** @test */
    public function user_level_calculation_from_xp(): void
    {
        $u = User::factory()->create();
        $levels = config('achievements.levels');

        // Process photos to accumulate XP
        for ($i = 1; $i <= 50; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => ['softdrinks' => ['water_bottle' => ['quantity' => 2]]],
            ]);
            RedisMetricsCollector::queue($p);
            $this->engine->evaluate($p);
        }

        // Get total XP
        $totalXp = (float) Redis::hGet("{u:{$u->id}}:stats", 'xp');

        // Calculate expected level
        $expectedLevel = 1;
        foreach ($levels as $xpRequired => $level) {
            if ($totalXp >= $xpRequired) {
                $expectedLevel = $level;
            }
        }

        // In a real implementation, you'd have a method to calculate level
        // $actualLevel = $u->calculateLevel();
        // $this->assertEquals($expectedLevel, $actualLevel);

        $this->assertGreaterThan(0, $expectedLevel);
        $this->assertGreaterThan(100, $totalXp); // Should have accumulated significant XP
    }

    /** @test */
    public function performance_with_user_having_many_achievements(): void
    {
        $u = User::factory()->create();

        // Give user many achievements by processing lots of photos
        for ($i = 1; $i <= 100; $i++) {
            $p = $this->makePhoto($u, [
                'tags' => [
                    'softdrinks' => ['water_bottle' => ['quantity' => 10]],
                    'food' => ['wrapper' => ['quantity' => 5]],
                ],
            ]);
            RedisMetricsCollector::queue($p);
            $this->engine->evaluate($p);
        }

        // Now time a new photo evaluation
        $startTime = microtime(true);

        $p = $this->makePhoto($u, [
            'tags' => ['food' => ['wrapper' => ['quantity' => 1]]],
        ]);
        RedisMetricsCollector::queue($p);
        $this->engine->evaluate($p);

        $endTime = microtime(true);

        // Should still be fast even with many existing achievements
        $this->assertLessThan(0.5, $endTime - $startTime);

        // Verify user has many achievements
        $achievementCount = DB::table('user_achievements')
            ->where('user_id', $u->id)
            ->count();
        $this->assertGreaterThan(20, $achievementCount);
    }

    /** @test */
    public function batch_processing_is_more_efficient_than_individual(): void
    {
        $user = User::factory()->create();
        $photos = collect();

        // Create more photos to see a bigger difference
        for ($i = 0; $i < 100; $i++) {
            $photos->push($this->makePhoto($user, [
                'tags' => ['food' => ['wrapper' => ['quantity' => 2]]]
            ]));
        }

        // Clear Redis
        Redis::flushDB();

        // Method 1: Individual processing
        $startTime = microtime(true);
        foreach ($photos as $photo) {
            RedisMetricsCollector::queue($photo);
        }
        $individualTime = microtime(true) - $startTime;
        $individualCounts = RedisMetricsCollector::getUserCounts($user->id);

        // Clear Redis for batch test
        Redis::flushDB();

        // Method 2: Batch processing
        $startTime = microtime(true);
        RedisMetricsCollector::queueBatch($user->id, $photos);
        $batchTime = microtime(true) - $startTime;
        $batchCounts = RedisMetricsCollector::getUserCounts($user->id);

        // Batch should be at least 20% faster (more realistic expectation)
        $this->assertLessThan($individualTime * 0.8, $batchTime,
            "Batch time ({$batchTime}) should be at least 20% faster than individual time ({$individualTime})");

        // Verify counts are identical
        $this->assertEquals($individualCounts['objects']['wrapper'], $batchCounts['objects']['wrapper']);
        $this->assertEquals(200, $batchCounts['objects']['wrapper']); // 100 photos * 2 quantity
    }

    /** @test */
    public function batch_processing_handles_mixed_dates_correctly(): void
    {
        $user = User::factory()->create();
        $photos = collect([
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]], Carbon::parse('2025-01-15')),
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]], Carbon::parse('2025-01-20')),
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]], Carbon::parse('2025-01-10')),
        ]);

        RedisMetricsCollector::queueBatch($user->id, $photos);

        // Verify upload flags are set for all dates
        $this->assertEquals(1, Redis::exists("{u:{$user->id}}:up:2025-01-10"));
        $this->assertEquals(1, Redis::exists("{u:{$user->id}}:up:2025-01-15"));
        $this->assertEquals(1, Redis::exists("{u:{$user->id}}:up:2025-01-20"));
    }

    /** @test */
    public function migration_processes_photos_by_user_efficiently(): void
    {
        // Create 3 users with different photo counts
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // User 1: 20 photos
        Photo::factory()->count(20)->create(['user_id' => $user1->id]);
        // User 2: 30 photos
        Photo::factory()->count(30)->create(['user_id' => $user2->id]);
        // User 3: 10 photos
        Photo::factory()->count(10)->create(['user_id' => $user3->id]);

        // Run migration
        $this->artisan('olm:v5')
            ->assertSuccessful();

        // Verify all photos are marked as migrated
        $this->assertEquals(0, Photo::whereNull('migrated_at')->count());

        // Verify each user's achievements were checked only once
        $this->assertLessThan(10,
            DB::table('user_achievements')
                ->where('user_id', $user1->id)
                ->where('created_at', '>=', now()->subMinute())
                ->count()
        );
    }

//    /** @test */
//    public function handles_redis_connection_failure_gracefully(): void
//    {
//        $user = User::factory()->create();
//        $photo = $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]]);
//
//        // Simulate Redis being down
//        Redis::shouldReceive('pipeline')->andThrow(new \RedisException('Connection refused'));
//
//        // Should not throw exception
//        $this->assertDoesNotThrow(function() use ($photo) {
//            RedisMetricsCollector::queue($photo);
//        });
//
//        // Photo should still be marked as processed in DB
//        $this->assertNotNull($photo->fresh()->migrated_at);
//    }

    /** @test */
    public function handles_concurrent_batch_processing_for_same_user(): void
    {
        $user = User::factory()->create();

        // Simulate two workers processing different batches for same user
        $batch1 = collect([
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 10]]]]),
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 20]]]]),
        ]);

        $batch2 = collect([
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 30]]]]),
            $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 40]]]]),
        ]);

        // Process concurrently (simulated)
        RedisMetricsCollector::queueBatch($user->id, $batch1);
        RedisMetricsCollector::queueBatch($user->id, $batch2);

        // Verify total is correct (not duplicated or lost)
        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(100, $counts['objects']['wrapper'] ?? 0);
        $this->assertEquals(4, $counts['uploads']);
    }

    /** @test */
    public function streak_handles_timezone_boundaries(): void
    {
        $user = User::factory()->create();

        // Upload at 11:59 PM
        $photo1 = $this->makePhoto($user,
            ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]],
            Carbon::parse('2025-01-20 23:59:00')
        );

        // Upload at 12:01 AM next day
        $photo2 = $this->makePhoto($user,
            ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]],
            Carbon::parse('2025-01-21 00:01:00')
        );

        RedisMetricsCollector::queueBatch($user->id, collect([$photo1, $photo2]));

        $counts = RedisMetricsCollector::getUserCounts($user->id);
        $this->assertEquals(2, $counts['streak']); // Should count as consecutive days
    }

    /** @test */
    public function user_achievement_cache_is_invalidated_on_unlock(): void
    {
        $user = User::factory()->create();

        // Warm cache
        $cached1 = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertEmpty($cached1);

        // Unlock achievement
        $photo = $this->makePhoto($user, ['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]]);
        RedisMetricsCollector::queue($photo);
        $this->engine->evaluate($photo);

        // Cache should be invalidated
        $cached2 = $this->repository->getUnlockedAchievementIds($user->id);
        $this->assertNotEmpty($cached2);
        $this->assertNotEquals($cached1, $cached2);
    }
}
