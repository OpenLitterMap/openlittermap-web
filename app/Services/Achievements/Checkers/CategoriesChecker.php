<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;
use App\Services\Achievements\Tags\TagKeyCache;

class CategoriesChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $categories = $counts['categories'] ?? [];
        if (empty($categories)) {
            return [];
        }

        $unlockedMap = array_flip($alreadyUnlocked);
        $toUnlock = [];

        // Count of unique categories (for dimension-wide achievements)
        $uniqueCategoryCount = count($categories);

        // Build optimized index
        $achievementsByType = $this->buildOptimizedIndex($definitions, $unlockedMap);

        // Check dimension-wide achievements (based on unique category count)
        if (isset($achievementsByType['categories:null'])) {
            foreach ($achievementsByType['categories:null'] as $achievement) {
                if ($uniqueCategoryCount >= $achievement['threshold']) {
                    $toUnlock[] = $achievement['id'];
                } else {
                    break; // Early exit
                }
            }
        }

        // Filter to only categories with counts > 0
        $activeCategories = array_filter($categories, fn($count) => $count > 0);
        if (empty($activeCategories)) {
            return $toUnlock;
        }

        // Batch get all tag IDs
        $tagIdMap = TagKeyCache::idsBatch('categories', array_keys($activeCategories));

        // Check per-category achievements
        foreach ($activeCategories as $categoryKey => $count) {
            if (!isset($tagIdMap[$categoryKey])) {
                continue;
            }

            $key = "category:{$tagIdMap[$categoryKey]}";
            if (!isset($achievementsByType[$key])) {
                continue;
            }

            foreach ($achievementsByType[$key] as $achievement) {
                if ($count >= $achievement['threshold']) {
                    $toUnlock[] = $achievement['id'];
                } else {
                    break; // Early exit
                }
            }
        }

        return $toUnlock;
    }

    private function buildOptimizedIndex(Collection $definitions, array $unlockedMap): array
    {
        $index = [];

        foreach ($definitions as $achievement) {
            // Skip if already unlocked or not category-related
            if (isset($unlockedMap[$achievement->id]) ||
                !in_array($achievement->type, ['categories', 'category'])) {
                continue;
            }

            $key = $achievement->type . ':' . ($achievement->tag_id ?? 'null');
            $index[$key][] = [
                'id' => $achievement->id,
                'threshold' => $achievement->threshold,
            ];
        }

        // Sort each group by threshold
        foreach ($index as &$group) {
            usort($group, fn($a, $b) => $a['threshold'] <=> $b['threshold']);
        }

        return $index;
    }
}
