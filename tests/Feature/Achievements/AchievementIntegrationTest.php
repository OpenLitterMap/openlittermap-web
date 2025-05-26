<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementProgressTracker;
use App\Services\Achievements\AchievementRepository;
use App\Services\Achievements\Strategies\UploadsAchievementStrategy;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function basic_achievement_flow_works(): void
    {
        // Create achievement
        DB::table('achievements')->insert([
            'type' => 'uploads',
            'tag_id' => null,
            'threshold' => 1,
            'xp' => 10,
            'metadata' => json_encode(['name' => 'First Upload']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create user
        $user = User::factory()->create();

        // Set up engine with just uploads strategy
        $repository = new AchievementRepository();
        $tracker = new AchievementProgressTracker($repository);
        $engine = new AchievementEngine(
            $repository,
            $tracker,
            app(RedisMetricsCollector::class)
        );

        $engine->registerStrategy(new UploadsAchievementStrategy());

        // Create mock photo
        $photo = new \App\Models\Photo();
        $photo->user_id = $user->id;
        $photo->summary = ['tags' => []];
        $photo->created_at = now();
        $photo->save();

        // Simulate Redis data
        \Redis::hSet("u:{$user->id}:stats", 'uploads', 1);

        // Evaluate
        $unlocked = $engine->evaluate($photo);

        // Assertions
        $this->assertCount(1, $unlocked);
        $this->assertEquals('uploads', $unlocked->first()->type);
        $this->assertEquals(1, $unlocked->first()->threshold);

        // Check database
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $unlocked->first()->id,
        ]);
    }
}
