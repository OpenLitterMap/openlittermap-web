<?php

namespace App\Actions;

class CalculateTagsDifferenceAction
{
    public function run(array $oldTags, array $newTags): array
    {
        $addedTags = array_diff_assoc_recursive($newTags, $oldTags);
        $removedTags = array_diff_assoc_recursive($oldTags, $newTags);
        $removedUserXp = 0;
        $rewardedAdminXp = 0;

        foreach ($addedTags as $category => $tags) {
            foreach ($tags as $tag => $adminCount) {
                // This means we have changed a tag count
                if (isset($removedTags[$category][$tag])) {
                    $userCount = $removedTags[$category][$tag];
                    if ($adminCount < $userCount) {
                        $removedUserXp += $userCount - $adminCount;
                    }
                }

                $rewardedAdminXp += 1;
            }
        }

        foreach ($removedTags as $category => $tags) {
            foreach ($tags as $tag => $userCount) {
                // This means we have deleted a tag entirely
                if (!isset($addedTags[$category][$tag])) {
                    $removedUserXp += $userCount;
                    $rewardedAdminXp += 1;
                }
            }
        }

        return [
            'added' => $addedTags,
            'removed' => $removedTags,
            'removedUserXp' => $removedUserXp,
            'rewardedAdminXp' => $rewardedAdminXp
        ];
    }
}
