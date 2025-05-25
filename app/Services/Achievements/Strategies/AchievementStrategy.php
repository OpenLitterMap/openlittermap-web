<?php

namespace App\Services\Achievements\Strategies;

use App\Models\Photo;

interface AchievementStrategy
{
    public function calculateProgress(Photo $photo, array $counts): array;
    public function getType(): string;
}
