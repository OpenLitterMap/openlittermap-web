<?php

namespace App\Services\Achievements;

use App\Models\Users\User;
use App\Services\Redis\RedisMetricsCollector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
            $counts = RedisMetricsCollector::getUserMetrics($userId);
            $already = $this->repository->getUnlockedAchievementIds($userId);
            $definitions = $this->repository->getAchievementDefinitions();

            /* ---------------------------------------------------------
             | 1) Let the specialised checkers do their normal work
             * --------------------------------------------------------- */
            $toUnlock = [];
            foreach ($this->checkers as $checker) {
                $toUnlock = array_merge(
                    $toUnlock,
                    $checker->check($counts, $definitions, $already)
                );
            }

            /* ---------------------------------------------------------
             | 2) Fallback “greater-OR-equal” pass
             |    (catches off-by-one defects inside checkers)
             * --------------------------------------------------------- */
            foreach ($definitions as $def) {
                if (in_array($def->id, $already, true) || in_array($def->id, $toUnlock, true)) {
                    continue;
                }

                if ($this->meetsThreshold($counts, $def)) {
                    $toUnlock[] = $def->id;
                }
            }

            $toUnlock = array_values(
                array_diff(
                    array_map('intval', $toUnlock),
                    $already
                )
            );
            if ($toUnlock === []) {
                return collect();
            }

            /* ---------------------------------------------------------
             | 3) Persist + invalidate cache
             * --------------------------------------------------------- */
            $unlocked = $this->repository->unlockAchievements($user, $toUnlock);
            Cache::forget("achievements:unlocked:{$userId}");

            return $unlocked;

        } catch (\Throwable $e) {
            Log::error('Achievement evaluation failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return collect();
        }
    }

    private function meetsThreshold(array $c, object $d): bool
    {
        return match ($d->type) {
            /* simple counters --------------------------------------- */
            'uploads' => $c['uploads'] >= $d->threshold,
            'streak'  => $c['streak']  >= $d->threshold,

            'categories', 'objects', 'materials', 'brands', 'custom_tags' =>
            $d->tag_id
                ? (($c[$d->type][(string)$d->tag_id] ?? 0) >= $d->threshold)  // Cast to string
                : (array_sum($c[$d->type] ?? []) >= $d->threshold),           // Handle missing key

            'category', 'object', 'material', 'brand', 'custom_tag' =>
                ($c[$d->type . 's'][(string)$d->tag_id] ?? 0) >= $d->threshold,   // Cast to string


            /* unknown type ------------------------------------------ */
            default => false,
        };
    }
}
