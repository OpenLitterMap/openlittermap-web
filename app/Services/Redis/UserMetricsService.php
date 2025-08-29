<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Models\Users\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Service for accessing user metrics from Redis
 *
 * Provides convenient methods for retrieving user statistics,
 * achievements progress, and activity patterns
 */
class UserMetricsService
{
    /**
     * Get comprehensive user metrics
     */
    public static function getUserMetrics(int $userId): array
    {
        return RedisMetricsCollector::getUserMetrics($userId);
    }

    /**
     * Get user stats only
     */
    public static function getUserStats(int $userId): array
    {
        $scope = "u:$userId";

        try {
            $stats = Redis::hGetAll("$scope:s");
            return [
                'uploads' => (int)($stats['uploads'] ?? 0),
                'xp' => (int)($stats['xp'] ?? 0),
                'litter' => (int)($stats['litter'] ?? 0),
                'streak' => self::calculateStreak($userId)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['uploads' => 0, 'xp' => 0, 'litter' => 0, 'streak' => 0];
        }
    }

    /**
     * Get user's top tags by dimension
     */
    public static function getUserTopTags(int $userId, string $dimension = 'objects', int $limit = 10): array
    {
        $scope = "u:$userId";

        $prefix = match($dimension) {
            'categories' => 'c:',
            'objects' => 'o:',
            'materials' => 'm:',
            'brands' => 'b:',
            'custom' => 'ct:',
            default => 'o:'
        };

        try {
            $allTags = Redis::hGetAll("$scope:t");
            $filtered = [];

            foreach ($allTags as $key => $count) {
                if (str_starts_with($key, $prefix)) {
                    $tagKey = substr($key, strlen($prefix));
                    $filtered[$tagKey] = (int)$count;
                }
            }

            arsort($filtered);
            return array_slice($filtered, 0, $limit, true);

        } catch (\Exception $e) {
            Log::error('Failed to get user top tags', [
                'user_id' => $userId,
                'dimension' => $dimension,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get user's activity calendar (bitmap based)
     */
    public static function getUserActivityCalendar(int $userId, int $days = 365): array
    {
        $bitmapKey = "u:$userId:b";
        $result = [];

        try {
            $today = self::getDayIndex(now());

            for ($i = $days - 1; $i >= 0; $i--) {
                $dayIndex = $today - $i;
                if ($dayIndex < 0) continue;

                $date = now('UTC')->subDays($i)->format('Y-m-d');
                $active = (bool)Redis::getBit($bitmapKey, $dayIndex);
                $result[$date] = $active;
            }

        } catch (\Exception $e) {
            Log::error('Failed to get user activity calendar', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Get user's contribution ranking in different scopes
     */
    public static function getUserRankings(int $userId): array
    {
        $rankings = [];

        try {
            // Global ranking
            $globalRank = Redis::zRevRank('g:contributors', (string)$userId);
            if ($globalRank !== null) {
                $rankings['global'] = $globalRank + 1; // Convert 0-indexed to 1-indexed
            }

            // Get user's photo to find their locations
            $user = User::find($userId);
            if ($user) {
                $photo = $user->photos()->whereNotNull('country_id')->first();

                if ($photo) {
                    if ($photo->country_id) {
                        $countryRank = Redis::zRevRank("c:{$photo->country_id}:contributors", (string)$userId);
                        if ($countryRank !== null) {
                            $rankings['country'] = $countryRank + 1;
                        }
                    }

                    if ($photo->state_id) {
                        $stateRank = Redis::zRevRank("s:{$photo->state_id}:contributors", (string)$userId);
                        if ($stateRank !== null) {
                            $rankings['state'] = $stateRank + 1;
                        }
                    }

                    if ($photo->city_id) {
                        $cityRank = Redis::zRevRank("ci:{$photo->city_id}:contributors", (string)$userId);
                        if ($cityRank !== null) {
                            $rankings['city'] = $cityRank + 1;
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to get user rankings', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        return $rankings;
    }

    /**
     * Get user's monthly trend
     */
    public static function getUserMonthlyTrend(int $userId, int $months = 12): array
    {
        $result = [];

        try {
            // We need to aggregate from location scopes since we don't store user-specific time series
            // For now, calculate from the activity bitmap
            $calendar = self::getUserActivityCalendar($userId, $months * 31);

            foreach ($calendar as $date => $active) {
                $month = substr($date, 0, 7);
                if (!isset($result[$month])) {
                    $result[$month] = 0;
                }
                if ($active) {
                    $result[$month]++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to get user monthly trend', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Calculate user's current streak
     */
    private static function calculateStreak(int $userId): int
    {
        $bitmapKey = "u:$userId:b";
        $today = self::getDayIndex(now());
        $streak = 0;

        try {
            // Check backwards from today
            for ($i = 0; $i < 365; $i++) {
                $dayIndex = $today - $i;
                if ($dayIndex < 0) break;

                $active = Redis::getBit($bitmapKey, $dayIndex);

                if (!$active) {
                    // Allow today to be empty (streak continues from yesterday)
                    if ($i === 0) continue;
                    break;
                }

                // Don't count today if we skipped it
                if ($i > 0 || $active) {
                    $streak++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to calculate streak', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }

        return $streak;
    }

    /**
     * Calculate day index for bitmap
     */
    private static function getDayIndex($timestamp): int
    {
        $epoch = new \DateTime('2020-01-01', new \DateTimeZone('UTC'));
        $diff = $epoch->diff($timestamp);
        return $diff->days;
    }

    /**
     * Get user's achievement progress
     */
    public static function getAchievementProgress(int $userId): array
    {
        $metrics = self::getUserMetrics($userId);

        return [
            'uploads' => [
                'current' => $metrics['uploads'],
                'next_milestone' => self::getNextMilestone($metrics['uploads'], [10, 50, 100, 500, 1000, 5000, 10000])
            ],
            'litter' => [
                'current' => $metrics['litter'],
                'next_milestone' => self::getNextMilestone($metrics['litter'], [100, 500, 1000, 5000, 10000, 50000, 100000])
            ],
            'streak' => [
                'current' => $metrics['streak'],
                'next_milestone' => self::getNextMilestone($metrics['streak'], [7, 30, 60, 90, 180, 365])
            ],
            'categories' => [
                'unique' => count($metrics['categories']),
                'next_milestone' => self::getNextMilestone(count($metrics['categories']), [5, 10, 15, 20])
            ],
            'brands' => [
                'unique' => count($metrics['brands']),
                'next_milestone' => self::getNextMilestone(count($metrics['brands']), [10, 25, 50, 100, 250, 500])
            ]
        ];
    }

    /**
     * Get next milestone for a value
     */
    private static function getNextMilestone(int $current, array $milestones): ?int
    {
        foreach ($milestones as $milestone) {
            if ($current < $milestone) {
                return $milestone;
            }
        }
        return null;
    }

    /**
     * Get user's contribution summary
     */
    public static function getUserSummary(int $userId): array
    {
        $metrics = self::getUserMetrics($userId);
        $rankings = self::getUserRankings($userId);
        $activity = self::getUserActivityCalendar($userId, 30);

        return [
            'stats' => [
                'uploads' => $metrics['uploads'],
                'xp' => $metrics['xp'],
                'litter' => $metrics['litter'],
                'streak' => $metrics['streak']
            ],
            'rankings' => $rankings,
            'recent_activity' => array_sum(array_values($activity)),
            'top_categories' => self::getUserTopTags($userId, 'categories', 5),
            'top_brands' => self::getUserTopTags($userId, 'brands', 5),
            'achievement_progress' => self::getAchievementProgress($userId)
        ];
    }
}
