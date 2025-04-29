<?php
/**
 * Tests for the refactored Achievement subsystem (Engine + helpers + DTO).
 */

declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Level;
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
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Redis fake
    // ------------------------------------------------------------------

    /**
     * Binds an in‑memory Redis mock that supports the minimal API surface
     * the AchievementEngine touches – `get`, `incrby`, `hgetall`, and
     * a *realistic* `pipeline()` that returns an ordered result array.
     */
    protected function fakeRedis(array $seed = []): void
    {
        // Stateless helper so we can capture return values inside `pipeline()`
        $connection = new class($seed)
        {
            private array $db;
            public function __construct(array $seed){$this->db = $seed;}

            // ─── string ops ─────────────────────────────────────────────
            public function get(string $key){return $this->db[$key] ?? null;}
            public function incrby(string $key, int $delta)
            {
                $this->db[$key] = (string) ( ($this->db[$key] ?? 0) + $delta );
            }

            // ─── hash ops ───────────────────────────────────────────────
            public function hgetall(string $key){return $this->db[$key] ?? [];}

            // ─── pipeline – collects *ordered* results like real Predis ──
            public function pipeline(callable $cb): array
            {
                $results = [];
                $pipe = new class($this, $results) {
                    private array $results;
                    public function __construct(private $conn, array &$results){$this->results = &$results;}
                    public function get($k){ $res = $this->conn->get($k); $this->results[] = $res; return $res; }
                    public function incrby($k,$v){ $this->conn->incrby($k,$v); $this->results[] = null; }
                    public function hgetall($k){ $res = $this->conn->hgetall($k); $this->results[] = $res; return $res; }
                };
                $cb($pipe);
                return $results;
            }
        };

        // Minimal factory so the engine resolves $redis->connection()
        $factory = new class($connection) implements RedisFactory
        {
            public function __construct(private $conn){}
            public function connection($name = null){return $this->conn;}
            public function client(){return $this->conn;}
        };

        $this->instance(RedisFactory::class, $factory);
    }

    // ------------------------------------------------------------------
    // setUp helpers
    // ------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRedis(); // bind before resolving anything that needs Redis

        Cache::forget('achievement:meta');

        Category::firstOrCreate(['key' => 'packaging']);
        LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
        LitterObject::firstOrCreate(['key' => 'can']);
    }

    private function createAch(string $slug, int $xp): int
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
        $this->fakeRedis($redisSeed);
        config()->set('achievements', ['foo' => ['xp' => 1, 'when' => $when]]);
        $this->createAch('foo', 1);

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
                ['users:1:totals:objects' => ['can' => 9]],
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
        $id = $this->createAch('x', 20);

        Event::fake();

        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $engine = app(AchievementEngine::class);

        $engine->unlock($user, collect(['x']));          // first time
        $engine->unlock($user, collect(['x']));          // duplicate ignored

        /** @var RedisFactory $redis */
        $redis = app(RedisFactory::class);
        $this->assertSame('20', $redis->connection()->get('{u:1}:xp'));
        $this->assertDatabaseCount('user_achievements', 1);
        Event::assertDispatchedTimes(AchievementsUnlocked::class, 1);
        $this->assertSame(0, $user->fresh()->level);       // 20 < 1000 → level unchanged
    }

    /** @test */
    public function unlock_levels_up_when_threshold_crossed(): void
    {
        config()->set('achievements', ['big' => ['xp' => 2000, 'when' => 'true']]);
        $this->createAch('big', 2000);

        $user   = User::factory()->create(['id' => 1, 'level' => 0]);
        $engine = app(AchievementEngine::class);

        $engine->unlock($user, collect(['big']));
        $this->assertSame(2, $user->fresh()->level); // 2k / 1k
    }
}
