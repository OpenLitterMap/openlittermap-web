<?php

namespace App\Services\Achievements\Strategies;

use App\Models\Photo;

class UploadsAchievementStrategy implements AchievementStrategy
{
    public function calculateProgress(Photo $photo, array $counts): array
    {
        return [
            'uploads' => $counts['uploads'] ?? 0
        ];
    }

    public function getType(): string
    {
        return 'uploads';
    }
}
