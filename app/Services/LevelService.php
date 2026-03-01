<?php

namespace App\Services;

/**
 * Calculate user level from XP using flat thresholds defined in config/levels.php.
 */
final class LevelService
{
    /**
     * Get full level info for a given XP total.
     *
     * @return array{level: int, title: string, xp: int, xp_into_level: int, xp_for_next: int, xp_remaining: int, progress_percent: int}
     */
    public static function getUserLevel(int $xp): array
    {
        $thresholds = config('levels.thresholds', [0 => 'Complete Noob']);

        // Ensure sorted ascending
        ksort($thresholds);

        $keys = array_keys($thresholds);
        $titles = array_values($thresholds);
        $count = count($keys);

        // Find the current level (highest threshold the user has reached)
        $levelIndex = 0;
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($xp >= $keys[$i]) {
                $levelIndex = $i;
                break;
            }
        }

        $currentThreshold = $keys[$levelIndex];
        $title = $titles[$levelIndex];
        $level = $levelIndex + 1;

        // Calculate progress toward next level
        $isMaxLevel = $levelIndex >= $count - 1;
        $nextThreshold = $isMaxLevel ? $currentThreshold : $keys[$levelIndex + 1];
        $xpForNext = $nextThreshold - $currentThreshold;
        $xpIntoLevel = $xp - $currentThreshold;
        $xpRemaining = $isMaxLevel ? 0 : $nextThreshold - $xp;
        $progressPercent = $isMaxLevel
            ? 100
            : ($xpForNext > 0 ? (int) round(($xpIntoLevel / $xpForNext) * 100) : 0);

        return [
            'level' => $level,
            'title' => $title,
            'xp' => $xp,
            'xp_into_level' => $xpIntoLevel,
            'xp_for_next' => $xpForNext,
            'xp_remaining' => $xpRemaining,
            'progress_percent' => min(100, $progressPercent),
        ];
    }
}
