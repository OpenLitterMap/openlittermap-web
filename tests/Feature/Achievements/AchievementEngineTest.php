<?php

declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Tests\Helpers\MockRedisTrait;
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase, MockRedisTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRedis([
            '{u:1}:stats' => ['xp'=>0,'uploads'=>0,'st'=>0],
            '{u:1}:t'     => [],    // object counts
            '{u:1}:c'     => [],    // category counts
            '{u:1}:b'     => [],    // brand/material/custom_tag counts
            '{u:1}:ach'   => [],    // unlocked set
        ]);

        $this->redisConn
            ->shouldReceive('script')
            ->with('LOAD', \Mockery::any())
            ->andReturn('fake‐sha');
        $this->redisConn
            ->shouldReceive('sAdd')
            ->andReturnUsing(fn($key, ...$slugs) => count($slugs));
        $this->redisConn
            ->shouldReceive('evalSha')
            ->andReturnUsing(function($sha, $numKeys, ...$args) {
                [$achKey, $statsKey] = array_slice($args, 0, 2);
                $xpToAdd = $args[$numKeys] ?? 0;
                $this->redisConn->hIncrBy($statsKey, 'xp', $xpToAdd);
                return 1;
            });

        // Baseline tags that the feature specs expect
        Category   ::firstOrCreate(['key' => 'packaging']);
        LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
        LitterObject::firstOrCreate(['key' => 'can']);

        TagKeyCache::forgetAll();

        Cache::forget('achievement:meta');
    }

    private function createAchievement(string $slug, int $xp): int
    {
        return Achievement::firstOrCreate(['slug' => $slug], [
            'name' => $slug,
            'xp'   => $xp,
        ])->id;
    }

    private function makePhoto(User $user, array $objects = []): Photo
    {
        $photo = new Photo();
        $photo->user_id = $user->id;
        $photo->setRelation('user', $user);
        $photo->created_at = Carbon::parse('2025-04-20 12:00:00');
        $photo->summary = [
            'tags' => collect($objects)->map(fn ($q) => ['quantity' => $q])->all(),
        ];
        return $photo;
    }

    // ------------------------------------------------------------------
    // Expression‑engine behaviour (data‑driven)
    // ------------------------------------------------------------------

    /**
     * @test
     * @dataProvider dslProvider
     */
    public function dsl_helpers_evaluate(string $when, array $redisSeed, array $objects, bool $expect): void
    {
        foreach ($redisSeed as $key => $value) {
            if (is_array($value)) {
                Redis::connection()->hMSet($key, $value);
            } else {
                Redis::connection()->set($key, $value);
            }
        }

        config()->set('achievements', ['foo' => ['xp' => 1, 'when' => $when]]);
        $this->createAchievement('foo', 1);

        $user  = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user, $objects);

        $engine = app(AchievementEngine::class);

        $result = $engine->slugsToUnlock($photo)->contains('foo');
        $this->assertSame($expect, $result);
    }

    public static function dslProvider(): array
    {
        return [
            'hasObject_positive' => [
                'hasObject("plastic_bottle",3)',
                [],
                ['plastic_bottle' => 3],
                true,
            ],
            'hasObject_negative' => [
                'hasObject("plastic_bottle",5)',
                [],
                ['plastic_bottle' => 3],
                false,
            ],
            'objectQty_combined' => [
                'objectQty("can")>=10',
                ['{u:1}:t' => ['can' => 9]],
                ['can' => 1],
                true,
            ],
            'streak' => [
                'stats.currentStreak>=3',
                ['{u:1}:streak' => '2'],
                [],
                false,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Unlock flow
    // ------------------------------------------------------------------

    /** @test */
    public function unlock_adds_xp_and_dispatches_event(): void
    {
        config()->set('achievements', ['x' => ['xp' => 20, 'when' => 'true']]);
        $this->createAchievement('x', 24);

        Event::fake();

        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $engine = app(AchievementEngine::class);

        $photo = $this->makePhoto($user);
        $engine->generateAchievements($photo);

        /** @var RedisFactory $redis */
        $redis = app(RedisFactory::class);
        $this->assertEquals(20, (int) $redis->connection()->hGet(sprintf('{u:%d}:stats', 1), 'xp'));
        $this->assertDatabaseCount('user_achievements', 2);
        Event::assertDispatchedTimes(AchievementsUnlocked::class, 1);
        $this->assertSame(1, $user->fresh()->level);
    }

    /** @test */
    public function unlock_levels_up_when_threshold_crossed(): void
    {
        config()->set('achievements', array_merge(config('achievements'), [
            'big' => ['xp' => 2000, 'when' => 'true'],
        ]));
        $this->createAchievement('big', 2000);

        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $engine = app(AchievementEngine::class);
        $engine->unlock($user, collect(['big']));

        $this->assertSame(5, $user->refresh()->level);
    }

    /** @test */
    public function first_in_country_unlocked(): void
    {
        config()->set('achievements', [
            'country-pioneer' => [
                'xp' => 10,
                'when' => 'isFirstInCountry()',
            ],
        ]);
        $this->createAchievement('country-pioneer', 10);

        $user = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user);
        $photo->country_id = 99;

        Redis::shouldReceive('evalSha')->andReturn(1);

        $engine = app(AchievementEngine::class);
        $slugs  = $engine->slugsToUnlock($photo);

        $this->assertTrue($slugs->contains('country-pioneer'));
    }

    // ------------------------------------------------------------------
    // Dynamic-milestone unlocks
    // ------------------------------------------------------------------

    /** @test */
    public function first_photo_unlocks_every_dimension_1_milestone(): void
    {
        $uploads = ['uploads-1'];
        $beerBottle = LitterObject::where('key', 'plastic_bottle')->first();
        $packCat    = Category::where('key', 'packaging')->first();
        $slugs = array_merge($uploads, [
            "object-{$beerBottle->id}-1",
            "category-{$packCat->id}-1",
            // material
            // brand
            // customTag
        ]);

        foreach ($slugs as $slug) {
            $this->createAchievement($slug, 2);
        }

        // $this->mockRedis([
//            '{u:1}:stats' => ['xp' => 0, 'uploads' => 0, 'st' => 0],
//            '{u:1}:t'     => [],
//            '{u:1}:ach'   => [],
//        ]);

        $user  = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user, ['plastic_bottle' => 1]);
        // one brand / material / customTag to hit every bucket
        $photo->summary = [
            'tags' => [
                'packaging' => [
                    'plastic_bottle' => [
                        'quantity'    => 1,
                        'materials'   => ['plastic'   => 1],
                        'brands'      => ['coke'      => 1],
                        'custom_tags' => ['washed_up' => 1],
                    ],
                ],
            ],
            'totals' => [
                'total_tags'    => 1,
                'total_objects' => 1,
                'by_category'   => ['packaging' => 1],
                'materials'     => 1,
                'brands'        => 1,
                'custom_tags'   => 1,
            ],
        ];

        app(AchievementEngine::class)->generateAchievements($photo);

        $unlocked = $user->fresh()->achievements->pluck('slug')->all();
        foreach ($slugs as $slug) {
            $this->assertContains($slug, $unlocked, "Missing {$slug}");
        }
        $this->assertCount(count($slugs), $unlocked);
    }

    /** @test */
    public function uploads_42_milestone_triggers_on_42nd_photo(): void
    {
        $this->createAchievement('uploads-42', 2);

        $user  = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user);

        // seed 41 prior uploads
        Redis::connection()->hIncrBy('{u:1}:stats', 'uploads', 41);

        // update redis
        RedisMetricsCollector::queue($photo);

        $engine = app(AchievementEngine::class);

        $this->assertTrue(
            $engine->slugsToUnlock($photo)->contains('uploads-42')
        );
    }

    /** @test */
    public function objects_42_milestone_triggers_when_cumulative_hits_42(): void
    {
        LitterObject::where('key', 'plastic_bottle')->first();

        $user  = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user, ['plastic_bottle' => 1]);

        Redis::connection()->hIncrBy('{u:1}:t', 'plastic_bottle', 41);
        RedisMetricsCollector::queue($photo);

        $this->assertTrue(
            app(AchievementEngine::class)
                ->slugsToUnlock($photo)
                ->contains('objects-42')
        );
    }

    /** @test */
    public function milestones_increment_for_every_tag_type(): void
    {
        // -----------------------------------------------------------------
        // 0.  Tag fixtures (creates one of each class)
        // -----------------------------------------------------------------
        $beerBottle  = LitterObject::firstOrCreate(['key' => 'beer_bottle']);
        $alcoholCat  = Category    ::firstOrCreate(['key' => 'alcohol']);
        $glassMat    = Materials   ::firstOrCreate(['key' => 'glass']);
        $heineken    = BrandList   ::firstOrCreate(['key' => 'heineken']);
        $myTag       = CustomTagNew::firstOrCreate(['key' => 'my_tag']);

        // Map “dimension” → tag key we’ll inject into the first photo
        $perDimension = [
            'object'     => $beerBottle->id,
            'category'   => $alcoholCat->id,
            'material'   => $glassMat->id,
            'brand'      => $heineken->id,
            'customTag'  => $myTag->id,
            'uploads'     => null,                // pseudo-dimension
        ];

        $milestones = [1, 42, 69];                // the set we want to prove

        // -----------------------------------------------------------------
        // 1.  Seed the achievements table from that map
        // -----------------------------------------------------------------
        $slugs42 = [];    // we’ll assert these later
        $slugs69 = [];

        foreach ($perDimension as $dim => $key) {
            foreach ($milestones as $m) {
                $slug = $key !== null ? "{$dim}-{$key}-{$m}" : "uploads-{$m}";

                Achievement::firstOrCreate(['slug' => $slug], [
                    'name' => $slug,
                    'xp'   => 0,
                ]);

                if ($m === 42) $slugs42[] = $slug;
                if ($m === 69) $slugs69[] = $slug;
            }
        }

        // -----------------------------------------------------------------
        // 2.  Wire Redis mock (uploads / objects / categories hashes only)
        //     We’ll bump *all* counters in one go to save boilerplate.
        // -----------------------------------------------------------------

        Redis::connection()->hMSet('{u:1}:stats', ['xp' => 0, 'uploads' => 1, 'st' => 0]);
        Redis::connection()->hMSet('{u:1}:t',      ['plastic_bottle' => 41]);
        Redis::connection()->hMSet('{u:1}:c',      ['packaging'       => 41]);
        Redis::connection()->hMSet('{u:1}:b', [
            "m:glass"   => 41,
            "b:coke"    => 41,
            "c:washed_up" => 41,
        ]);

        // -----------------------------------------------------------------
        // 3.  First photo (qty 1 for each tag) ----------------------------
        // -----------------------------------------------------------------
        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $engine = app(AchievementEngine::class);

        $p1 = $this->makePhoto($user, [$beerBottle->key => 1]);
        $p1->summary = $this->buildSummary($beerBottle->key, $glassMat->key, $heineken->key,  $myTag->key, 1);

        $unlocked = $engine->slugsToUnlock($p1);
        foreach ($perDimension as $dim => $k) {
            $slug1 = $k !== null ? "{$dim}-{$k}-1" : 'uploads-1';
            $this->assertTrue($unlocked->contains($slug1), "missing $slug1");
        }

        // -----------------------------------------------------------------
        // 4.  Second photo (+41 each → 42) -------------------------------
        // -----------------------------------------------------------------
        $p2 = $this->makePhoto($user, [$beerBottle->key => 41]);
        $p2->summary = $this->buildSummary($beerBottle->key, $glassMat->key, $heineken->key,  $myTag->key, 41);

        RedisMetricsCollector::queue($p2);
        $unlocked = $engine->slugsToUnlock($p2);

        foreach ($slugs42 as $slug) {
            $this->assertTrue($unlocked->contains($slug), "missing $slug (42)");
        }

        // -----------------------------------------------------------------
        // 5.  Third photo (+27 each → 69) --------------------------------
        // -----------------------------------------------------------------
        $p3 = $this->makePhoto($user, [$beerBottle->key => 27]);
        $p3->summary = $this->buildSummary($beerBottle->key, $glassMat->key, $heineken->key,  $myTag->key, 27);

        RedisMetricsCollector::queue($p3);
        $unlocked = $engine->slugsToUnlock($p3);

        foreach ($slugs69 as $slug) {
            $this->assertTrue($unlocked->contains($slug), "missing $slug (69)");
        }
    }

    /* Build a summary that hits every type once with $qty */
    private function buildSummary(
        string $objKey,
        string $matKey,
        string $brandKey,
        string $tagKey,
        int $qty
    ): array
    {
        return [
            'tags' => [
                'alcohol' => [
                    $objKey => [
                        'quantity'    => $qty,
                        'materials'   => [$matKey  => $qty],
                        'brands'      => [$brandKey => $qty],
                        'custom_tags' => [$tagKey   => $qty],
                    ],
                ],
            ],
            'totals' => [
                'total_objects' => $qty,
                'materials'     => $qty,
                'brands'        => $qty,
                'custom_tags'   => $qty,
            ],
        ];
    }
}
