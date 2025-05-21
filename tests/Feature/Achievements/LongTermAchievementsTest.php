<?php
declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\{
    BrandList, Category, CustomTagNew, LitterObject, Materials
};
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LongTermAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();

        app(RedisFactory::class)->connection()->flushdb();
    }

    /** @test */
    public function engine_handles_120_photos_over_six_months(): void
    {
        /* -------------------------------------------------------------
           0.  Seed a core tag universe
        --------------------------------------------------------------*/
        $oBottle   = LitterObject::firstOrCreate(['key' => 'plastic_bottle']);
        $oCan      = LitterObject::firstOrCreate(['key' => 'can']);
        $oBag      = LitterObject::firstOrCreate(['key' => 'plastic_bag']);
        $oMask     = LitterObject::firstOrCreate(['key' => 'mask']);
        $oCup      = LitterObject::firstOrCreate(['key' => 'paper_cup']);
        $oStraw    = LitterObject::firstOrCreate(['key' => 'straw']);
        $oWrapper  = LitterObject::firstOrCreate(['key' => 'wrapper']);

        $cFood     = Category    ::firstOrCreate(['key' => 'food']);
        $cBeverage = Category    ::firstOrCreate(['key' => 'beverage']);
        $cMedical  = Category    ::firstOrCreate(['key' => 'medical']);
        $cGeneral  = Category    ::firstOrCreate(['key' => 'general']);

        $mGlass    = Materials   ::firstOrCreate(['key' => 'glass']);
        $mPlastic  = Materials   ::firstOrCreate(['key' => 'plastic']);

        $bCoke     = BrandList   ::firstOrCreate(['key' => 'coca_cola']);
        $bPepsi    = BrandList   ::firstOrCreate(['key' => 'pepsi']);
        $bHeineken = BrandList   ::firstOrCreate(['key' => 'heineken']);

        $tBeach    = CustomTagNew::firstOrCreate(['key' => 'beach']);
        $tPark     = CustomTagNew::firstOrCreate(['key' => 'park']);

        TagKeyCache::forgetAll();                           // force-refresh maps

        /* -------------------------------------------------------------
           1.  EXP milestones – copy the prod list but shrink XP
        --------------------------------------------------------------*/
        $cfg = [
            'night-owl' => ['xp' => 5,  'when' => "timeOfDay() == 'night'"],
            'weekend'   => ['xp' => 5,  'when' => 'isWeekend()'],
        ];

        // also wire up our three uploads-N milestones
        foreach ([1,42,69] as $m) {
            $cfg["uploads-{$m}"] = [
                'xp'   => 2,
                'when' => "statCount('uploads') >= {$m}",
            ];

            Achievement::updateOrCreate(
                ['slug'=>"uploads-{$m}"],
                ['name'=>"uploads {$m}", 'xp'=>2],
            );
        }

        config()->set('achievements', $cfg);

        foreach (['night-owl','weekend'] as $slug) {
            Achievement::updateOrCreate(
                ['slug'=>$slug],
                ['name'=>$slug, 'xp'=>config('achievements')[$slug]['xp']]
            );
        }

        /* engine picks up dynamic milestones automatically */
        $user = User::factory()->create(['id' => 7, 'level' => 0]);

        /* -------------------------------------------------------------
           2.  Mock Redis baseline - no longer needed
        --------------------------------------------------------------*/

        /* -------------------------------------------------------------
           3.  Simulate 120 photos over ~180 days
        --------------------------------------------------------------*/
        $engine  = app(AchievementEngine::class);
        $unlocks = collect();

        $start = CarbonImmutable::parse('2025-01-01 10:00:00');

        /** helper that alternates between tag mixes */
        $photoPayload = function (int $i) use (
            $oBottle,$oCan,$oBag,$oMask,$oCup,$oStraw,$oWrapper,
            $cFood,$cBeverage,$cMedical,$cGeneral,
            $mGlass,$mPlastic,
            $bCoke,$bPepsi,$bHeineken,
            $tBeach,$tPark
        ): array {
            $objects = [
                [$oBottle->key => 1, $oCan->key  => 1],                // light
                [$oBag->key    => 3, $oMask->key => 2],                // plastics
                [$oCup->key    => 5, $oStraw->key=> 5],                // party
                [$oWrapper->key=> 7],                                  // snack
            ][$i % 4];

            $category = match ($i % 4) {
                0       => $cBeverage->key,
                1       => $cGeneral ->key,
                2       => $cFood    ->key,
                default => $cMedical ->key,
            };

            $material = $i % 2 === 0 ? $mPlastic->key : $mGlass->key;
            $brand    = [ $bCoke->key, $bPepsi->key, $bHeineken->key ][$i % 3];
            $tag      = $i % 2 === 0 ? $tBeach->key : $tPark->key;

            $qty = array_sum($objects);

            return [
                'tags' => [
                    $category => array_map(fn($q)=>[
                        'quantity'    => $q,
                        'materials'   => [$material => $q],
                        'brands'      => [$brand    => $q],
                        'custom_tags' => [$tag      => $q],
                    ], $objects),
                ],
                'totals' => [
                    'total_objects' => $qty,
                    'materials'     => $qty,
                    'brands'        => $qty,
                    'custom_tags'   => $qty,
                    'by_category'   => [$category => $qty],
                ],
            ];
        };

        for ($i = 0; $i < 120; $i++) {

            // add a brand and material half-way to prove cache busting
            if ($i === 60) {
                $bSprite = BrandList ::firstOrCreate(['key' => 'sprite']);
                $mAlu    = Materials::firstOrCreate(['key' => 'aluminium']);
                TagKeyCache::forgetAll();

                // rebuild the engine to pick up the new "-1" milestones
                $engine = app(AchievementEngine::class);
            }

            $photo = new Photo();
            $photo->setRelation('user', $user);
            $photo->user_id    = $user->id;
            $photo->created_at = $start->addDays($i);
            $photo->summary    = $photoPayload($i);

            // first, persist that photo’s metrics exactly like production does
            RedisMetricsCollector::queue($photo);

            // now ask the achievement engine what new slugs fire
            $slugs   = $engine->slugsToUnlock($photo);
            $unlocks = $unlocks->merge($slugs);

            // we don’t call ->process() because that would mutate Redis.
        }

        /* -------------------------------------------------------------
           4.  Assertions
        --------------------------------------------------------------*/
        // uploads-1 / 42 / 69 must fire
        foreach ([1, 42, 69] as $m) {
            $this->assertTrue($unlocks->contains("uploads-{$m}"));
        }

        // objects-69 (total objects after 120 photos is > 400)
        $this->assertTrue($unlocks->contains('objects-69'));

        // at least one milestone for every dimension (object, category…)
        $dims = ['object','category','material','brand','customTag'];
        foreach ($dims as $dim) {
            $this->assertTrue(
                $unlocks->first(fn ($s) => str_starts_with($s, $dim.'-')) !== null,
                "no unlocks for $dim"
            );
        }

        // make sure adding the extra brand/material mid-way unlocked its “-1”
        $this->assertTrue($unlocks->contains("brand-{$bSprite->id}-1"));
        $this->assertTrue($unlocks->contains("material-{$mAlu->id}-1"));

        // XP total is exactly the sum of the XP in the unlocked slugs
        // only look at each slug once
        $uniq = $unlocks->unique();
        $xpTotal = Achievement::whereIn('slug', $uniq)->sum('xp');
        $this->assertEquals(
            $xpTotal,
            $uniq->sum(fn($slug) => config('achievements')[$slug]['xp'] ?? 0),
        );
    }

    /** @test */
    public function xp_is_added_once_per_slug_even_on_repeated_photos(): void
    {
        config()->set('milestones', []);

        config()->set('achievements', [
            'spam' => ['xp' => 10, 'when' => 'true'],
        ]);
        Achievement::firstOrCreate(['slug' => 'spam'], ['name'=>'spam','xp'=>10]);

        $user = User::factory()->create(['id'=>99, 'level' => 0]);
        $photo = new Photo();
        $photo->setRelation('user',$user);
        $photo->user_id = $user->id;
        $photo->created_at = now();
        $photo->summary = ['tags'=>[],'totals'=>[]];

        $engine = app(AchievementEngine::class);

        /* process the *same* photo twice */
        RedisMetricsCollector::queue($photo);
        $engine->generateAchievements($photo);
        $engine->generateAchievements($photo);

        /** XP in Redis should be 10, not 20 */
        $this->assertEquals(10, app('redis')->connection()->hGet('{u:99}:stats','xp'));

        /** only one pivot row */
        $this->assertDatabaseCount('user_achievements', 1);
    }


    /** @test */
    public function streak_and_country_achievements_coexist(): void
    {
        config()->set('achievements', [
            'streak-3'        => ['xp'=>5, 'when'=>'stats.currentStreak==3'],
            'country-pioneer' => ['xp'=>5, 'when'=>'isFirstInCountry()'],
        ]);
        Achievement::firstOrCreate(['slug'=>'streak-3'],['name'=>'x','xp'=>5]);
        Achievement::firstOrCreate(['slug'=>'country-pioneer'],['name'=>'y','xp'=>5]);

        TagKeyCache::forgetAll();

        $user = User::factory()->create(['id'=>11, 'level' => 0]);

        $engine = app(AchievementEngine::class);
        $photo  = new Photo();
        $photo->setRelation('user',$user);
        $photo->user_id = $user->id;
        $photo->country_id = 55;
        $photo->created_at = now();
        $photo->summary = ['tags'=>[],'totals'=>[]];

        // simulate a 3-day streak
        $day1 = (clone $photo)->setCreatedAt(now()->subDays(2));
        $day2 = (clone $photo)->setCreatedAt(now()->subDay());
        RedisMetricsCollector::queue($day1);
        RedisMetricsCollector::queue($day2);
        RedisMetricsCollector::queue($photo);
        $slugs = $engine->slugsToUnlock($photo);

        $this->assertTrue($slugs->contains('streak-3'));
        $this->assertTrue($slugs->contains('country-pioneer'));
    }
}
