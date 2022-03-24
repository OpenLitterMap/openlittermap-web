<?php

namespace App\Actions;

class CalculateTagsDifferenceAction
{
    /**
     * Calculates the diff between the existing user tags
     * and the tags provided by an admin
     *
     * todo refactor into returning a Value Object
     */
    public function run(array $oldTags, array $newTags, array $oldCustomTags, array $newCustomTags): array
    {
        $tagsDiff = $this->tagsDiff($oldTags, $newTags);
        $customTagsDiff = $this->customTagsDiff($oldCustomTags, $newCustomTags);

        return [
            'added' => ['tags' => $tagsDiff['added'], 'customTags' => $customTagsDiff['added']],
            'removed' => ['tags' => $tagsDiff['removed'], 'customTags' => $customTagsDiff['removed']],
            'removedUserXp' => $tagsDiff['removedUserXp'] + $customTagsDiff['removedUserXp'],
            'rewardedAdminXp' => $tagsDiff['rewardedAdminXp'] + $customTagsDiff['rewardedAdminXp']
        ];
    }

    private function tagsDiff(array $oldTags, array $newTags): array
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

    private function customTagsDiff(array $oldTags, array $newTags): array
    {
        $addedTags = array_diff($newTags, $oldTags);
        $removedTags = array_diff($oldTags, $newTags);

        $removedUserXp = count($removedTags);
        $rewardedAdminXp = count($addedTags) + count($removedTags);

        return [
            'added' => $addedTags,
            'removed' => $removedTags,
            'removedUserXp' => $removedUserXp,
            'rewardedAdminXp' => $rewardedAdminXp
        ];
    }
}
