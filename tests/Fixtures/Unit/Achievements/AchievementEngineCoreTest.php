<?php

namespace Tests\Fixtures\Unit\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\Tags\TagKeyCache;
use Tests\TestCase;

class AchievementEngineCoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TagKeyCache::forgetAll();
    }

    /** @test */
    public function time_of_day_achievement_fires()
    {
        config()->set('achievements', ['night-owl' => ['xp' => 1, 'when' => 'timeOfDay()=="night"']]);
        Achievement::firstOrCreate(['slug' => 'night-owl'], ['name' => 'owl', 'xp' => 1]);

        $user = User::factory()->create(['level' => 0]);
        $photo = new \App\Models\Photo();
        $photo->setRelation('user', $user);
        $photo->created_at = now()->setHour(2);
        $photo->summary = ['tags' => [], 'totals' => []];

        $engine = app(AchievementEngine::class);
        $slugs = $engine->slugsToUnlock($photo);

        $this->assertTrue($slugs->contains('night-owl'));
    }

    /** @test */
    public function weekend_achievement_fires_on_saturday()
    {
        config()->set('achievements', ['weekend' => ['xp' => 1, 'when' => 'isWeekend()']]);
        Achievement::firstOrCreate(['slug' => 'weekend'], ['name' => 'week', 'xp' => 1]);

        $user = User::factory()->create(['level' => 0]);
        $photo = new \App\Models\Photo();
        $photo->setRelation('user', $user);
        $photo->created_at = now()->next(\Carbon\Carbon::SATURDAY);
        $photo->summary = ['tags' => [], 'totals' => []];

        $engine = app(AchievementEngine::class);
        $slugs = $engine->slugsToUnlock($photo);

        $this->assertTrue($slugs->contains('weekend'));
    }
}
