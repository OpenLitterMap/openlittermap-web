<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class ObjectsChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $objects = $counts['objects'] ?? [];
        if (empty($objects)) {
            return [];
        }

        $toUnlock = [];
        $totalObjects = array_sum($objects);

        // Check dimension-wide achievements (type = 'objects', no tag_id)
        foreach ($definitions as $achievement) {
            if ($achievement->type === 'objects' &&
                $achievement->tag_id === null &&
                !in_array($achievement->id, $alreadyUnlocked) &&
                $totalObjects >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            }
        }

        // Check per-object achievements (type = 'object', with tag_id)
        foreach ($objects as $objectKey => $count) {
            if ($count <= 0) continue;

            $tagId = $this->getTagId('litter_objects', $objectKey);
            if (!$tagId) continue;

            foreach ($definitions as $achievement) {
                if ($achievement->type === 'object' &&
                    $achievement->tag_id == $tagId &&
                    !in_array($achievement->id, $alreadyUnlocked) &&
                    $count >= $achievement->threshold) {
                    $toUnlock[] = $achievement->id;
                }
            }
        }

        return $toUnlock;
    }
}
