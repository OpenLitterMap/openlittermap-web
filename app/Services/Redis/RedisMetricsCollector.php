<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Photo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis metrics collector that works alongside MetricsService
 *
 * Key changes:
 * - No longer handles MySQL writes (MetricsService does that)
 * - Respects processed_fp for idempotency
 * - Uses processed_xp for correct XP tracking
 * - Only handles Redis operations
 */
final class RedisMetricsCollector
{
    /**
     * Process a photo into Redis (called by MetricsService after MySQL update)
     */
    public static function processPhoto(Photo $photo, array $metrics, string $operation): void
    {
        try {
            $scopes = RedisKeys::getPhotoScopes($photo);
            $userId = $photo->user_id;

            Redis::pipeline(function($pipe) use ($scopes, $userId, $metrics, $operation, $photo) {
                foreach ($scopes as $scope) {
                    if ($operation === 'create') {
                        // Increment basic stats
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'photos', 1);
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', $metrics['litter']);
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'xp', $metrics['xp']);

                        // Track unique contributors (append-only) - FIX: pfAdd needs array
                        $pipe->pfAdd(RedisKeys::hll($scope), [(string)$userId]);

                        // Contributor ranking
                        $pipe->zIncrBy(RedisKeys::contributorRanking($scope), 1, (string)$userId);

                        // XP leaderboard ranking
                        $pipe->zIncrBy(RedisKeys::xpRanking($scope), $metrics['xp'], (string)$userId);

                    } elseif ($operation === 'update') {
                        // Apply deltas only
                        if (isset($metrics['litter']) && $metrics['litter'] !== 0) {
                            $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', $metrics['litter']);
                        }
                        if (isset($metrics['xp']) && $metrics['xp'] !== 0) {
                            $pipe->hIncrBy(RedisKeys::stats($scope), 'xp', $metrics['xp']);
                            $pipe->zIncrBy(RedisKeys::xpRanking($scope), $metrics['xp'], (string)$userId);
                        }

                    } elseif ($operation === 'delete') {
                        // Decrement stats
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'photos', -1);
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'litter', -abs($metrics['litter']));
                        $pipe->hIncrBy(RedisKeys::stats($scope), 'xp', -abs($metrics['xp']));

                        // Decrement contributor ranking (HLL cannot be decremented)
                        $pipe->zIncrBy(RedisKeys::contributorRanking($scope), -1, (string)$userId);

                        // XP leaderboard ranking
                        $pipe->zIncrBy(RedisKeys::xpRanking($scope), -abs($metrics['xp']), (string)$userId);
                    }

                    // Update tag counts and rankings
                    self::updateTags($pipe, $scope, $metrics['tags'] ?? [], $operation);
                }

                // User-specific updates
                self::updateUserMetrics($pipe, $userId, $metrics, $operation, $photo);
            });

        } catch (\Exception $e) {
            Log::error('Redis update failed', [
                'photo_id' => $photo->id,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update tag counts and rankings
     */
    private static function updateTags($pipe, string $scope, array $tags, string $operation): void
    {
        foreach (['categories', 'objects', 'materials', 'brands', 'custom_tags'] as $dimension) {
            $items = $tags[$dimension] ?? [];
            if (empty($items)) continue;

            $hashKey = match($dimension) {
                'categories' => RedisKeys::categories($scope),
                'objects' => RedisKeys::objects($scope),
                'materials' => RedisKeys::materials($scope),
                'brands' => RedisKeys::brands($scope),
                'custom_tags' => RedisKeys::customTags($scope),
            };

            $rankKey = RedisKeys::ranking($scope, $dimension);

            foreach ($items as $id => $count) {
                if ($operation === 'delete') {
                    $count = -abs($count);
                }

                $pipe->hIncrBy($hashKey, (string)$id, $count);
                $pipe->zIncrBy($rankKey, $count, (string)$id);
            }
        }
    }

    /**
     * Update user-specific metrics
     */
    private static function updateUserMetrics($pipe, int $userId, array $metrics, string $operation, Photo $photo): void
    {
        $userScope = RedisKeys::user($userId);

        if ($operation === 'create') {
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'uploads', 1);
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', $metrics['xp']);
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'litter', $metrics['litter']);

            // Update streak bitmap
            $dayIndex = self::getDayIndex($photo->created_at);
            $pipe->setBit(RedisKeys::userBitmap($userId), $dayIndex, true);

        } elseif ($operation === 'update') {
            // Only update if values changed
            if (isset($metrics['xp']) && $metrics['xp'] !== 0) {
                $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', $metrics['xp']);
            }
            if (isset($metrics['litter']) && $metrics['litter'] !== 0) {
                $pipe->hIncrBy(RedisKeys::stats($userScope), 'litter', $metrics['litter']);
            }

        } elseif ($operation === 'delete') {
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'uploads', -1);
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'xp', -abs($metrics['xp']));
            $pipe->hIncrBy(RedisKeys::stats($userScope), 'litter', -abs($metrics['litter']));
        }

        // Update user tags
        foreach ($metrics['tags'] ?? [] as $dimension => $items) {
            foreach ($items as $id => $count) {
                if ($operation === 'delete') {
                    $count = -abs($count);
                }

                $tagKey = match($dimension) {
                    'categories' => "cat:$id",
                    'objects' => "obj:$id",
                    'materials' => "mat:$id",
                    'brands' => "brand:$id",
                    'custom_tags' => "custom:$id",
                    default => null,
                };

                if ($tagKey) {
                    $pipe->hIncrBy("{$userScope}:tags", $tagKey, $count);
                }
            }
        }
    }

    /**
     * Calculate day index for bitmap (consistent epoch)
     */
    private static function getDayIndex($timestamp): int
    {
        $epoch = new \DateTime('2020-01-01', new \DateTimeZone('UTC'));
        $current = new \DateTime($timestamp->format('Y-m-d'), new \DateTimeZone('UTC'));
        return $epoch->diff($current)->days;
    }

    /**
     * Get user metrics summary
     */
    public static function getUserMetrics(int $userId): array
    {
        $userScope = RedisKeys::user($userId);

        try {
            $results = Redis::pipeline(function($pipe) use ($userScope) {
                $pipe->hGetAll(RedisKeys::stats($userScope));
                $pipe->hGetAll("{$userScope}:tags");
            });
        } catch (\Exception $e) {
            Log::error('Failed to get user metrics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return self::emptyUserMetrics();
        }

        $stats = $results[0] ?? [];
        $tags = $results[1] ?? [];

        // Parse tags by dimension
        $dimensions = [
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => []
        ];

        foreach ($tags as $key => $count) {
            [$prefix, $id] = explode(':', $key, 2);
            $count = (int)$count;

            switch ($prefix) {
                case 'cat':
                    $dimensions['categories'][$id] = $count;
                    break;
                case 'obj':
                    $dimensions['objects'][$id] = $count;
                    break;
                case 'mat':
                    $dimensions['materials'][$id] = $count;
                    break;
                case 'brand':
                    $dimensions['brands'][$id] = $count;
                    break;
                case 'custom':
                    $dimensions['custom_tags'][$id] = $count;
                    break;
            }
        }

        return [
            'uploads' => (int)($stats['uploads'] ?? 0),
            'xp' => (int)($stats['xp'] ?? 0),
            'litter' => (int)($stats['litter'] ?? 0),
            'streak' => self::calculateStreak($userId),
            'categories' => $dimensions['categories'],
            'objects' => $dimensions['objects'],
            'materials' => $dimensions['materials'],
            'brands' => $dimensions['brands'],
            'custom_tags' => $dimensions['custom_tags'],
        ];
    }

    /**
     * Calculate user streak from bitmap
     */
    private static function calculateStreak(int $userId): int
    {
        $bitmapKey = RedisKeys::userBitmap($userId);
        $today = self::getDayIndex(now());
        $streak = 0;

        // Check backwards from today
        for ($i = 0; $i < 365; $i++) {
            $dayIndex = $today - $i;
            if ($dayIndex < 0) break;

            if (!Redis::getBit($bitmapKey, $dayIndex)) {
                // Allow 1 day gap for today
                if ($i === 0) continue;
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * Empty user metrics structure
     */
    private static function emptyUserMetrics(): array
    {
        return [
            'uploads' => 0,
            'xp' => 0,
            'litter' => 0,
            'streak' => 0,
            'categories' => [],
            'objects' => [],
            'materials' => [],
            'brands' => [],
            'custom_tags' => [],
        ];
    }
}
