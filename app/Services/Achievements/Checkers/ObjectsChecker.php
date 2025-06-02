<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;
use App\Services\Achievements\Tags\TagKeyCache;

class ObjectsChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $objects = $counts['objects'] ?? [];
        if (empty($objects)) {
            return [];
        }

        // Convert to hash map for O(1) lookups instead of O(n) in_array()
        $unlockedMap = array_flip($alreadyUnlocked);
        $toUnlock = [];
        $totalObjects = array_sum($objects);

        // Create optimized lookup structure with only what we need
        $achievementsByType = $this->buildOptimizedIndex($definitions, $unlockedMap);

        // Early exit if user has unlocked everything possible
        if (empty($achievementsByType)) {
            return [];
        }

        // Check dimension-wide achievements (type = 'objects', no tag_id)
        if (isset($achievementsByType['objects:null'])) {
            foreach ($achievementsByType['objects:null'] as $achievement) {
                if ($totalObjects >= $achievement['threshold']) {
                    $toUnlock[] = $achievement['id'];
                } else {
                    // Early exit: achievements are sorted by threshold
                    break;
                }
            }
        }

        // Filter to only objects with counts > 0
        $activeObjects = array_filter($objects, fn($count) => $count > 0);
        if (empty($activeObjects)) {
            return $toUnlock;
        }

        // Batch get all tag IDs in one call
        $tagIdMap = TagKeyCache::getTagIdsBatch('litter_objects', array_keys($activeObjects));

        // Check per-object achievements
        foreach ($activeObjects as $objectKey => $count) {
            if (!isset($tagIdMap[$objectKey])) {
                continue;
            }

            $key = "object:{$tagIdMap[$objectKey]}";
            if (!isset($achievementsByType[$key])) {
                continue;
            }

            // Check achievements for this specific object
            foreach ($achievementsByType[$key] as $achievement) {
                if ($count >= $achievement['threshold']) {
                    $toUnlock[] = $achievement['id'];
                } else {
                    // Early exit: sorted by threshold, no point checking further
                    break;
                }
            }
        }

        return $toUnlock;
    }

    /**
     * Build optimized index with only unlockable achievements
     */
    private function buildOptimizedIndex(Collection $definitions, array $unlockedMap): array
    {
        $index = [];

        foreach ($definitions as $achievement) {
            // Skip if already unlocked
            if (isset($unlockedMap[$achievement->id])) {
                continue;
            }

            // Only index object-related achievements
            if (!in_array($achievement->type, ['objects', 'object'])) {
                continue;
            }

            $key = $achievement->type . ':' . ($achievement->tag_id ?? 'null');

            // Store only what we need for checking
            $index[$key][] = [
                'id' => $achievement->id,
                'threshold' => $achievement->threshold,
            ];
        }

        // Sort each group by threshold for early exit optimization
        foreach ($index as &$group) {
            usort($group, fn($a, $b) => $a['threshold'] <=> $b['threshold']);
        }

        return $index;
    }
}
