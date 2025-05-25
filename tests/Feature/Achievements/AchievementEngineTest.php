<?php
declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Events\AchievementsUnlocked;
use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\{BrandList, Category, LitterObject, Materials};
use App\Models\Location\{Country, State, City};
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, DB, Event, Redis};
use Tests\TestCase;

class AchievementEngineTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------
     |  Helpers
     * ------------------------------------------------------------------*/

    /** Map token → tinyint code (keep in sync with production enum) */
    private const DIM = [
        // dimension-wide
        'uploads'    => 0,
        'objects'    => 1,
        'categories' => 2,
        'materials'  => 3,
        'brands'     => 4,
        // per-tag
        'object'     => 5,
        'category'   => 6,
        'material'   => 7,
        'brand'      => 8,
    ];

    private int $countryId;
    private int $stateId;
    private int $cityId;

    private function dimCode(string $token): int
    {
        return self::DIM[$token] ?? throw new \InvalidArgumentException("Unknown dimension: $token");
    }

    /** Create an achievement definition row from a slug */
    private function define(string $slug, int $xp): Achievement
    {
        $parts = explode('-', $slug);
        if (count($parts) === 2) {
            [$token, $threshold] = $parts;
            $tagId = 0;
        } elseif (count($parts) === 3) {
            [$token, $tagId, $threshold] = $parts;
            $tagId = (int) $tagId;
        } else {
            throw new \InvalidArgumentException('Bad slug: ' . $slug);
        }

        return Achievement::create([
            'slug'      => $slug,
            'dimension' => $this->dimCode($token),
            'tag_id'    => $tagId,
            'threshold' => (int) $threshold,
            'xp'        => $xp,
        ]);
    }

    private function makePhoto(User $user, array $summary): Photo
    {
        return tap(new Photo(), function (Photo $p) use ($user, $summary) {
            $p->user_id    = $user->id;
            $p->created_at = Carbon::parse('2025-01-20 12:00:00');
            $p->summary    = $summary;
            $p->filename = 'test.png';
            $p->model = 'iphone';
            $p->datetime = Carbon::parse('2025-01-20 12:00:00');

            $p->country_id= $this->countryId;
            $p->state_id  = $this->stateId;
            $p->city_id   = $this->cityId;

            $p->setRelation('user', $user);
            $p->setRelation('country', null);   // avoid lazy-load queries
            $p->setRelation('state',   null);
            $p->setRelation('city',    null);
            $p->setRelation('user', $user);

            $p->save();
        });
    }

    private function assertUnlocked(User $u, string $slug): void
    {
        $id = Achievement::where('slug', $slug)->value('id');
        $this->assertDatabaseHas('user_achievements', [
            'user_id'        => $u->id,
            'achievement_id' => $id,
        ]);
    }

    private function assertNotUnlocked(User $u, string $slug): void
    {
        $id = Achievement::where('slug', $slug)->value('id');
        $this->assertDatabaseMissing('user_achievements', [
            'user_id'        => $u->id,
            'achievement_id' => $id,
        ]);
    }

    /* ------------------------------------------------------------------
     |  Bootstrap
     * ------------------------------------------------------------------*/

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushDB();
        Cache::flush();

        $country = Country::factory()->create();
        $state = State::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->create(['country_id' => $country->id, 'state_id' => $state->id]);
        $this->countryId = $country->id;
        $this->stateId = $state->id;
        $this->cityId = $city->id;

        Category::firstOrCreate(['key' => 'packaging']);
        LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
        LitterObject::firstOrCreate(['key' => 'can']);

        config(['achievements.milestones' => [1, 10, 42, 69, 100]]);
    }

    /* ------------------------------------------------------------------
     |  Tests
     * ------------------------------------------------------------------*/

    /** @test */
    public function first_upload_unlocks(): void
    {
        $this->define('uploads-1', 10);

        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 1]]],
        ]);

        Event::fake();
        RedisMetricsCollector::queue($p);
        app(AchievementEngine::class)->generateAchievements($p);

        $this->assertUnlocked($u, 'uploads-1');
        Event::assertDispatched(AchievementsUnlocked::class);
        $this->assertEquals(10, (int) Redis::hGet("{u:{$u->id}}:stats", 'xp'));
    }

    /** @test */
    public function per_tag_object_milestones(): void
    {
        $bottle = LitterObject::where('key', 'plastic_bottle')->first();
        $this->define("object-{$bottle->id}-1", 5);
        $this->define("object-{$bottle->id}-10", 20);

        $u = User::factory()->create();

        RedisMetricsCollector::queue(
            $this->makePhoto($u, ['tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 5]]]])
        );
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        $this->assertUnlocked($u, "object-{$bottle->id}-1");
        $this->assertNotUnlocked($u, "object-{$bottle->id}-10");

        RedisMetricsCollector::queue(
            $this->makePhoto($u, ['tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 5]]]])
        );
        app(AchievementEngine::class)->generateAchievements(Photo::latest()->first());

        $this->assertUnlocked($u, "object-{$bottle->id}-10");
    }

    /** @test */
    public function category_milestones(): void
    {
        $cat = Category::first();
        $this->define("category-{$cat->id}-1", 5);
        $this->define("category-{$cat->id}-10", 20);

        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'packaging' => [
                    'plastic_bottle' => ['quantity' => 6],
                    'can'            => ['quantity' => 4],
                ],
            ],
            'totals' => ['by_category' => ['packaging' => 10]],
        ];

        RedisMetricsCollector::queue($this->makePhoto($u, $summary));
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        $this->assertUnlocked($u, "category-{$cat->id}-1");
        $this->assertUnlocked($u, "category-{$cat->id}-10");
    }

    /** @test */
    public function material_and_brand_milestones(): void
    {
        $plastic = Materials::firstOrCreate(['key' => 'plastic']);
        $coke    = BrandList::firstOrCreate(['key' => 'coca_cola']);

        $this->define("material-{$plastic->id}-1", 5);
        $this->define("material-{$plastic->id}-10", 20);
        $this->define("brand-{$coke->id}-1", 5);
        $this->define("brand-{$coke->id}-10", 20);

        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'packaging' => [
                    'plastic_bottle' => [
                        'quantity'  => 10,
                        'materials' => ['plastic'   => 10],
                        'brands'    => ['coca_cola' => 10],
                    ],
                ],
            ],
        ];

        RedisMetricsCollector::queue($this->makePhoto($u, $summary));
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        // material
        $this->assertUnlocked($u, "material-{$plastic->id}-1");
        $this->assertUnlocked($u, "material-{$plastic->id}-10");

        // brand
        $this->assertUnlocked($u, "brand-{$coke->id}-1");
        $this->assertUnlocked($u, "brand-{$coke->id}-10");
    }

    /** @test */
    public function dimension_wide_milestones(): void
    {
        collect([
            'objects-10'   => 10,
            'objects-42'   => 20,
            'categories-1' => 5,
            'materials-10' => 15,
            'brands-10'    => 15,
        ])->each(fn ($xp, $slug) => $this->define($slug, $xp));

        $u = User::factory()->create();

        $summary = [
            'tags' => [
                'packaging' => [
                    'plastic_bottle' => [
                        'quantity'  => 30,
                        'materials' => ['plastic'    => 30],
                        'brands'    => ['coca_cola'  => 15, 'pepsi' => 15],
                    ],
                    'can' => [
                        'quantity'  => 12,
                        'materials' => ['aluminium' => 12],
                        'brands'    => ['coca_cola' => 12],
                    ],
                ],
            ],
        ];

        RedisMetricsCollector::queue($this->makePhoto($u, $summary));
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        foreach (['objects-10', 'objects-42', 'categories-1', 'materials-10', 'brands-10'] as $s) {
            $this->assertUnlocked($u, $s);
        }
    }

    /** @test */
    public function idempotent_unlocks(): void
    {
        $this->define('uploads-1', 10);

        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 1]]],
        ]);

        Event::fake();
        RedisMetricsCollector::queue($p);
        app(AchievementEngine::class)->generateAchievements($p);
        app(AchievementEngine::class)->generateAchievements($p);

        $this->assertUnlocked($u, 'uploads-1');
        Event::assertDispatchedTimes(AchievementsUnlocked::class, 1);
    }

    /** @test */
    public function user_levels_up_via_xp(): void
    {
        config(['level' => [0 => 0, 100 => 1, 500 => 2, 1000 => 3]]);
        $this->define('uploads-1', 600);

        $u = User::factory()->create(['level' => 0]);

        RedisMetricsCollector::queue(
            $this->makePhoto($u, ['tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 1]]]])
        );
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        $this->assertEquals(3, $u->fresh()->level);
    }

    /** @test */
    public function tag_lookup_is_cached(): void
    {
        $bottle = LitterObject::where('key', 'plastic_bottle')->first();
        $this->define("object-{$bottle->id}-1", 5);

        $u = User::factory()->create();

        DB::enableQueryLog();

        RedisMetricsCollector::queue(
            $this->makePhoto($u, ['tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 1]]]])
        );
        app(AchievementEngine::class)->generateAchievements(Photo::first());

        $first = count(DB::getQueryLog());
        DB::flushQueryLog();

        RedisMetricsCollector::queue(
            $this->makePhoto($u, ['tags' => ['packaging' => ['plastic_bottle' => ['quantity' => 1]]]])
        );
        app(AchievementEngine::class)->generateAchievements(Photo::latest()->first());

        $second = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan($first, $second);
    }

    /** @test */
    public function batch_queue_processing(): void
    {
        $this->define('uploads-1', 10);
        $this->define('uploads-10', 50);

        $users = User::factory()->count(5)->create();
        foreach ($users as $u) {
            Redis::hSet("{u:{$u->id}}:stats", 'uploads', 10);
            Redis::sAdd('achievement:queue', $u->id);
        }

        Event::fake();
        app(AchievementEngine::class)->processQueue();

        foreach ($users as $u) {
            $this->assertUnlocked($u, 'uploads-1');
            $this->assertUnlocked($u, 'uploads-10');
        }
        $this->assertEquals(0, Redis::sCard('achievement:queue'));
    }

    /** @test */
    public function missing_tags_are_ignored(): void
    {
        $this->define('object-99999-1', 5);

        $u = User::factory()->create();
        $p = $this->makePhoto($u, [
            'tags' => ['packaging' => ['ghost_tag' => ['quantity' => 1]]],
        ]);

        RedisMetricsCollector::queue($p);
        app(AchievementEngine::class)->generateAchievements($p);

        $this->assertNotUnlocked($u, 'object-99999-1');
    }
}
