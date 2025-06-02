<?php

namespace App\Services\Achievements;

use App\Models\Achievements\Achievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AchievementRepository
{
    /**
     * Get all achievement definitions (cached for 24 hours)
     */
    public function getAchievementDefinitions(): Collection
    {
        return Cache::remember('achievements.all', 86400, function () {
            return DB::table('achievements')
                ->select('id', 'type', 'tag_id', 'threshold', 'metadata')
                ->get()
                ->map(function ($row) {
                    // Decode metadata if it's stored as JSON
                    if (is_string($row->metadata)) {
                        $row->metadata = json_decode($row->metadata, true);
                    }
                    return $row;
                });
        });
    }

    /**
     * Get user's unlocked achievement IDs (cached for 5 minutes)
     */
    public function getUnlockedAchievementIds(int $userId): array
    {
        return Cache::remember("user.achievements.$userId", 300, function () use ($userId) {
            return DB::table('user_achievements')
                ->where('user_id', $userId)
                ->pluck('achievement_id')
                ->toArray();
        });
    }

    /**
     * Unlock achievements for a user (with cache invalidation)
     */
    public function unlockAchievements($user, array $achievementIds): Collection
    {
        if (empty($achievementIds)) {
            return collect();
        }

        // Remove any duplicates from the input
        $achievementIds = array_unique($achievementIds);

        // Get already unlocked achievements to prevent duplicates
        $alreadyUnlocked = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->whereIn('achievement_id', $achievementIds)
            ->pluck('achievement_id')
            ->toArray();

        // Filter out already unlocked
        $toUnlock = array_diff($achievementIds, $alreadyUnlocked);

        if (empty($toUnlock)) {
            return collect();
        }

        // Get achievement details
        $achievements = Achievement::whereIn('id', $toUnlock)->get();

        if ($achievements->isEmpty()) {
            return collect();
        }

        // Prepare bulk insert data
        $insertData = [];
        foreach ($toUnlock as $achievementId) {
            $insertData[] = [
                'user_id' => $user->id,
                'achievement_id' => $achievementId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('user_achievements')->insertOrIgnore($insertData);
        }

        // Clear user's cache AFTER the insert
        Cache::forget("user.achievements.{$user->id}");

        // Return only the newly unlocked achievements
        return $achievements;
    }
}
