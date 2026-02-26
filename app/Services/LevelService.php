<?php

namespace App\Services;

/**
 * Calculate user level from XP using a geometric progression.
 *
 * Formula: Level N requires round(base_xp * growth_factor^(N-1)) XP.
 * Cumulative XP for level N = sum of XP required for levels 1..N.
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
        $baseXp = (int) config('levels.base_xp', 100);
        $growth = (float) config('levels.growth_factor', 1.5);
        $maxLevel = (int) config('levels.max_level', 50);
        $titles = config('levels.titles', []);

        $cumulativeXp = 0;
        $level = 1;

        for ($n = 1; $n <= $maxLevel; $n++) {
            $xpForThisLevel = (int) round($baseXp * pow($growth, $n - 1));
            $nextCumulative = $cumulativeXp + $xpForThisLevel;

            if ($xp < $nextCumulative) {
                // User is at level N (hasn't completed it yet)
                $xpIntoLevel = $xp - $cumulativeXp;
                $xpRemaining = $nextCumulative - $xp;
                $progressPercent = $xpForThisLevel > 0
                    ? (int) round(($xpIntoLevel / $xpForThisLevel) * 100)
                    : 0;

                return [
                    'level' => $n,
                    'title' => $titles[$n] ?? 'Beginner',
                    'xp' => $xp,
                    'xp_into_level' => $xpIntoLevel,
                    'xp_for_next' => $xpForThisLevel,
                    'xp_remaining' => $xpRemaining,
                    'progress_percent' => $progressPercent,
                ];
            }

            $cumulativeXp = $nextCumulative;
            $level = $n;
        }

        // User has reached or exceeded max level
        return [
            'level' => $maxLevel,
            'title' => $titles[$maxLevel] ?? 'Founder',
            'xp' => $xp,
            'xp_into_level' => $xp - $cumulativeXp + (int) round($baseXp * pow($growth, $maxLevel - 1)),
            'xp_for_next' => 0,
            'xp_remaining' => 0,
            'progress_percent' => 100,
        ];
    }
}
