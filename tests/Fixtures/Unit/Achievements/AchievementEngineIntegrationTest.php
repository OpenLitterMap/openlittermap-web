<?php

namespace Tests\Fixtures\Unit\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AchievementEngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        config()->set('achievements', [
            'spam' => ['xp'=>10,'when'=>'true'],
        ]);
        Achievement::firstOrCreate(['slug'=>'spam'], ['name'=>'s','xp'=>10]);
    }

    /** @test */
    public function process_gives_xp_and_records_pivot()
    {
        $user = User::factory()->create(['level' => 0]);
        $photo = Photo::factory()->make();
        $photo->user()->associate($user);
        $photo->summary = ['tags'=>[], 'totals'=>[]];
        $photo->created_at = now();

        RedisMetricsCollector::queue($photo);
        app(AchievementEngine::class)->generateAchievements($photo);

        $this->assertDatabaseHas('user_achievements', [
            'user_id'=>$user->id,
            'achievement_id'=>Achievement::where('slug', 'spam')->first()->id
        ]);

        $this->assertEquals(10, Redis::hget("{u:{$user->id}}:stats", 'xp'));
    }
}
