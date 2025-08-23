<?php

namespace App\Services\Points;

use App\Services\Points\Aggregators\MetadataAggregator;
use App\Services\Points\Aggregators\TimeSeriesAggregator;
use App\Services\Points\Aggregators\CategoryAggregator;
use App\Services\Points\Aggregators\ObjectAggregator;
use App\Services\Points\Aggregators\BrandAggregator;
use App\Services\Points\Aggregators\MaterialAggregator;
use App\Services\Points\Aggregators\ContributorAggregator;
use App\Services\Points\Builders\QueryBuilder;
use App\Services\Points\Builders\CacheKeyBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PointsStatsService
{
    private const MAX_ROWS_FOR_DIRECT = 5000;
    private const MAX_ROWS_FOR_TEMP = 100000;
    private const SAMPLE_RATE = 10;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly CacheKeyBuilder $cacheKeyBuilder,
        private readonly MetadataAggregator $metadataAggregator,
        private readonly TimeSeriesAggregator $timeSeriesAggregator,
        private readonly CategoryAggregator $categoryAggregator,
        private readonly ObjectAggregator $objectAggregator,
        private readonly BrandAggregator $brandAggregator,
        private readonly MaterialAggregator $materialAggregator,
        private readonly ContributorAggregator $contributorAggregator
    ) {}

    /**
     * Get aggregated statistics for the given parameters
     */
    public function getStats(array $params): array
    {
        $cacheKey = $this->cacheKeyBuilder->build($params);
        $cacheTTL = $this->cacheKeyBuilder->getTTL($params);

        return Cache::remember($cacheKey, $cacheTTL, function() use ($params) {
            return $this->generateStats($params);
        });
    }

    /**
     * Generate fresh statistics
     */
    private function generateStats(array $params): array
    {
        $totalCount = $this->getTotalPhotoCount($params);

        // Choose aggregation strategy based on data size
        $strategy = $this->determineStrategy($totalCount);

        // Build query components
        [$whereSql, $bindings] = $this->queryBuilder->buildWhere($params);

        // Execute aggregation based on strategy
        $rawData = match($strategy) {
            'direct' => $this->directAggregate($whereSql, $bindings, $params),
            'temp' => $this->tempTableAggregate($whereSql, $bindings, $params, $totalCount),
            'sampled' => $this->sampledAggregate($whereSql, $bindings, $params),
        };

        return $this->formatResponse($rawData, $params);
    }

    /**
     * Get total count of photos matching filters
     */
    private function getTotalPhotoCount(array $params): int
    {
        [$whereSql, $bindings] = $this->queryBuilder->buildWhere($params);
        $result = DB::selectOne("SELECT COUNT(*) as count FROM photos WHERE {$whereSql}", $bindings);
        return (int)($result->count ?? 0);
    }

    /**
     * Determine aggregation strategy based on data size
     */
    private function determineStrategy(int $count): string
    {
        if ($count <= self::MAX_ROWS_FOR_DIRECT) {
            return 'direct';
        }

        if ($count <= self::MAX_ROWS_FOR_TEMP) {
            return 'temp';
        }

        return 'sampled';
    }

    /**
     * Direct aggregation for small datasets
     */
    private function directAggregate(string $whereSql, array $bindings, array $params): array
    {
        return [
            'metadata' => $this->metadataAggregator->aggregate($whereSql, $bindings),
            'time_series' => $this->timeSeriesAggregator->aggregate($whereSql, $bindings, $params),
            'categories' => $this->categoryAggregator->aggregate($whereSql, $bindings),
            'objects' => $this->objectAggregator->aggregate($whereSql, $bindings),
            'brands' => $this->brandAggregator->aggregate($whereSql, $bindings),
            'materials' => $this->materialAggregator->aggregate($whereSql, $bindings),
            'contributors' => $this->contributorAggregator->aggregate($whereSql, $bindings),
            'meta' => [
                'strategy' => 'direct',
                'cached' => false,
                'sampling' => false,
                'truncated' => false,
            ],
        ];
    }

    /**
     * Temporary table aggregation for medium datasets
     */
    private function tempTableAggregate(string $whereSql, array $bindings, array $params, int $totalCount): array
    {
        $tempTable = 'temp_photos_' . uniqid();

        try {
            // Create temporary table with subset of data
            DB::statement("
                CREATE TEMPORARY TABLE {$tempTable} AS
                SELECT * FROM photos
                WHERE {$whereSql}
                LIMIT " . self::MAX_ROWS_FOR_TEMP,
                $bindings
            );

            // Run aggregations on temp table
            return [
                'metadata' => $this->metadataAggregator->aggregateFromTable($tempTable),
                'time_series' => $this->timeSeriesAggregator->aggregateFromTable($tempTable, $params),
                'categories' => $this->categoryAggregator->aggregateFromTable($tempTable),
                'objects' => $this->objectAggregator->aggregateFromTable($tempTable),
                'brands' => $this->brandAggregator->aggregateFromTable($tempTable),
                'materials' => $this->materialAggregator->aggregateFromTable($tempTable),
                'contributors' => $this->contributorAggregator->aggregateFromTable($tempTable),
                'meta' => [
                    'strategy' => 'temp_table',
                    'cached' => false,
                    'sampling' => false,
                    'truncated' => $totalCount > self::MAX_ROWS_FOR_TEMP,
                ],
            ];
        } finally {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS {$tempTable}");
        }
    }

    /**
     * Sampled aggregation for very large datasets
     */
    private function sampledAggregate(string $whereSql, array $bindings, array $params): array
    {
        // Add sampling condition
        $sampledWhere = $whereSql . ' AND (id % ? = 0)';
        $sampledBindings = array_merge($bindings, [self::SAMPLE_RATE]);

        $data = [
            'metadata' => $this->metadataAggregator->aggregate($sampledWhere, $sampledBindings),
            'time_series' => $this->timeSeriesAggregator->aggregate($sampledWhere, $sampledBindings, $params),
            'categories' => $this->categoryAggregator->aggregate($sampledWhere, $sampledBindings),
            'objects' => $this->objectAggregator->aggregate($sampledWhere, $sampledBindings),
            'brands' => $this->brandAggregator->aggregate($sampledWhere, $sampledBindings),
            'materials' => $this->materialAggregator->aggregate($sampledWhere, $sampledBindings),
            'contributors' => $this->contributorAggregator->aggregate($sampledWhere, $sampledBindings),
            'meta' => [
                'strategy' => 'sampled',
                'cached' => false,
                'sampling' => true,
                'sample_rate' => self::SAMPLE_RATE,
                'truncated' => false,
            ],
        ];

        // Scale up sampled results
        $this->scaleUpResults($data, self::SAMPLE_RATE);

        return $data;
    }

    /**
     * Scale up sampled results
     */
    private function scaleUpResults(array &$data, int $sampleRate): void
    {
        // Scale metadata
        foreach (['total_photos', 'total_tags', 'total_objects', 'total_brands', 'total_materials', 'total_users', 'total_teams', 'picked_up', 'not_picked_up'] as $field) {
            if (isset($data['metadata'][$field])) {
                $data['metadata'][$field] *= $sampleRate;
            }
        }

        // Scale time series
        if (isset($data['time_series']['histogram'])) {
            foreach ($data['time_series']['histogram'] as &$bucket) {
                $bucket['photos'] *= $sampleRate;
                $bucket['objects'] *= $sampleRate;
            }
        }

        // Scale categories
        foreach ($data['categories'] as &$category) {
            $category['count'] *= $sampleRate;
        }

        // Scale objects
        foreach ($data['objects'] as &$object) {
            $object['count'] *= $sampleRate;
        }

        // Scale brands
        foreach ($data['brands'] as &$brand) {
            $brand['count'] *= $sampleRate;
        }

        // Scale materials
        foreach ($data['materials'] as &$material) {
            $material['count'] *= $sampleRate;
        }
    }

    /**
     * Format response for frontend consumption
     */
    private function formatResponse(array $rawData, array $params): array
    {
        return [
            // Section A: Metadata
            'metadata' => $rawData['metadata'],

            // Section B: Time-series
            'time_series' => $rawData['time_series'],

            // Section C: Category breakdown (objects only, no brands/materials)
            'categories' => $rawData['categories'],

            // Section D: Top Litter Objects (with emojis)
            'top_objects' => array_slice($rawData['objects'], 0, 20),

            // Section E: Top Brands
            'top_brands' => array_slice($rawData['brands'], 0, 15),

            // Section F: Top Materials
            'top_materials' => array_slice($rawData['materials'], 0, 10),

            // Section G: Top Contributors
            'top_contributors' => array_slice($rawData['contributors'], 0, 10),

            // Section H: Export data
            'export' => [
                'available' => true,
                'formats' => ['csv', 'json', 'xlsx'],
            ],

            // Meta information
            'meta' => $rawData['meta'],
            'filters_applied' => $this->getAppliedFilters($params),
        ];
    }

    /**
     * Get list of applied filters
     */
    private function getAppliedFilters(array $params): array
    {
        $filterKeys = [
            'categories', 'litter_objects', 'materials', 'brands',
            'custom_tags', 'username', 'year', 'from', 'to',
            'country_id', 'state_id', 'city_id', 'bbox'
        ];

        return array_filter(
            array_intersect_key($params, array_flip($filterKeys)),
            fn($value) => !empty($value)
        );
    }
}



