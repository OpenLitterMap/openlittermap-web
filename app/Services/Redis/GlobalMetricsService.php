<?php

declare(strict_types=1);

namespace App\Services\Redis;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Services\Achievements\Tags\TagKeyCache;

/**
 * Service for accessing global metrics and rankings
 *
 * Provides methods for retrieving platform-wide statistics,
 * leaderboards, and trend analysis
 */
class GlobalMetricsService
{
    /**
     * Get global statistics
     */
    public static function getGlobalStats(): array
    {
        try {
            $stats = Redis::hGetAll('g:s');
            $contributors = Redis::pfCount('g:hll');

            return [
                'photos' => (int)($stats['photos'] ?? 0),
                'litter' => (int)($stats['litter'] ?? 0),
                'xp' => (int)($stats['xp'] ?? 0),
                'contributors' => $contributors
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get global stats', ['error' => $e->getMessage()]);
            return ['photos' => 0, 'litter' => 0, 'xp' => 0, 'contributors' => 0];
        }
    }

    /**
     * Get top contributors globally
     */
    public static function getTopContributors(int $limit = 100): array
    {
        try {
            $contributors = Redis::zRevRange('g:contributors', 0, $limit - 1, 'WITHSCORES');

            $result = [];
            foreach ($contributors as $userId => $score) {
                $result[] = [
                    'user_id' => (int)$userId,
                    'photos' => (int)$score
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get top contributors', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get top countries by metric
     */
    public static function getTopCountries(string $metric = 'litter', int $limit = 50): array
    {
        try {
            $countries = [];

            // Get all country scopes (this would need to be tracked separately in production)
            // For now, we'll scan for country keys (not ideal for production)
            $keys = Redis::keys('c:*:s');

            foreach ($keys as $key) {
                if (preg_match('/^c:(\d+):s$/', $key, $matches)) {
                    $countryId = (int)$matches[1];
                    $value = (int)Redis::hGet($key, $metric === 'photos' ? 'photos' : 'litter');
                    if ($value > 0) {
                        $countries[$countryId] = $value;
                    }
                }
            }

            arsort($countries);
            $countries = array_slice($countries, 0, $limit, true);

            $result = [];
            foreach ($countries as $id => $value) {
                $result[] = [
                    'country_id' => $id,
                    $metric => $value
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get top countries', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get global top tags by dimension
     */
    public static function getTopTags(string $dimension = 'objects', int $limit = 100): array
    {
        $dimKey = match($dimension) {
            'categories' => 'c',
            'objects' => 'o',
            'brands' => 'b',
            'materials' => 'm',
            default => 'o'
        };

        try {
            $items = Redis::zRevRange("g:r:$dimKey", 0, $limit - 1, 'WITHSCORES');

            $result = [];
            foreach ($items as $id => $count) {
                $name = TagKeyCache::keyFor(rtrim($dimension, 's'), $id) ?? $id;

                $result[] = [
                    'id' => $id,
                    'name' => $name,
                    'count' => (int)$count
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get top tags', [
                'dimension' => $dimension,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get global time series
     */
    public static function getTimeSeries(string $granularity = 'daily', int $periods = 30): array
    {
        $result = [];

        try {
            switch ($granularity) {
                case 'daily':
                    for ($i = $periods - 1; $i >= 0; $i--) {
                        $date = now('UTC')->subDays($i);
                        $key = 'g:d:' . $date->format('Ymd');
                        $data = Redis::hGetAll($key);

                        $result[$date->format('Y-m-d')] = [
                            'photos' => (int)($data['p'] ?? 0),
                            'litter' => (int)($data['l'] ?? 0),
                            'xp' => (int)($data['x'] ?? 0)
                        ];
                    }
                    break;

                case 'weekly':
                    for ($i = $periods - 1; $i >= 0; $i--) {
                        $week = now('UTC')->subWeeks($i);
                        $key = 'g:w:' . $week->format('YW');
                        $data = Redis::hGetAll($key);

                        $result[$week->format('Y-W')] = [
                            'photos' => (int)($data['p'] ?? 0),
                            'litter' => (int)($data['l'] ?? 0),
                            'xp' => (int)($data['x'] ?? 0)
                        ];
                    }
                    break;

                case 'monthly':
                    for ($i = $periods - 1; $i >= 0; $i--) {
                        $month = now('UTC')->subMonths($i);
                        $key = 'g:m:' . $month->format('Ym');
                        $data = Redis::hGetAll($key);

                        $result[$month->format('Y-m')] = [
                            'photos' => (int)($data['p'] ?? 0),
                            'litter' => (int)($data['l'] ?? 0),
                            'xp' => (int)($data['x'] ?? 0)
                        ];
                    }
                    break;

                case 'yearly':
                    for ($i = $periods - 1; $i >= 0; $i--) {
                        $year = now('UTC')->subYears($i);
                        $key = 'g:y:' . $year->format('Y');
                        $data = Redis::hGetAll($key);

                        $result[$year->format('Y')] = [
                            'photos' => (int)($data['p'] ?? 0),
                            'litter' => (int)($data['l'] ?? 0),
                            'xp' => (int)($data['x'] ?? 0)
                        ];
                    }
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to get time series', [
                'granularity' => $granularity,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Get platform growth metrics
     */
    public static function getGrowthMetrics(): array
    {
        try {
            // Compare current month to previous month
            $thisMonth = now('UTC')->format('Ym');
            $lastMonth = now('UTC')->subMonth()->format('Ym');

            $thisMonthData = Redis::hGetAll("g:m:$thisMonth");
            $lastMonthData = Redis::hGetAll("g:m:$lastMonth");

            $photosGrowth = self::calculateGrowthRate(
                (int)($thisMonthData['p'] ?? 0),
                (int)($lastMonthData['p'] ?? 0)
            );

            $litterGrowth = self::calculateGrowthRate(
                (int)($thisMonthData['l'] ?? 0),
                (int)($lastMonthData['l'] ?? 0)
            );

            // Weekly active contributors
            $weeklyContributors = [];
            for ($i = 0; $i < 4; $i++) {
                $week = now('UTC')->subWeeks($i)->format('YW');
                // This would need separate tracking in production
                $weeklyContributors[] = 0; // Placeholder
            }

            return [
                'monthly_photos_growth' => $photosGrowth,
                'monthly_litter_growth' => $litterGrowth,
                'weekly_active_contributors' => $weeklyContributors
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get growth metrics', ['error' => $e->getMessage()]);
            return [
                'monthly_photos_growth' => 0,
                'monthly_litter_growth' => 0,
                'weekly_active_contributors' => []
            ];
        }
    }

    /**
     * Calculate growth rate percentage
     */
    private static function calculateGrowthRate(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get tag distribution percentages
     */
    public static function getTagDistribution(): array
    {
        try {
            $allTags = Redis::hGetAll('g:t');

            $categories = [];
            $materials = [];
            $totalLitter = 0;

            foreach ($allTags as $key => $count) {
                $count = (int)$count;

                if (str_starts_with($key, 'c:')) {
                    $catKey = substr($key, 2);
                    $categories[$catKey] = $count;
                    $totalLitter += $count;
                } elseif (str_starts_with($key, 'm:')) {
                    $matKey = substr($key, 2);
                    $materials[$matKey] = $count;
                }
            }

            // Calculate percentages
            $categoryPercentages = [];
            foreach ($categories as $cat => $count) {
                $name = TagKeyCache::keyFor('category', $cat) ?? $cat;
                $categoryPercentages[$name] = $totalLitter > 0
                    ? round(($count / $totalLitter) * 100, 2)
                    : 0;
            }

            arsort($categoryPercentages);
            arsort($materials);

            return [
                'categories' => $categoryPercentages,
                'top_materials' => array_slice($materials, 0, 10, true)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get tag distribution', ['error' => $e->getMessage()]);
            return ['categories' => [], 'top_materials' => []];
        }
    }

    /**
     * Get dashboard summary
     */
    public static function getDashboardSummary(): array
    {
        return [
            'stats' => self::getGlobalStats(),
            'top_contributors' => self::getTopContributors(10),
            'top_countries' => self::getTopCountries('litter', 10),
            'top_brands' => self::getTopTags('brands', 10),
            'recent_activity' => self::getTimeSeries('daily', 7),
            'growth' => self::getGrowthMetrics()
        ];
    }
}
