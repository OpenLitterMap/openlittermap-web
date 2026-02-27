<?php

namespace App\Services\Points;

use App\Services\Points\Aggregators\MetadataAggregator;
use App\Services\Points\Aggregators\TimeSeriesAggregator;
use App\Services\Points\Aggregators\CategoryAggregator;
use App\Services\Points\Aggregators\ObjectAggregator;
use App\Services\Points\Aggregators\BrandAggregator;
use App\Services\Points\Aggregators\MaterialAggregator;
use App\Services\Points\Aggregators\ContributorAggregator;
use App\Services\Points\Aggregators\CustomTagAggregator;
use App\Services\Points\Builders\QueryBuilder;

class PointsStatsService
{
    private const MAX_RESULTS = 1000;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly MetadataAggregator $metadataAggregator,
        private readonly TimeSeriesAggregator $timeSeriesAggregator,
        private readonly CategoryAggregator $categoryAggregator,
        private readonly ObjectAggregator $objectAggregator,
        private readonly BrandAggregator $brandAggregator,
        private readonly MaterialAggregator $materialAggregator,
        private readonly ContributorAggregator $contributorAggregator,
        private readonly CustomTagAggregator $customTagAggregator
    ) {}

    /**
     * Get aggregated statistics for the given parameters
     */
    public function getStats(array $params): array
    {
        // Build base query
        $baseQuery = $this->queryBuilder->build($params);

        // Check if we have more than MAX_RESULTS
        $totalCount = (clone $baseQuery)->count();

        // Limit results for performance
        $photoIds = (clone $baseQuery)->limit(self::MAX_RESULTS)->pluck('id');

        // If no photos found, return empty stats
        if ($photoIds->isEmpty()) {
            return $this->emptyStats();
        }

        // Run all aggregations
        return [
            'counts' => $this->metadataAggregator->aggregate($photoIds),
            'by_category' => $this->categoryAggregator->aggregate($photoIds),
            'by_object' => $this->objectAggregator->aggregate($photoIds),
            'brands' => $this->brandAggregator->aggregate($photoIds),
            'materials' => $this->materialAggregator->aggregate($photoIds),
            'custom_tags' => $this->customTagAggregator->aggregate($photoIds),
            'top_contributors' => $this->contributorAggregator->aggregate($photoIds),
            'time_histogram' => $this->timeSeriesAggregator->aggregate($photoIds, $params),
            'meta' => [
                'truncated' => $totalCount > self::MAX_RESULTS,
                'max_results' => self::MAX_RESULTS,
            ],
        ];
    }

    /**
     * Return empty stats structure
     */
    private function emptyStats(): array
    {
        return [
            'counts' => [
                'photos' => 0,
                'users' => 0,
                'teams' => 0,
                'total_objects' => 0,
                'total_tags' => 0,
                'picked_up' => 0,
                'not_picked_up' => 0,
            ],
            'by_category' => [],
            'by_object' => [],
            'brands' => [],
            'materials' => [],
            'custom_tags' => [],
            'top_contributors' => [],
            'time_histogram' => [],
            'meta' => [
                'truncated' => false,
                'max_results' => self::MAX_RESULTS,
            ],
        ];
    }
}
