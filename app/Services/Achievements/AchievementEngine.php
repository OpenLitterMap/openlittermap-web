<?php

declare(strict_types=1);

namespace App\Services\Achievements;

use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\Checkers\AchievementChecker;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AchievementEngine
{
    private array $checkers = [];

    public function __construct(
        private AchievementRepository $repository
    ) {
        $this->registerDefaultCheckers();
    }

    /**
     * Register an achievement checker
     */
    public function registerChecker(AchievementChecker $checker): self
    {
        $this->checkers[] = $checker;
        return $this;
    }

    /**
     * Evaluate achievements for a photo
     */
    public function evaluate(Photo $photo): Collection
    {
        if (!$photo->user_id) {
            return collect();
        }

        try {
            $user = $photo->user ?? User::find($photo->user_id);
            if (!$user) {
                return collect();
            }

            // Get current state
            $counts = RedisMetricsCollector::getUserCounts($user->id);
            $unlocked = $this->repository->getUnlockedAchievementIds($user->id);
            $definitions = $this->repository->getAchievementDefinitions();

            // Check what should be unlocked
            $toUnlock = [];
            foreach ($this->checkers as $checker) {
                $eligible = $checker->check($counts, $definitions, $unlocked);
                $toUnlock = array_merge($toUnlock, $eligible);
            }

            if (empty($toUnlock)) {
                return collect();
            }

            // Unlock and return
            return $this->repository->unlockAchievements($user, $toUnlock);

        } catch (\Throwable $e) {
            Log::error('Achievement evaluation failed', [
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Register default checkers
     */
    private function registerDefaultCheckers(): void
    {
        $this->registerChecker(new Checkers\UploadsChecker());
        $this->registerChecker(new Checkers\ObjectsChecker());
        $this->registerChecker(new Checkers\CategoriesChecker());
        $this->registerChecker(new Checkers\MaterialsChecker());
        $this->registerChecker(new Checkers\BrandsChecker());
    }
}
