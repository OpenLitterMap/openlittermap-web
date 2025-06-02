<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class UploadsChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $uploads = $counts['uploads'] ?? 0;
        if ($uploads <= 0) {
            return [];
        }

        $toUnlock = [];
        $unlockedMap = array_flip($alreadyUnlocked); // O(1) lookups

        // Pre-filter and sort achievements
        $uploadAchievements = $definitions
            ->filter(fn($a) => $a->type === 'uploads' && $a->tag_id === null && !isset($unlockedMap[$a->id]))
            ->sortBy('threshold')
            ->values();

        foreach ($uploadAchievements as $achievement) {
            if ($uploads >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            } else {
                // Early exit: sorted by threshold
                break;
            }
        }

        return $toUnlock;
    }
}
