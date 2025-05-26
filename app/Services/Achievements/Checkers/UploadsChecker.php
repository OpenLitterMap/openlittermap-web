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

        foreach ($definitions as $achievement) {
            // Skip if not uploads type or if it has a tag_id
            if ($achievement->type !== 'uploads' || $achievement->tag_id !== null) {
                continue;
            }

            // Skip if already unlocked
            if (in_array($achievement->id, $alreadyUnlocked)) {
                continue;
            }

            // Check if threshold is met
            if ($uploads >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            }
        }

        return $toUnlock;
    }
}
