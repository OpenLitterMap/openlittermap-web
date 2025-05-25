<?php

namespace App\Services\Achievements\Strategies;

use App\Models\Photo;
use App\Services\Achievements\Tags\TagKeyCache;

class TagBasedAchievementStrategy implements AchievementStrategy
{
    public function __construct(
        private string $type,
        private string $countKey
    ) {}

    public function calculateProgress(Photo $photo, array $counts): array
    {
        $progress = [];
        $data = $counts[$this->countKey] ?? [];

        foreach ($data as $tagKey => $value) {
            $tagId = TagKeyCache::idFor($this->type, (string)$tagKey);
            if ($tagId && $value > 0) {
                $progress["{$this->type}-{$tagId}"] = $value;
            }
        }

        return $progress;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
