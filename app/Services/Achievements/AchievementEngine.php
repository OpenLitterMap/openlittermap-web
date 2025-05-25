<?php
declare(strict_types=1);

namespace App\Services\Achievements;

use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\Strategies\AchievementStrategy;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Collection;

class AchievementEngine
{
    private array $strategies = [];

    public function __construct(
        private AchievementRepository $repository,
        private AchievementProgressTracker $tracker,
        private RedisMetricsCollector $metrics
    ) {}

    /**
     * Register an achievement strategy
     */
    public function registerStrategy(AchievementStrategy $strategy): self
    {
        $this->strategies[$strategy->getType()] = $strategy;
        return $this;
    }

    /**
     * Evaluate achievements for a photo (used in migration)
     */
    public function evaluate(Photo $photo): Collection
    {
        if (!$photo->user_id) {
            return collect();
        }

        $user = $photo->user ?? User::find($photo->user_id);
        if (!$user) {
            return collect();
        }

        $progressData = $this->calculateProgress($photo);
        $toUnlock = $this->tracker->checkProgress($user->id, $progressData);

        if ($toUnlock->isEmpty()) {
            return collect();
        }

        return $this->repository->unlockAchievements($user, $toUnlock);
    }

    /**
     * Calculate all progress data for a photo
     */
    private function calculateProgress(Photo $photo): array
    {
        $counts = $this->metrics->getUserCounts($photo->user_id);
        $progress = [];

        foreach ($this->strategies as $strategy) {
            $strategyProgress = $strategy->calculateProgress($photo, $counts);
            foreach ($strategyProgress as $key => $value) {
                $progress[$key] = $value;
            }
        }

        return $progress;
    }
}
