<?php

namespace App\Services\Achievements;

use Illuminate\Support\Collection;

class AchievementProgressTracker
{
    private Collection $definitions;
    private array $unlockedCache = [];

    public function __construct(
        private AchievementRepository $repository
    ) {
        $this->definitions = $this->repository->getAchievementDefinitions();
    }

    /**
     * Check which achievements should be unlocked based on progress
     */
    public function checkProgress(int $userId, array $progressData): Collection
    {
        $unlocked = $this->getUnlockedForUser($userId);
        $toUnlock = collect();

        foreach ($progressData as $key => $value) {
            // For each progress key, check all matching thresholds
            $matches = $this->definitions->filter(function ($achievement) use ($key, $value) {
                $achievementKey = $achievement->type;
                if ($achievement->tag_id) {
                    $achievementKey .= "-{$achievement->tag_id}";
                }

                return str_starts_with($key, $achievementKey) && $value >= $achievement->threshold;
            });

            foreach ($matches as $achievement) {
                if (!in_array($achievement->id, $unlocked)) {
                    $toUnlock->push($achievement->id);
                }
            }
        }

        return $toUnlock->unique();
    }

    private function getUnlockedForUser(int $userId): array
    {
        if (!isset($this->unlockedCache[$userId])) {
            $this->unlockedCache[$userId] = $this->repository->getUnlockedAchievementIds($userId);
        }
        return $this->unlockedCache[$userId];
    }

    public function clearUserCache(int $userId): void
    {
        unset($this->unlockedCache[$userId]);
    }
}
