<?php

namespace App\Services\Achievements;

use App\Models\Achievements\Achievement;
use App\Models\Users\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AchievementQueryService
{
    /**
     * Get next achievable milestones for a user
     *
     * @param User $user
     * @param int $limit Maximum number of achievements to return
     * @param array $types Filter by specific types (optional)
     * @return Collection
     */
    public function getNextAchievableFor(User $user, int $limit = 10, array $types = []): Collection
    {
        $query = Achievement::notUnlockedBy($user);

        if (!empty($types)) {
            $query->whereIn('type', $types);
        }

        $achievements = $query->get();

        // Calculate progress for each and filter to those with progress > 0
        $withProgress = $achievements->map(function ($achievement) use ($user) {
            $progress = $achievement->getProgressFor($user);
            if ($progress > 0) {
                $achievement->current_progress = $progress;
                $achievement->progress_percentage = $achievement->getProgressPercentageFor($user);
                $achievement->remaining = $achievement->getRemainingFor($user);
                return $achievement;
            }
            return null;
        })->filter();

        // Sort by how close they are to completion (highest percentage first)
        return $withProgress
            ->sortByDesc('progress_percentage')
            ->take($limit)
            ->values();
    }

    /**
     * Get user's achievement summary
     */
    public function getUserSummary(User $user): array
    {
        $cacheKey = "user_achievement_summary:{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $totalAchievements = Achievement::count();
            $unlockedCount = $user->achievements()->count();
            $totalXp = (int) \Redis::hGet("u:{$user->id}:stats", 'xp') ?: 0;

            // Get achievements by type
            $byType = Achievement::select('type', \DB::raw('COUNT(*) as total'))
                ->groupBy('type')
                ->get()
                ->keyBy('type')
                ->map->total;

            $unlockedByType = $user->achievements()
                ->select('type', \DB::raw('COUNT(*) as unlocked'))
                ->groupBy('type')
                ->get()
                ->keyBy('type')
                ->map->unlocked;

            return [
                'total_achievements' => $totalAchievements,
                'unlocked_count' => $unlockedCount,
                'completion_percentage' => round(($unlockedCount / $totalAchievements) * 100, 1),
                'total_xp' => $totalXp,
                'level' => $user->level,
                'by_type' => $byType->map(function ($total, $type) use ($unlockedByType) {
                    $unlocked = $unlockedByType[$type] ?? 0;
                    return [
                        'total' => $total,
                        'unlocked' => $unlocked,
                        'percentage' => round(($unlocked / $total) * 100, 1),
                    ];
                }),
            ];
        });
    }

    /**
     * Get recent achievements for a user
     */
    public function getRecentlyUnlocked(User $user, int $limit = 10): Collection
    {
        return $user->achievements()
            ->with('pivot')
            ->orderBy('pivot.unlocked_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($achievement) {
                $achievement->unlocked_at = $achievement->pivot->unlocked_at;
                return $achievement;
            });
    }

    /**
     * Get achievements close to being unlocked (80%+ progress)
     */
    public function getAlmostUnlocked(User $user, int $limit = 5): Collection
    {
        $achievements = Achievement::notUnlockedBy($user)->get();

        $closeToUnlocking = $achievements->map(function ($achievement) use ($user) {
            $percentage = $achievement->getProgressPercentageFor($user);
            if ($percentage >= 80 && $percentage < 100) {
                $achievement->progress_percentage = $percentage;
                $achievement->remaining = $achievement->getRemainingFor($user);
                return $achievement;
            }
            return null;
        })->filter();

        return $closeToUnlocking
            ->sortByDesc('progress_percentage')
            ->take($limit)
            ->values();
    }

    /**
     * Get achievement statistics for a specific type
     */
    public function getTypeStatistics(User $user, string $type): array
    {
        $achievements = Achievement::ofType($type)->get();
        $unlocked = $user->achievements()->ofType($type)->count();

        $milestones = config('achievements.milestones');
        $nextMilestone = null;
        $currentProgress = 0;

        // Get current progress for this type
        $counts = \App\Services\Redis\RedisMetricsCollector::getUserCounts($user->id);
        $currentProgress = match($type) {
            'uploads' => $counts['uploads'] ?? 0,
            'objects' => array_sum($counts['objects'] ?? []),
            'categories' => count($counts['categories'] ?? []),
            'materials' => array_sum($counts['materials'] ?? []),
            'brands' => array_sum($counts['brands'] ?? []),
            default => 0,
        };

        // Find next milestone
        foreach ($milestones as $milestone) {
            if ($milestone > $currentProgress) {
                $nextMilestone = $milestone;
                break;
            }
        }

        return [
            'type' => $type,
            'total_achievements' => $achievements->count(),
            'unlocked' => $unlocked,
            'current_progress' => $currentProgress,
            'next_milestone' => $nextMilestone,
            'progress_to_next' => $nextMilestone
                ? round(($currentProgress / $nextMilestone) * 100, 1)
                : 100,
            'xp_earned' => $user->achievements()
                ->ofType($type)
                ->sum('xp'),
        ];
    }

    /**
     * Clear cache for a user
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("user_achievement_summary:{$userId}");
    }
}
