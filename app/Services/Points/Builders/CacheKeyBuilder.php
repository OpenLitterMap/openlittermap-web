<?php

namespace App\Services\Points\Builders;

class CacheKeyBuilder
{
    private const CACHE_TTL = [
        'hour' => 300,    // 5 minutes
        'day' => 1800,    // 30 minutes
        'week' => 3600,   // 1 hour
        'month' => 7200,  // 2 hours
        'year' => 21600,  // 6 hours
    ];

    /**
     * Build cache key from parameters
     */
    public function build(array $params): string
    {
        ksort($params);
        return 'points_stats_' . md5(json_encode($params));
    }

    /**
     * Get cache TTL based on zoom level
     */
    public function getTTL(array $params): int
    {
        $zoom = $params['zoom'] ?? 15;

        $timeGroup = match(true) {
            $zoom >= 19 => 'hour',
            $zoom >= 17 => 'day',
            $zoom >= 15 => 'week',
            default => 'month',
        };

        return self::CACHE_TTL[$timeGroup] ?? 1800;
    }
}
