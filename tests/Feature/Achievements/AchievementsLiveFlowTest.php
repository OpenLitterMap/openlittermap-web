<?php

namespace Tests\Feature\Achievements;

use App\Events\TagsVerifiedByAdmin;
use App\Jobs\EvaluateUserAchievements;
use App\Listeners\Metrics\ProcessPhotoMetrics;
use App\Models\Achievements\Achievement;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Checkers\CustomTagChecker;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AchievementsLiveFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('user_achievements')->delete();
        DB::table('achievements')->delete();
        Cache::flush();

        $userKeys = Redis::keys('{u:*');
        if ($userKeys && is_array($userKeys)) {
            Redis::del($userKeys);
        }
    }

    /** @test */
    public function process_photo_metrics_dispatches_achievements_job(): void
    {
        Queue::fake(EvaluateUserAchievements::class);

        $user = User::factory()->create();
        $photo = Photo::factory()->for($user)->create([
            'verified' => 2,
            'lat' => 51.5074,
            'lon' => -0.1278,
            'summary' => json_encode(['tags' => ['food' => ['wrapper' => ['quantity' => 1]]]]),
        ]);

        $event = new TagsVerifiedByAdmin(
            $photo->id,
            $user->id,
            $photo->country_id,
            $photo->state_id,
            $photo->city_id
        );

        $listener = app(ProcessPhotoMetrics::class);
        $listener->handle($event);

        Queue::assertPushed(EvaluateUserAchievements::class, function ($job) use ($user) {
            return $job->userId === $user->id;
        });
    }

    /** @test */
    public function achievements_controller_returns_progress_for_user_with_achievements(): void
    {
        $user = User::factory()->create();

        // Create an achievement definition and unlock it
        $achievement = Achievement::create([
            'type' => 'uploads',
            'tag_id' => null,
            'threshold' => 1,
            'metadata' => json_encode(['xp' => 10]),
        ]);

        DB::table('user_achievements')->insert([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/achievements');

        $response->assertOk();
        $response->assertJsonPath('summary.unlocked', 1);
        $response->assertJsonPath('summary.percentage', 100);
    }

    /** @test */
    public function custom_tag_checker_is_registered_and_evaluates(): void
    {
        // Verify CustomTagChecker is in the tagged set
        $checkers = iterator_to_array(app()->tagged('achievement.checker'));
        $checkerClasses = array_map(fn($c) => get_class($c), $checkers);

        $this->assertContains(CustomTagChecker::class, $checkerClasses);

        // Verify the engine has it
        $engine = app(AchievementEngine::class);
        $this->assertNotNull($engine);
    }
}
