<?php
declare(strict_types=1);

namespace Tests\Unit\Achievements;

use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Models\Users\User;
use App\Models\Photo;
use App\Models\Achievements\Achievement;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class AchievementEngineStreakTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            RedisFactory::class,
            $this->app->make(RedisManager::class)
        );

        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_unlocks_both_streak_and_country_pioneer()
    {
        // 1) Prepare your config & achievements
        config()->set('achievements', [
            'streak-3'        => ['xp' => 5, 'when' => 'stats.currentStreak == 3'],
            'country-pioneer' => ['xp' => 5, 'when' => 'isFirstInCountry()'],
        ]);
        Achievement::firstOrCreate(['slug'=>'streak-3'],        ['name'=>'streak-3','xp'=>5]);
        Achievement::firstOrCreate(['slug'=>'country-pioneer'], ['name'=>'country-…','xp'=>5]);

        // 2) Fake out TagKeyCache (if you rely on any tags)
        TagKeyCache::forgetAll();

        // 3) Build a dummy user & photo
        $user = User::factory()->create(['id'=>11, 'level'=>0]);
        $photo = new Photo();
        $photo->setRelation('user', $user);
        $photo->user_id    = 11;
        $photo->country_id = 55;
        $photo->created_at = now();
        $photo->summary    = ['tags' => [], 'totals' => []];

        // 4) MOCK the Redis connection:
        $mockConn = Mockery::mock(RedisConnection::class);
        $mockConn
            ->shouldReceive('hmget')
            ->with('{u:11}:stats', ['xp','uploads','streak'])
            ->andReturn([0, 0, 3]);

        $mockConn
            // then it does get('{u:11}:streak')
            ->shouldReceive('get')
            ->with("{u:11}:streak")
            ->andReturn('3');

        $mockConn
            // and isFirstInCountry() does setnx("first:country:55", 11)
            ->shouldReceive('setnx')
            ->with("first:country:55", 11)
            ->andReturn(true);

        $mockConn
            ->shouldReceive('hgetall')
            ->with(sprintf('{u:%d}:t', 11))
            ->andReturn([]);

        $mockConn
            ->shouldReceive('flushdb')
            ->andReturnNull();

        // 5) MOCK the RedisFactory so that ->connection() returns our fake
        $mockFactory = Mockery::mock(RedisFactory::class);
        $mockFactory
            ->shouldReceive('connection')
            ->andReturn($mockConn);

        // 6) Bind that fake into the container
        $this->app->instance(RedisFactory::class, $mockFactory);

        // ** STUB THE FACADE **
        Redis::shouldReceive('connection')
            ->andReturn($mockConn);

        // 7) Finally, resolve & exercise the engine
        $engine = $this->app->make(AchievementEngine::class);
        $slugs  = $engine->slugsToUnlock($photo);

        // 8) Assert you got both of them back
        $this->assertTrue($slugs->contains('streak-3'));
        $this->assertTrue($slugs->contains('country-pioneer'));
    }
}
