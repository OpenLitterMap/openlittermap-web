<?php

namespace App\Services\Achievements;

use App\Models\Photo;

class UpdateAchievementsService
{
    public function generateAchievements(Photo $photo): void
    {
        $slugs = app(AchievementEngine::class)->slugsToUnlock($photo);

        app(AchievementEngine::class)->unlock($photo->user, $slugs);
    }
}
