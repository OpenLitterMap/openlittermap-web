<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;
use App\Services\Achievements\Tags\TagKeyCache;

abstract class OptimizedTagBasedChecker extends AchievementChecker
{
    /**
     * Get the dimension type for counts array
     */
    abstract protected function getCountsKey(): string;

    /**
     * Get the achievement type for dimension-wide achievements
     */
    abstract protected function getDimensionType(): string;

    /**
     * Get the achievement type for per-tag achievements
     */
    abstract protected function getTagType(): string;

    /**
     * Get the database table name
     */
    abstract protected function getTableName(): string;

    /**
     * Should we sum values for dimension-wide achievements?
     */
    protected function shouldSumValues(): bool
    {
        return true;
    }

    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $items = $counts[$this->getCountsKey()] ?? [];
        if (empty($items)) {
            return [];
        }

        $unlockedMap = array_flip($alreadyUnlocked);
        $toUnlock = [];

        // Calculate total for dimension-wide achievements
        $total = $this->shouldSumValues() ? array_sum($items) : count($items);

        // Build optimized index
        $achievementsByType = $this->buildOptimizedIndex($definitions, $unlockedMap);

        // Check dimension-wide achievements
        $dimensionKey = $this->getDimensionType() . ':null';
        if (isset($achievementsByType[$dimensionKey]) && $total > 0) {
            foreach ($achievementsByType[$dimensionKey] as $achievement) {
                if ($total >= $achievement['threshold']) {
                    $toUnlock[] = $achievement['id'];
                } else {
                    break; // Early exit
                }
            }
        }

        // Filter to only items with counts > 0
        $activeItems = array_filter($items, fn($count) => $count > 0);
        if (empty($activeItems)) {
            return $toUnlock;
        }

        // Batch get all tag IDs
        $tagIdMap = TagKeyCache::idsBatch($this->getTableName(), array_keys($activeItems));

        // Check per-tag achievements
        foreach ($activeItems as $itemKey => $count) {
            if (!isset($tagIdMap[$itemKey])) {
                continue;
            }

            $key = $this->getTagType() . ":{$tagIdMap[$itemKey]}";
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
        $types = [$this->getDimensionType(), $this->getTagType()];

        foreach ($definitions as $achievement) {
            // Skip if already unlocked or not our type
            if (isset($unlockedMap[$achievement->id]) || !in_array($achievement->type, $types)) {
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
