<?php

namespace App\Services\Achievements\Strategies;

use App\Models\Photo;

class DimensionWideAchievementStrategy implements AchievementStrategy
{
    public function __construct(
        private string $type,
        private string $countKey,
        private bool $sumValues = false
    ) {}

    public function calculateProgress(Photo $photo, array $counts): array
    {
        $data = $counts[$this->countKey] ?? [];

        if ($this->sumValues) {
            // For objects, materials, brands - sum all values
            $value = array_sum($data);
        } else {
            // For categories - count unique keys
            $value = count($data);
        }

        return [
            $this->type => $value
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }
}
