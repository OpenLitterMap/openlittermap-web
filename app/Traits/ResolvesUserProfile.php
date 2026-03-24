<?php

namespace App\Traits;

use App\Enums\LocationType;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\LevelService;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

trait ResolvesUserProfile
{
    /**
     * Build the full profile data array for an authenticated user.
     *
     * Optimized for speed: core stats + level + rank in ~2 queries.
     * Heavy data (achievements, locations, global_stats, streak) are
     * available via separate endpoints or deferred to client-side fetch.
     *
     * Used by both ProfileController::index() and AuthTokenController::login()
     * to ensure the same response shape.
     */
    protected function buildFullProfileData(User $user): array
    {
        $userId = $user->id;

        // Core stats: 1 MySQL query + 1 Redis pipeline (stats hash only, no streak)
        $stats = $this->resolveUserStatsLight($userId, $user);
        $uploads = $stats['uploads'];
        $tags = $stats['tags'];
        $xp = $stats['xp'];

        // Level — pure PHP, zero queries
        $levelInfo = LevelService::getUserLevel($xp);

        // Rank — 1 Redis ZREVRANK (fast O(log N)), cached fallback
        $totalRanked = Cache::remember('users:count', 3600, fn () => User::count());
        $globalPosition = $this->getGlobalRank($userId, (int) $user->xp);

        $percentile = $totalRanked > 0
            ? round((1 - ($globalPosition - 1) / $totalRanked) * 100, 1)
            : 0;

        // Team — 1 query only if user has an active team
        $team = null;
        if ($user->active_team) {
            $teamModel = $user->team;
            if ($teamModel) {
                $team = ['id' => $teamModel->id, 'name' => $teamModel->name];
            }
        }

        return [
            'user' => [
                'id' => $userId,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at->toIso8601String(),
                'member_since' => $user->created_at->format('F Y'),
                'global_flag' => $user->global_flag,
                'public_profile' => (bool) $user->public_profile,
                'show_name' => (bool) $user->show_name,
                'show_username' => (bool) $user->show_username,
                'show_name_maps' => (bool) $user->show_name_maps,
                'show_username_maps' => (bool) $user->show_username_maps,
                'picked_up' => $user->picked_up === null ? null : (bool) $user->picked_up,
                'previous_tags' => (bool) $user->previous_tags,
                'emailsub' => (bool) $user->emailsub,
                'prevent_others_tagging_my_photos' => (bool) $user->prevent_others_tagging_my_photos,
                'public_photos' => (bool) $user->public_photos,
            ],
            'stats' => [
                'uploads' => $uploads,
                'tags' => $tags,
                'xp' => $xp,
                'littercoin' => (int) ($user->littercoin_allowance + $user->littercoin_owed),
            ],
            'level' => $levelInfo,
            'rank' => [
                'global_position' => $globalPosition,
                'global_total' => $totalRanked,
                'percentile' => $percentile,
            ],
            'team' => $team,
        ];
    }

    /**
     * Lightweight stats resolution — no streak, no tag breakdown parsing.
     *
     * Cost: 1 Redis HGETALL + 1 MySQL metrics query (indexed).
     * Fallback queries only fire for brand-new users with no metrics row.
     */
    protected function resolveUserStatsLight(int $userId, User $user): array
    {
        // Single Redis hash lookup — just the stats we need
        $userScope = RedisKeys::user($userId);

        try {
            $redisStats = Redis::hGetAll(RedisKeys::stats($userScope));
        } catch (\Exception $e) {
            $redisStats = [];
        }

        $redisUploads = (int) ($redisStats['uploads'] ?? 0);
        $redisXp = (int) ($redisStats['xp'] ?? 0);

        // MySQL metrics — single indexed row lookup
        $metricsRow = DB::table('metrics')
            ->where('user_id', $userId)
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('year', 0)
            ->where('month', 0)
            ->first(['uploads', 'tags', 'xp']);

        $uploads = (int) ($metricsRow->uploads ?? 0);
        $tags = (int) ($metricsRow->tags ?? 0);
        $xp = (int) ($metricsRow->xp ?? 0);

        // Fallback for brand-new users without a metrics row
        if (! $uploads) {
            $uploads = $redisUploads ?: (int) Photo::where('user_id', $userId)->count();
        }
        if (! $xp) {
            $xp = $redisXp ?: (int) $user->xp;
        }

        return [
            'uploads' => $uploads,
            'tags' => $tags,
            'xp' => $xp,
        ];
    }

    /**
     * Resolve user stats from Redis with MySQL fallbacks.
     * Full version — includes streak calculation. Used by ProfileController::show().
     */
    protected function resolveUserStats(int $userId, User $user): array
    {
        // Reuse the lightweight version for core stats
        $stats = $this->resolveUserStatsLight($userId, $user);

        // Add streak (expensive: up to 365 GETBIT ops in a pipeline)
        $userScope = RedisKeys::user($userId);
        $stats['streak'] = $this->calculateStreak($userScope, $userId);

        return $stats;
    }

    /**
     * Calculate user streak from bitmap.
     */
    protected function calculateStreak(string $userScope, int $userId): int
    {
        $bitmapKey = RedisKeys::userBitmap($userId);
        $today = (int) now()->format('z'); // 0-indexed day of year
        $daysToCheck = min($today + 1, 365);

        if ($daysToCheck <= 0) {
            return 0;
        }

        try {
            $bits = Redis::pipeline(function ($pipe) use ($bitmapKey, $today, $daysToCheck) {
                for ($i = 0; $i < $daysToCheck; $i++) {
                    $pipe->getBit($bitmapKey, $today - $i);
                }
            });
        } catch (\Exception $e) {
            return 0;
        }

        $streak = 0;

        foreach ($bits as $i => $bit) {
            if (! $bit) {
                if ($i === 0) {
                    continue;
                }
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Get the user's global rank position from Redis ZREVRANK.
     * Falls back to users.xp count if user is not in the Redis ZSET.
     */
    protected function getGlobalRank(int $userId, int $fallbackXp): int
    {
        $globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
        $rank = Redis::zRevRank($globalXpKey, (string) $userId);

        if ($rank !== false) {
            return $rank + 1;
        }

        return User::where('xp', '>', $fallbackXp)->count() + 1;
    }
}
