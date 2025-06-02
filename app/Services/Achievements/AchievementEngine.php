<?php

namespace App\Services\Achievements;

use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AchievementEngine
{
    public function __construct(
        private AchievementRepository $repository,
        private iterable /* AchievementChecker[] */ $checkers,
    ) {}

    /**
     * Evaluate achievements for a photo
     */
    public function evaluate(int $userId): Collection
    {
        $user = User::find($userId);
        if (!$user) {
            return collect();
        }

        try {
            $counts = RedisMetricsCollector::getUserCounts($userId);
            $unlockedIds = $this->repository->getUnlockedAchievementIds($userId);
            $definitions = $this->repository->getAchievementDefinitions();

            $toUnlock = [];
            foreach ($this->checkers as $checker) {
                $newUnlocks = $checker->check($counts, $definitions, $unlockedIds);
                if (!empty($newUnlocks)) {
                    $toUnlock = array_unique(array_merge($toUnlock, $newUnlocks));
                }
            }

            $toUnlock = array_diff($toUnlock, $unlockedIds);

            return empty($toUnlock)
                ? collect()
                : $this->repository->unlockAchievements($user, $toUnlock);
        } catch (\Throwable $e) {
            Log::error('Achievement evaluation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return collect();
        }
    }
}
