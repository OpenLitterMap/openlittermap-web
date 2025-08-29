<?php

declare(strict_types=1);

namespace App\Services\Redis;

use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Trait for Location models to access Redis metrics
 *
 * Use this in Country, State, and City models to get instant access to:
 * - Total photos, litter, XP
 * - Top contributors
 * - Tag breakdowns by dimension
 * - Time series data (daily, weekly, monthly, yearly)
 */
trait LocationMetricsTrait
{
    /**
     * Get the Redis scope for this location
     */
    protected function getRedisScope(): string
    {
        if ($this instanceof \App\Models\Location\Country) {
            return "c:{$this->id}";
        }
        if ($this instanceof \App\Models\Location\State) {
            return "s:{$this->id}";
        }
        if ($this instanceof \App\Models\Location\City) {
            return "ci:{$this->id}";
        }

        throw new \RuntimeException('Unknown location type: ' . get_class($this));
    }

    /**
     * Safe Redis operation wrapper
     */
    private function safeRedis(callable $operation, $fallback = null)
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            Log::error('Redis operation failed in Location model', [
                'location_type' => get_class($this),
                'location_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return $fallback;
        }
    }

    /**
     * Get all-time stats
     */
    public function getStatsAttribute(): array
    {
        $scope = $this->getRedisScope();

        $stats = $this->safeRedis(
            fn() => Redis::hGetAll("$scope:s"),
            []
        );

        return [
            'photos' => (int)($stats['photos'] ?? 0),
            'litter' => (int)($stats['litter'] ?? 0),
            'xp' => (int)($stats['xp'] ?? 0),
            'contributors' => $this->getTotalContributorsAttribute()
        ];
    }

    /**
     * Get total photos count
     */
    public function getTotalPhotosRedisAttribute(): int
    {
        $scope = $this->getRedisScope();
        return (int)$this->safeRedis(
            fn() => Redis::hGet("$scope:s", 'photos'),
            0
        );
    }

    /**
     * Get total litter count
     */
    public function getTotalLitterRedisAttribute(): int
    {
        $scope = $this->getRedisScope();
        return (int)$this->safeRedis(
            fn() => Redis::hGet("$scope:s", 'litter'),
            0
        );
    }

    /**
     * Get total XP
     */
    public function getTotalXpAttribute(): int
    {
        $scope = $this->getRedisScope();
        return (int)$this->safeRedis(
            fn() => Redis::hGet("$scope:s", 'xp'),
            0
        );
    }

    /**
     * Get unique contributor count
     */
    public function getTotalContributorsAttribute(): int
    {
        $scope = $this->getRedisScope();
        return (int)$this->safeRedis(
            fn() => Redis::pfCount("$scope:hll"),
            0
        );
    }

    /**
     * Get top contributors
     */
    public function getTopContributors(int $limit = 10): array
    {
        $scope = $this->getRedisScope();

        $contributors = $this->safeRedis(
            fn() => Redis::zRevRange("$scope:contributors", 0, $limit - 1, 'WITHSCORES'),
            []
        );

        $result = [];
        foreach ($contributors as $userId => $photoCount) {
            $result[(int)$userId] = (int)$photoCount;
        }

        return $result;
    }

    /**
     * Get tag breakdown by dimension
     */
    public function getTagsByDimension(string $dimension = 'objects'): array
    {
        $scope = $this->getRedisScope();

        $prefix = match($dimension) {
            'categories' => 'c:',
            'objects' => 'o:',
            'materials' => 'm:',
            'brands' => 'b:',
            'custom' => 'ct:',
            default => 'o:'
        };

        $allTags = $this->safeRedis(
            fn() => Redis::hGetAll("$scope:t"),
            []
        );

        $result = [];
        foreach ($allTags as $key => $count) {
            if (str_starts_with($key, $prefix)) {
                $tagKey = substr($key, strlen($prefix));

                // Try to get human-readable name from cache
                $name = TagKeyCache::keyFor(
                    rtrim($dimension, 's'), // Remove plural
                    $tagKey
                ) ?? $tagKey;

                $result[$name] = (int)$count;
            }
        }

        arsort($result);
        return $result;
    }

    /**
     * Get litter breakdown by category
     */
    public function getLitterDataAttribute(): array
    {
        return $this->getTagsByDimension('categories');
    }

    /**
     * Get brands breakdown
     */
    public function getBrandsDataAttribute(): array
    {
        return $this->getTagsByDimension('brands');
    }

    /**
     * Get objects breakdown (top 20)
     */
    public function getObjectsDataAttribute(): array
    {
        $objects = $this->getTagsByDimension('objects');
        return array_slice($objects, 0, 20, true);
    }

    /**
     * Get materials breakdown
     */
    public function getMaterialsDataAttribute(): array
    {
        return $this->getTagsByDimension('materials');
    }

    /**
     * Get top items from ranking ZSET
     */
    public function getTopRanked(string $dimension = 'objects', int $limit = 10): array
    {
        $scope = $this->getRedisScope();

        $dimKey = match($dimension) {
            'categories' => 'c',
            'objects' => 'o',
            'brands' => 'b',
            default => 'o'
        };

        $items = $this->safeRedis(
            fn() => Redis::zRevRange("$scope:r:$dimKey", 0, $limit - 1, 'WITHSCORES'),
            []
        );

        $result = [];
        foreach ($items as $id => $count) {
            $name = TagKeyCache::keyFor(
                rtrim($dimension, 's'),
                $id
            ) ?? $id;

            $result[] = [
                'id' => $id,
                'name' => $name,
                'count' => (int)$count
            ];
        }

        return $result;
    }

    /**
     * Get time series data
     */
    public function getTimeSeries(string $granularity = 'daily', int $periods = 30): array
    {
        $scope = $this->getRedisScope();
        $result = [];

        switch ($granularity) {
            case 'daily':
                for ($i = $periods - 1; $i >= 0; $i--) {
                    $date = now('UTC')->subDays($i)->format('Ymd');
                    $key = "$scope:d:$date";
                    $data = $this->safeRedis(fn() => Redis::hGetAll($key), []);
                    $result[$date] = [
                        'photos' => (int)($data['p'] ?? 0),
                        'litter' => (int)($data['l'] ?? 0),
                        'xp' => (int)($data['x'] ?? 0)
                    ];
                }
                break;

            case 'weekly':
                for ($i = $periods - 1; $i >= 0; $i--) {
                    $week = now('UTC')->subWeeks($i)->format('YW');
                    $key = "$scope:w:$week";
                    $data = $this->safeRedis(fn() => Redis::hGetAll($key), []);
                    $result[$week] = [
                        'photos' => (int)($data['p'] ?? 0),
                        'litter' => (int)($data['l'] ?? 0),
                        'xp' => (int)($data['x'] ?? 0)
                    ];
                }
                break;

            case 'monthly':
                for ($i = $periods - 1; $i >= 0; $i--) {
                    $month = now('UTC')->subMonths($i)->format('Ym');
                    $key = "$scope:m:$month";
                    $data = $this->safeRedis(fn() => Redis::hGetAll($key), []);
                    $result[$month] = [
                        'photos' => (int)($data['p'] ?? 0),
                        'litter' => (int)($data['l'] ?? 0),
                        'xp' => (int)($data['x'] ?? 0)
                    ];
                }
                break;

            case 'yearly':
                for ($i = $periods - 1; $i >= 0; $i--) {
                    $year = now('UTC')->subYears($i)->format('Y');
                    $key = "$scope:y:$year";
                    $data = $this->safeRedis(fn() => Redis::hGetAll($key), []);
                    $result[$year] = [
                        'photos' => (int)($data['p'] ?? 0),
                        'litter' => (int)($data['l'] ?? 0),
                        'xp' => (int)($data['x'] ?? 0)
                    ];
                }
                break;
        }

        return $result;
    }

    /**
     * Get daily activity (convenience method)
     */
    public function getDailyActivity(int $days = 30): array
    {
        $series = $this->getTimeSeries('daily', $days);
        $result = [];

        foreach ($series as $date => $data) {
            $formatted = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            $result[$formatted] = $data['photos'];
        }

        return $result;
    }

    /**
     * Get photos per month (legacy compatibility)
     */
    public function getPpmAttribute(): array
    {
        $series = $this->getTimeSeries('monthly', 24);
        $result = [];

        foreach ($series as $month => $data) {
            $formatted = substr($month, 0, 4) . '-' . substr($month, 4, 2);
            $result[$formatted] = $data['photos'];
        }

        return $result;
    }

    /**
     * Get recent activity (last 7 days)
     */
    public function getRecentActivityAttribute(): array
    {
        return $this->getDailyActivity(7);
    }

    /**
     * Check if location has recent activity
     */
    public function hasRecentActivity(int $days = 7): bool
    {
        $activity = $this->getDailyActivity($days);
        return array_sum($activity) > 0;
    }

    /**
     * Get percentage of global totals
     */
    public function getGlobalPercentage(string $metric = 'litter'): float
    {
        $localValue = match($metric) {
            'photos' => $this->total_photos_redis,
            'litter' => $this->total_litter_redis,
            'xp' => $this->total_xp,
            'contributors' => $this->total_contributors,
            default => 0
        };

        if ($localValue === 0) {
            return 0.0;
        }

        $globalValue = $this->safeRedis(function() use ($metric) {
            return match($metric) {
                'photos' => (int)Redis::hGet('g:s', 'photos'),
                'litter' => (int)Redis::hGet('g:s', 'litter'),
                'xp' => (int)Redis::hGet('g:s', 'xp'),
                'contributors' => Redis::pfCount('g:hll'),
                default => 0
            };
        }, 0);

        return $globalValue > 0 ? round(($localValue / $globalValue) * 100, 2) : 0.0;
    }

    /**
     * Get complete metrics summary
     */
    public function getMetricsSummary(): array
    {
        return [
            'stats' => $this->stats,
            'top_contributors' => $this->getTopContributors(5),
            'top_objects' => $this->getTopRanked('objects', 10),
            'top_brands' => $this->getTopRanked('brands', 10),
            'recent_activity' => $this->recent_activity,
            'monthly_trend' => $this->ppm,
            'global_percentage' => [
                'photos' => $this->getGlobalPercentage('photos'),
                'litter' => $this->getGlobalPercentage('litter'),
            ]
        ];
    }
}
