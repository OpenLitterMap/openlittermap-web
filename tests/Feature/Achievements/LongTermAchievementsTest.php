<?php
declare(strict_types=1);

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\{BrandList, Category, CustomTagNew, LitterObject, Materials};
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisMetricsCollector;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LongTermAchievementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushDB();
        app(RedisFactory::class)->connection()->flushDB();
    }

    /** @test */
    public function engine_handles_120_photos_over_six_months(): void
    {
        /* 0. core tag universe ------------------------------------------- */
        $oBottle   = LitterObject::firstOrCreate(['key'=>'plastic_bottle']);
        $oCan      = LitterObject::firstOrCreate(['key'=>'can']);
        $oBag      = LitterObject::firstOrCreate(['key'=>'plastic_bag']);
        $oMask     = LitterObject::firstOrCreate(['key'=>'mask']);
        $oCup      = LitterObject::firstOrCreate(['key'=>'paper_cup']);
        $oStraw    = LitterObject::firstOrCreate(['key'=>'straw']);
        $oWrapper  = LitterObject::firstOrCreate(['key'=>'wrapper']);

        $cFood     = Category ::firstOrCreate(['key'=>'food']);
        $cBev      = Category ::firstOrCreate(['key'=>'beverage']);
        $cMed      = Category ::firstOrCreate(['key'=>'medical']);
        $cGen      = Category ::firstOrCreate(['key'=>'general']);

        $mGlass    = Materials::firstOrCreate(['key'=>'glass']);
        $mPlastic  = Materials::firstOrCreate(['key'=>'plastic']);

        $bCoke     = BrandList::firstOrCreate(['key'=>'coca_cola']);
        $bPepsi    = BrandList::firstOrCreate(['key'=>'pepsi']);
        $bHein     = BrandList::firstOrCreate(['key'=>'heineken']);

        $tBeach    = CustomTagNew::firstOrCreate(['key'=>'beach']);
        $tPark     = CustomTagNew::firstOrCreate(['key'=>'park']);

        TagKeyCache::forgetAll();

        $user = User::factory()->create(['id'=>7,'level'=>0]);

        /* 2. simulate 120 uploads ---------------------------------------- */
        $engine  = app(AchievementEngine::class);
        $unlocks = collect();
        $start   = CarbonImmutable::parse('2025-01-01 10:00:00');

        $payload = function (int $i) use ($oBottle,$oCan,$oBag,$oMask,$oCup,$oStraw,$oWrapper,
            $cFood,$cBev,$cMed,$cGen,$mGlass,$mPlastic,$bCoke,$bPepsi,$bHein,$tBeach,$tPark)
        {
            $objs = [
                [$oBottle->key=>1,$oCan->key=>1],
                [$oBag->key=>3,$oMask->key=>2],
                [$oCup->key=>5,$oStraw->key=>5],
                [$oWrapper->key=>7],
            ][$i % 4];

            $cat   = [$cBev->key, $cGen->key, $cFood->key, $cMed->key][$i%4];
            $mat   = $i%2===0 ? $mPlastic->key : $mGlass->key;
            $brand = [$bCoke->key,$bPepsi->key,$bHein->key][$i%3];
            $tag   = $i%2===0 ? $tBeach->key : $tPark->key;

            $qty = array_sum($objs);

            return [
                'tags' => [
                    $cat => array_map(fn($q)=>[
                        'quantity'=>$q,
                        'materials'=>[$mat=>$q],
                        'brands'=>[$brand=>$q],
                        'custom_tags'=>[$tag=>$q],
                    ], $objs),
                ],
                'totals'=>[
                    'total_objects'=>$qty,
                    'materials'=>$qty,
                    'brands'=>$qty,
                    'custom_tags'=>$qty,
                    'by_category'=>[$cat=>$qty],
                ],
            ];
        };

        for ($i=0;$i<120;$i++) {

            if ($i===60) {
                $bSprite = BrandList::firstOrCreate(['key'=>'sprite']);
                $mAlu    = Materials ::firstOrCreate(['key'=>'aluminium']);
                TagKeyCache::forgetAll();
                $engine = app(AchievementEngine::class);     // rebuild
            }

            $photo              = new Photo();
            $photo->setRelation('user',$user);
            $photo->user_id     = $user->id;
            $photo->created_at  = $start->addDays($i);
            $photo->summary     = $payload($i);

            RedisMetricsCollector::queue($photo);           // prod pipeline
            $unlocks = $unlocks->merge($engine->slugsToUnlock($photo));
        }

        /* 3. assertions --------------------------------------------------- */
        foreach ([1,42,69] as $m) {
            $this->assertTrue($unlocks->contains("uploads-{$m}"));
        }
        $this->assertTrue($unlocks->contains('objects-69'));

        foreach (['object','category','material','brand','customTag'] as $d) {
            $this->assertTrue(
                $unlocks->first(fn($s)=>str_starts_with($s,"$d-"))!==null,
                "no unlocks for $d"
            );
        }

        $this->assertTrue($unlocks->contains("brand-{$bSprite->id}-1"));
        $this->assertTrue($unlocks->contains("material-{$mAlu->id}-1"));

        $uniq   = $unlocks->unique();
        $xpDB   = Achievement::whereIn('slug',$uniq)->sum('xp');
        $xpCfg  = $uniq->sum(fn($s)=>config('achievements')[$s]['xp']??0);
        $this->assertEquals($xpDB,$xpCfg);
    }

    /** @test */
    public function xp_is_added_once_per_slug_even_on_repeated_photos(): void
    {
        config()->set('milestones',[]);
        config()->set('achievements',['spam'=>['xp'=>10,'when'=>'true']]);
        Achievement::firstOrCreate(['slug'=>'spam'],['xp'=>10]);

        $u     = User::factory()->create(['id'=>99]);
        $photo = (new Photo)->forceFill(['user_id'=>$u->id,'summary'=>['tags'=>[],'totals'=>[]]]);
        $photo->setRelation('user',$u);

        $e = app(AchievementEngine::class);
        RedisMetricsCollector::queue($photo);
        $e->generateAchievements($photo);
        $e->generateAchievements($photo);

        $this->assertEquals(10,(int)Redis::hGet('{u:99}:stats','xp'));
        $this->assertDatabaseCount('user_achievements',1);
    }

    /** @test */
    public function streak_and_country_achievements_coexist(): void
    {
//        config()->set('achievements',[
//            'streak-3'=>['xp'=>5,'when'=>'stats.currentStreak==3'],
//            'country-pioneer'=>['xp'=>5,'when'=>'isFirstInCountry()'],
//        ]);
//        Achievement::firstOrCreate(['slug'=>'streak-3'],['xp'=>5]);
//        Achievement::firstOrCreate(['slug'=>'country-pioneer'],['xp'=>5]);
        TagKeyCache::forgetAll();

        $u = User::factory()->create(['id'=>11]);

        $e = app(AchievementEngine::class);
        $photo = (new Photo)->forceFill([
            'user_id'=>$u->id,'country_id'=>55,'summary'=>['tags'=>[],'totals'=>[]]
        ])->setRelation('user',$u);

        // fabricate a 3-day streak
        $d2 = clone $photo; $d2->created_at=now()->subDay();
        $d3 = clone $photo; $d3->created_at=now()->subDays(2);
        RedisMetricsCollector::queue($d3);
        RedisMetricsCollector::queue($d2);
        RedisMetricsCollector::queue($photo);

        $slugs = $e->slugsToUnlock($photo);
        $this->assertTrue($slugs->contains('streak-3'));
        $this->assertTrue($slugs->contains('country-pioneer'));
    }
}
