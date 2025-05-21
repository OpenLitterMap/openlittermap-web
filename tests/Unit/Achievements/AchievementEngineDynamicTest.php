<?php
declare(strict_types=1);

namespace Tests\Unit\Achievements;

use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Models\Users\User;
use App\Models\Achievements\Achievement;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Mockery;
use Tests\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class AchievementEngineDynamicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // stub Redis so buildStats() can read hmget/get/hgetall
        $mockConn = Mockery::mock(RedisConnection::class);
        $mockConn->shouldReceive('hmget')->andReturn([0,0,0]);
        $mockConn->shouldReceive('get')->andReturn('0');
        $mockConn->shouldReceive('hgetall')->andReturn([]);
        $factory = Mockery::mock(RedisFactory::class)
            ->shouldReceive('connection')->andReturn($mockConn)
            ->getMock();
        $this->app->instance(RedisFactory::class, $factory);

        TagKeyCache::forgetAll();
    }

    /** @test */
    public function upload_milestones_trigger_at_counts()
    {
        // static definition only; dynamic builder will merge in nothing important
        $defs = [
            'uploads-3' => ['xp'=>0, 'when'=>'statCount("uploads") >= 3'],
        ];
        config()->set('achievements', $defs);
        Achievement::firstOrCreate(['slug'=>'uploads-3'], ['name'=>'u3','xp'=>0]);

        $user = User::factory()->create(['level'=>0]);
        $photo = new \App\Models\Photo();
        $photo->setRelation('user', $user);

        // 1) give it a timestamp so buildStats()->timeOfDay() works
        $photo->created_at = now();

        // 2) simulate 3 objects so statCount('uploads') returns 3
        $photo->summary = [
            'tags'   => [],
            'totals' => [
                'objects' => ['dummy'=>3],
                // you can omit other totals; buildStats only needs 'objects'
            ],
        ];

        // instantiate engine manually, passing a real EL instance
        $engine = new AchievementEngine(
            app(Cache::class),
            new ExpressionLanguage(),
            $defs
        );

        $slugs = $engine->slugsToUnlock($photo);

        $this->assertTrue($slugs->contains('uploads-3'));
    }
}
