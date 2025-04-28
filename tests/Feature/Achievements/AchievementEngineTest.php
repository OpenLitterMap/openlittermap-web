<?php
declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, Event, Redis};
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------------- */
    /* Bootstrap                                                               */
    /* ---------------------------------------------------------------------- */
    protected function setUp(): void
    {
        parent::setUp();

        // one category + two object keys is enough for summaries
        Category::firstOrCreate(['key' => 'packaging']);
        LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
        LitterObject::firstOrCreate(['key' => 'can']);

        Cache::forget('achievement:meta');
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers                                                                 */
    /* ---------------------------------------------------------------------- */

    /** Tiny Redis stub – covers only commands hit during slugsToUnlock() */
    private function fakeRedis(array $seed = []): void
    {
        Redis::swap(new class($seed)
        {
            public function __construct(private array $d) {}

            // strings
            public function get(string $k)              { return $this->d[$k] ?? null; }
            public function incr(string $k,$by=1)       { $this->d[$k]=(string)(($this->d[$k]??0)+$by); }
            public function incrByFloat(string $k,$by)  { $this->incr($k,$by); }

            // hashes
            public function hgetall(string $k)          { return $this->d[$k] ?? []; }

            /* ignore the rest */
            public function __call($m,$a)               { return null; }
        });
    }

    private function createAch(string $slug,int $xp): void
    {
        Achievement::firstOrCreate(['slug'=>$slug],['name'=>$slug,'xp'=>$xp]);
    }

    private function makePhoto(User $u,array $objects=[]): Photo
    {
        $p              = new Photo();
        $p->user_id     = $u->id;
        $p->setRelation('user',$u);
        $p->created_at  = Carbon::parse('2025-04-20 12:00:00');
        $p->summary     = ['tags'=>collect($objects)->map(fn($q)=>['quantity'=>$q])->all()];
        return $p;
    }

    /* ---------------------------------------------------------------------- */
    /* Tests                                                                   */
    /* ---------------------------------------------------------------------- */

    /** hasObject() unlocks when photo contains required qty */
    public function test_has_object_helper(): void
    {
        config()->set('achievements',[
            'slayer'=>['xp'=>5,'when'=>'hasObject("plastic_bottle",3)']
        ]);
        $this->createAch('slayer',5);
        $this->fakeRedis();                 // engine reads from stub

        $u = User::factory()->create();
        $p = $this->makePhoto($u,['plastic_bottle'=>3]);

        $this->assertSame(['slayer'],(new AchievementEngine)->slugsToUnlock($p)->all());
    }

    /** objectQty() consults Redis cumulative counter */
    public function test_object_qty_helper(): void
    {
        config()->set('achievements',[
            'legend'=>['xp'=>50,'when'=>'objectQty("can")>=10']
        ]);
        $this->createAch('legend',50);

        $this->fakeRedis([
            sprintf('users:%d:totals:objects',1)=>['can'=>9],
        ]);

        $u = User::factory()->create(['id'=>1]);
        $p = $this->makePhoto($u,['can'=>1]);

        $this->assertTrue((new AchievementEngine)->slugsToUnlock($p)->contains('legend'));
    }

    /** current_streak helper uses streak key */
    public function test_streak_helper(): void
    {
        config()->set('achievements',[
            'tri'=>['xp'=>1,'when'=>'stats.current_streak>=3']
        ]);
        $this->createAch('tri',1);

        $this->fakeRedis([
            '{u:5}:streak'=>'3',
        ]);

        $u = User::factory()->create(['id'=>5]);
        $p = $this->makePhoto($u);

        $this->assertSame(['tri'],(new AchievementEngine)->slugsToUnlock($p)->all());
    }

    /** Engine filters already-owned achievements */
    public function test_already_owned_filtered(): void
    {
        $this->createAch('a',1);
        config()->set('achievements',['a'=>['xp'=>1,'when'=>'true']]);

        $u = User::factory()->create();
        $u->achievements()->attach(Achievement::whereSlug('a')->first(),['unlocked_at'=>now()]);
        $p = $this->makePhoto($u);

        $this->assertFalse((new AchievementEngine)->slugsToUnlock($p)->contains('a'));
    }

    /** unlock() – pivot, XP, event, idempotent */
    public function test_unlock_flow(): void
    {
        Redis::flushdb();                              // real redis, not fake
        $this->createAch('x',20);
        config()->set('achievements',['x'=>['xp'=>20,'when'=>'true']]);

        Event::fake();

        $u = User::factory()->create();

        $engine = new AchievementEngine;
        $engine->unlock($u,collect(['x']));
        $engine->unlock($u,collect(['x']));            // duplicate ignored

        $this->assertSame('20',Redis::get("{u:{$u->id}}:xp"));
        $this->assertDatabaseCount('user_achievements',1);
        Event::assertDispatchedTimes(AchievementsUnlocked::class,1);
    }

    /** level recalculates from Redis XP */
    public function test_level_up(): void
    {
        Redis::flushdb();
        $this->createAch('big',2000);
        config()->set('achievements',['big'=>['xp'=>2000,'when'=>'true']]);

        $u = User::factory()->create(['level'=>0]);

        (new AchievementEngine)->unlock($u,collect(['big']));
        $u->refresh();

        $this->assertSame(2,$u->level); // 2000 / 1000
    }

    /** unlock() no-op with empty slug collection */
    public function test_unlock_noop(): void
    {
        Redis::flushdb();
        $u = User::factory()->create();

        (new AchievementEngine)->unlock($u,collect());

        $this->assertDatabaseCount('user_achievements',0);
        $this->assertNull(Redis::get("{u:{$u->id}}:xp"));
    }
}
