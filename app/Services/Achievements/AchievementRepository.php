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
     * Get all achievement definitions keyed by type-tagId-threshold
     */
    public function getAchievementDefinitions(): Collection
    {
        return Cache::remember('achievement:definitions', 86400, function () {
            return DB::table('achievements')
                ->get()
                ->keyBy(function ($achievement) {
                    $key = $achievement->type;
                    if ($achievement->tag_id) {
                        $key .= "-{$achievement->tag_id}";
                    }
                    $key .= "-{$achievement->threshold}";
                    return $key;
                });
        });
    }

    /**
     * Get user's unlocked achievement IDs
     */
    public function getUnlockedAchievementIds(int $userId): array
    {
        $key = "user:{$userId}:achievements";
        $cached = Redis::sMembers($key);

        if (!empty($cached)) {
            return array_map('intval', $cached);
        }

        // Load from database and cache
        $ids = DB::table('user_achievements')
            ->where('user_id', $userId)
            ->pluck('achievement_id')
            ->toArray();

        if (!empty($ids)) {
            \Redis::sAdd($key, ...$ids);
            \Redis::expire($key, 86400);
        }

        return $ids;
    }

    /**
     * Unlock achievements for a user
     */
    public function unlockAchievements(User $user, Collection $achievementIds): Collection
    {
        if ($achievementIds->isEmpty()) {
            return collect();
        }

        $achievements = DB::table('achievements')
            ->whereIn('id', $achievementIds)
            ->get();

        DB::transaction(function () use ($user, $achievements) {
            // Insert pivot records
            $pivotData = $achievements->map(fn($a) => [
                'user_id' => $user->id,
                'achievement_id' => $a->id,
                'unlocked_at' => now(),
            ])->toArray();

            DB::table('user_achievements')->insertOrIgnore($pivotData);

            // Update cache
            $key = "user:{$user->id}:achievements";
            $ids = $achievements->pluck('id')->toArray();
            if (!empty($ids)) {
                \Redis::sAdd($key, ...$ids);
                \Redis::expire($key, 86400);
            }

            // Update XP
            $totalXp = $achievements->sum('xp');
            if ($totalXp > 0) {
                \Redis::hIncrBy("u:{$user->id}:stats", 'xp', $totalXp);
            }
        });

        return $achievements;
    }
}
