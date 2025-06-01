<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class CustomTagChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $customTags = $counts['custom_tags'] ?? [];
        if (empty($customTags)) {
            return [];
        }

        $toUnlock = [];

        foreach ($customTags as $tagKey => $count) {
            if ($count <= 0) continue;

            $tagId = $this->getTagId('custom_tags_new', $tagKey);
            if (!$tagId) continue;

            foreach ($definitions as $achievement) {
                if ($achievement->type === 'customTag' &&
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
