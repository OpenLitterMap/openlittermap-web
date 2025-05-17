<?php
/**
 * Tests for the refactored Achievement subsystem (Engine + helpers + DTO).
 */

declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\Helpers\MockRedisTrait;
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;
    use MockRedisTrait;

    protected function setUp(): void
    {
        parent::setUp();

        static $seeded = false;

        if (! $seeded) {
            Category::firstOrCreate(['key' => 'packaging']);
            LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
            LitterObject::firstOrCreate(['key' => 'can']);
            $seeded = true;
        }

        $this->mockRedis(['{u:1}:stats' => ['xp' => 0, 'uploads' => 0, 'st' => 0]]);
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
                $this->redisConn->shouldReceive('hgetall')
                    ->with($key)
                    ->andReturn($value);
            } else {
                $this->redisConn->shouldReceive('get')
                    ->with($key)
                    ->andReturn($value);
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
        $engine->process($photo);

        /** @var RedisFactory $redis */
        $redis = app(RedisFactory::class);
        $this->assertEquals(22, (int) $redis->connection()->hGet(sprintf('{u:%d}:stats', 1), 'xp'));
        $this->assertDatabaseCount('user_achievements', 1);
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

        $this->mockRedis(['{u:1}:u' => '1']);

        $this->redisConn
            ->shouldReceive('evalSha')
            ->withAnyArgs()
            ->andReturn(1);    // make the helper truthy

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
        $slugs = [
            'uploads-1', 'objects-1', 'categories-1',
            'materials-1', 'customTags-1',
            // 'brands-1', ?
        ];
        foreach ($slugs as $slug) {
            $this->createAchievement($slug, 2);
        }

        $this->mockRedis([
            '{u:1}:stats' => ['xp' => 0, 'uploads' => 0, 'st' => 0],
            '{u:1}:t'     => [],
            '{u:1}:ach'   => [],
        ]);

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

        app(AchievementEngine::class)->process($photo);

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

        $this->mockRedis([
            '{u:1}:stats' => ['xp' => 0, 'uploads' => 41, 'st' => 0],
            '{u:1}:ach'   => [],
        ]);

        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo  = $this->makePhoto($user);          // 42nd upload
        $engine = app(AchievementEngine::class);

        $this->assertTrue(
            $engine->slugsToUnlock($photo)->contains('uploads-42')
        );
    }

    /** @test */
    public function objects_42_milestone_triggers_when_cumulative_hits_42(): void
    {
        $this->createAchievement('objects-42', 2);

        // user already has 41 bottles picked
        $this->mockRedis([
            '{u:1}:stats' => ['xp' => 0, 'uploads' => 0, 'st' => 0],
            '{u:1}:t'     => ['plastic_bottle' => 41],
            '{u:1}:ach'   => [],
        ]);

        $user  = User::factory()->create(['id' => 1, 'level' => 0]);
        $photo = $this->makePhoto($user, ['plastic_bottle' => 1]);

        $this->assertTrue(
            app(AchievementEngine::class)
                ->slugsToUnlock($photo)
                ->contains('objects-42')
        );
    }
}
