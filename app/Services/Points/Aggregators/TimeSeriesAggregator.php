<?php

namespace App\Services\Points\Aggregators;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TimeSeriesAggregator
{
    /**
     * Aggregate time series data
     */
    public function aggregate(string $whereSql, array $bindings, array $params): array
    {
        $groupBy = $this->getTimeGroupBy($params);

        $results = DB::select("
            SELECT
                {$groupBy} as bucket,
                COUNT(*) as photos,
                SUM(total_litter) as objects
            FROM photos
            WHERE {$whereSql}
            GROUP BY bucket
            ORDER BY bucket
        ", $bindings);

        $histogram = array_map(fn($row) => [
            'bucket' => $row->bucket,
            'photos' => (int)$row->photos,
            'objects' => (int)$row->objects,
        ], $results);

        return $this->calculateMetrics($histogram, $params);
    }

    /**
     * Aggregate from temporary table
     */
    public function aggregateFromTable(string $table, array $params): array
    {
        $groupBy = $this->getTimeGroupBy($params);

        $results = DB::select("
            SELECT
                {$groupBy} as bucket,
                COUNT(*) as photos,
                SUM(total_litter) as objects
            FROM {$table}
            GROUP BY bucket
            ORDER BY bucket
        ");

        $histogram = array_map(fn($row) => [
            'bucket' => $row->bucket,
            'photos' => (int)$row->photos,
            'objects' => (int)$row->objects,
        ], $results);

        return $this->calculateMetrics($histogram, $params);
    }

    /**
     * Get SQL GROUP BY clause based on zoom level
     */
    private function getTimeGroupBy(array $params): string
    {
        $zoom = $params['zoom'] ?? 15;

        return match(true) {
            $zoom >= 19 => "DATE_FORMAT(datetime, '%Y-%m-%d %H:00:00')",
            $zoom >= 17 => 'DATE(datetime)',
            $zoom >= 15 => 'YEARWEEK(datetime, 1)',
            default => "DATE_FORMAT(datetime, '%Y-%m-01')",
        };
    }

    /**
     * Calculate time series metrics
     */
    private function calculateMetrics(array $histogram, array $params): array
    {
        if (empty($histogram)) {
            return [
                'date_range' => ['from' => null, 'to' => null, 'days' => 0],
                'histogram' => [],
                'litter_per_minute' => 0,
                'avg_per_day' => 0,
                'peak_day' => null,
            ];
        }

        // Extract date range
        $first = reset($histogram);
        $last = end($histogram);
        $from = CarbonImmutable::parse($first['bucket']);
        $to = CarbonImmutable::parse($last['bucket']);
        $days = $from->diffInDays($to) + 1;

        // Calculate metrics
        $totalObjects = array_sum(array_column($histogram, 'objects'));
        $peakDay = array_reduce($histogram, fn($max, $day) =>
        (!$max || $day['objects'] > $max['objects']) ? $day : $max
        );

        // Litter per minute (assuming 8 active hours per day)
        $activeMinutes = $days * 8 * 60;
        $litterPerMinute = $activeMinutes > 0 ? round($totalObjects / $activeMinutes, 2) : 0;

        return [
            'date_range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'days' => $days,
            ],
            'histogram' => $histogram,
            'litter_per_minute' => $litterPerMinute,
            'avg_per_day' => $days > 0 ? round($totalObjects / $days, 1) : 0,
            'peak_day' => $peakDay ? [
                'date' => $peakDay['bucket'],
                'count' => $peakDay['objects'],
            ] : null,
        ];
    }
}
