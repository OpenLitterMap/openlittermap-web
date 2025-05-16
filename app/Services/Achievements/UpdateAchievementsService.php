<?php

namespace App\Services\Achievements;

use App\Models\Photo;

class UpdateAchievementsService
{
    public function generateAchievements(Photo $photo): void
    {
        app(AchievementEngine::class)->process($photo);
    }
}
