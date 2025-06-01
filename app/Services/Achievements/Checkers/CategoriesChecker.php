<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class CategoriesChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $categories = $counts['categories'] ?? [];
        if (empty($categories)) {
            return [];
        }

        $toUnlock = [];
        $totalCategories = count($categories);

        // Check dimension-wide achievements
        foreach ($definitions as $achievement) {
            if ($achievement->type === 'categories' &&
                $achievement->tag_id === null &&
                !in_array($achievement->id, $alreadyUnlocked) &&
                $totalCategories >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            }
        }

        // Check per-category achievements
        foreach ($categories as $categoryKey => $count) {
            if ($count <= 0) continue;

            $tagId = $this->getTagId('categories', $categoryKey);
            if (!$tagId) continue;

            foreach ($definitions as $achievement) {
                if ($achievement->type === 'category' &&
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
