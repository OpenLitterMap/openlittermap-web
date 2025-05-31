<?php

namespace App\Services\Achievements;

use App\Models\Users\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AchievementRepository
{
    /**
     * Get all achievement definitions
     */
    public function getAchievementDefinitions(): Collection
    {
        return Cache::remember('achievements:all', 3600, function () {
            return DB::table('achievements')
                ->select(['id', 'type', 'tag_id', 'threshold', 'xp', 'metadata'])
                ->get()
                ->map(function ($achievement) {
                    return (object) [
                        'id' => $achievement->id,
                        'type' => $achievement->type,
                        'tag_id' => $achievement->tag_id,
                        'threshold' => $achievement->threshold,
                        'xp' => $achievement->xp,
                        'metadata' => json_decode($achievement->metadata ?? '{}', true),
                    ];
                });
        });
    }

    /**
     * Get user's unlocked achievement IDs
     */
    public function getUnlockedAchievementIds(int $userId): array
    {
        return Cache::remember(
            "user:$userId:achievements",
            300, // 5 minutes for active users
            fn() => DB::table('user_achievements')
                ->where('user_id', $userId)
                ->pluck('achievement_id')
                ->toArray()
        );
    }

    /**
     * Unlock achievements for a user
     */
    public function unlockAchievements(User $user, array $achievementIds): Collection
    {
        if (empty($achievementIds)) {
            return collect();
        }

        // Get achievement details
        $achievements = DB::table('achievements')
            ->whereIn('id', $achievementIds)
            ->get()
            ->map(function ($achievement) {
                $achievement->metadata = json_decode($achievement->metadata ?? '{}', true);
                return $achievement;
            });

        if ($achievements->isEmpty()) {
            return collect();
        }

        DB::transaction(function () use ($user, $achievements) {
            $data = $achievements->map(fn($a) => [
                'user_id' => $user->id,
                'achievement_id' => $a->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            DB::table('user_achievements')->insertOrIgnore($data);

            // Update XP in Redis
            $totalXp = $achievements->sum('xp');
            if ($totalXp > 0) {
                Redis::hIncrByFloat("{u:{$user->id}}:stats", 'xp', $totalXp);
            }
        });

        Cache::forget("user:{$user->id}:achievements");

        return $achievements;
    }
}
