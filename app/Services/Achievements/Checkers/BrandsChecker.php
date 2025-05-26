<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class BrandsChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $brands = $counts['brands'] ?? [];
        if (empty($brands)) {
            return [];
        }

        $toUnlock = [];
        $totalBrands = array_sum($brands);

        // Check dimension-wide achievements
        foreach ($definitions as $achievement) {
            if ($achievement->type === 'brands' &&
                $achievement->tag_id === null &&
                !in_array($achievement->id, $alreadyUnlocked) &&
                $totalBrands >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            }
        }

        // Check per-brand achievements
        foreach ($brands as $brandKey => $count) {
            if ($count <= 0) continue;

            $tagId = $this->getTagId('brandslist', $brandKey);
            if (!$tagId) continue;

            foreach ($definitions as $achievement) {
                if ($achievement->type === 'brand' &&
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
