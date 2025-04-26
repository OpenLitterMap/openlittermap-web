<?php

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Event, Redis};
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([GenerateTagsSeeder::class, GenerateBrandsSeeder::class]);

        Cache::forget('achievement:meta');
    }

    /*──────────────────────── helpers ────────────────────────*/

    /** Swap Redis connection, fake only hgetall() used by the engine. */
    private function fakeRedis(array $maps = []): void
    {
        Redis::swap(new class($maps) {
            public function __construct(private array $maps) {}
            public function hgetall(string $key) { return $this->maps[$key] ?? []; }
            public function __call($m,$a) {} // no-op for other commands
        });
    }

    /** Create Achievement rows so pivot inserts have FK targets. */
    private function makeAchievement(string $slug, int $xp = 0): Achievement
    {
        return Achievement::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'xp' => $xp]
        );
    }

    /** Build a photo summary with real object keys from the seeder. */
    private function photoFor(User $user, array $objectsQty): Photo
    {
        // guarantee the category key exists (seeder created it already)
        Category::firstOrCreate(['key' => 'packaging']);

        return Photo::factory()->for($user)->create([
            'summary' => [
                'tags' => collect($objectsQty)->map(fn($q) => [
                    'quantity'  => $q,
                    'materials' => [],
                    'brands'    => [],
                ])->all(),
            ],
        ]);
    }

    /*──────────────────────── tests ──────────────────────────*/

    /** hasObject() unlocks slug when photo contains required qty */
    public function test_has_object_helper_unlocks_slug(): void
    {
        config()->set('achievements', [
            'plastic-slayer' => ['xp' => 10, 'when' => 'hasObject("plastic_bottle", 5)'],
        ]);
        $this->makeAchievement('plastic-slayer', 10);

        $this->fakeRedis();

        $user  = User::factory()->create();
        $photo = $this->photoFor($user, ['plastic_bottle' => 5]);

        $engine = new AchievementEngine;

        $this->assertSame(['plastic-slayer'], $engine->slugsToUnlock($photo)->all());
    }

    /** objectQty() uses Redis cumulative counter */
    public function test_object_qty_helper_uses_redis(): void
    {
        config()->set('achievements', [
            'legend' => ['xp' => 100, 'when' => 'objectQty("can") >= 1000'],
        ]);
        $this->makeAchievement('legend', 100);

        $this->fakeRedis([
            sprintf('users:%d:totals:objects', 1) => ['can' => 999],
        ]);

        $user  = User::factory()->create(['id' => 1]);
        $photo = $this->photoFor($user, ['can' => 1]);

        $this->assertTrue(
            (new AchievementEngine)->slugsToUnlock($photo)->contains('legend')
        );
    }

    /** stats.current_streak comes from Redis hash */
    public function test_streak_from_redis_hash(): void
    {
        config()->set('achievements', [
            'streak-3' => ['xp' => 5, 'when' => 'stats.current_streak >= 3'],
        ]);
        $this->makeAchievement('streak-3', 5);

        $this->fakeRedis([
            'activity:users:1' => [
                now()->toDateString()             => 1,
                now()->subDay()->toDateString()   => 1,
                now()->subDays(2)->toDateString() => 1,
            ],
        ]);

        $user  = User::factory()->create(['id' => 1]);
        $photo = $this->photoFor($user, ['can' => 1]);

        $this->assertSame(['streak-3'], (new AchievementEngine)->slugsToUnlock($photo)->all());
    }

    /** already-owned achievements are filtered out by slugsToUnlock */
    public function test_already_has_achievement_is_filtered(): void
    {
        $ach = $this->makeAchievement('first-upload', 1);
        config()->set('achievements', [
            'first-upload' => ['xp' => 1, 'when' => 'true'],
        ]);

        $user = User::factory()->create();
        $user->achievements()->attach($ach->id, ['unlocked_at' => now()]);

        $photo = $this->photoFor($user, ['can' => 1]);

        $this->assertFalse(
            (new AchievementEngine)->slugsToUnlock($photo)->contains('first-upload')
        );
    }

    /** unlock() writes pivot row and increments XP */
    public function test_unlock_persists_pivot_and_xp(): void
    {
        $ach = $this->makeAchievement('slug-a', 50);
        config()->set('achievements', [
            'slug-a' => ['xp' => 50, 'when' => 'true'],
        ]);

        $user = User::factory()->create(['xp' => 0]);

        (new AchievementEngine)->unlock($user, collect(['slug-a']));

        $this->assertEquals(50, $user->fresh()->xp);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $ach->id,
        ]);
    }

    /** unlock() levels up user when XP crosses boundary */
    public function test_level_up_logic(): void
    {
        $this->makeAchievement('big', 1500);
        config()->set('achievements', [
            'big' => ['xp' => 1500, 'when' => 'true'],
        ]);

        $user = User::factory()->create(['xp' => 0, 'level' => 0]);

        (new AchievementEngine)->unlock($user, collect(['big']));

        $user->refresh();
        $this->assertSame(1, $user->level);
        $this->assertNotNull($user->leveled_up_at);
    }

    /** AchievementsUnlocked event is dispatched with correct payload */
    public function test_event_is_dispatched(): void
    {
        $this->makeAchievement('e', 10);
        config()->set('achievements', [
            'e' => ['xp' => 10, 'when' => 'true'],
        ]);

        Event::fake();

        $user = User::factory()->create();
        (new AchievementEngine)->unlock($user, collect(['e']));

        Event::assertDispatched(AchievementsUnlocked::class,
            fn ($e) => $e->user->is($user) && $e->achievements->keys()->contains('e')
        );
    }

    /** unlock() is a no-op when given an empty slug collection */
    public function test_unlock_no_op_with_empty_slugs(): void
    {
        $user = User::factory()->create(['xp' => 0]);

        (new AchievementEngine)->unlock($user, collect());

        $this->assertEquals(0, $user->fresh()->xp);
        $this->assertDatabaseCount('user_achievements', 0);
    }
}
