<?php

namespace App\Services\Achievements\Checkers;

use Illuminate\Support\Collection;

class MaterialsChecker extends AchievementChecker
{
    public function check(array $counts, Collection $definitions, array $alreadyUnlocked): array
    {
        $materials = $counts['materials'] ?? [];
        if (empty($materials)) {
            return [];
        }

        $toUnlock = [];
        $totalMaterials = array_sum($materials);

        // Check dimension-wide achievements
        foreach ($definitions as $achievement) {
            if ($achievement->type === 'materials' &&
                $achievement->tag_id === null &&
                !in_array($achievement->id, $alreadyUnlocked) &&
                $totalMaterials >= $achievement->threshold) {
                $toUnlock[] = $achievement->id;
            }
        }

        // Check per-material achievements
        foreach ($materials as $materialKey => $count) {
            if ($count <= 0) continue;

            $tagId = $this->getTagId('materials', $materialKey);
            if (!$tagId) continue;

            foreach ($definitions as $achievement) {
                if ($achievement->type === 'material' &&
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
