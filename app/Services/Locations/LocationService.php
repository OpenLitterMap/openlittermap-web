<?php

namespace App\Services\Locations;

use App\Enums\LocationType;
use App\Services\Achievements\Tags\TagKeyCache;
use App\Services\Redis\RedisKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Location service updated for v5 metrics system
 * Now uses correct Redis keys and can optionally read from metrics table
 */
class LocationService
{
    /**
     * Get paginated locations
     */
    public function getLocations(LocationType $type, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, (int) ($params['per_page'] ?? 50));
        $sortBy = $params['sort_by'] ?? 'total_litter';
        $sortDir = in_array($params['sort_dir'] ?? 'desc', ['asc', 'desc']) ? $params['sort_dir'] : 'desc';
        $parentId = isset($params['parent_id']) ? (int) $params['parent_id'] : null;

        // Use ZSET-based pagination for Redis metrics
        if (in_array($sortBy, ['total_litter', 'total_photos'])) {
            return $this->getLocationsViaRanking($type, $parentId, $sortBy, $page, $perPage, $sortDir);
        }

        // Use database for other sorts
        return $this->getLocationsViaDatabase($type, $parentId, $sortBy, $page, $perPage, $sortDir);
    }

    /**
     * Get location details
     */
    public function getLocationDetails(LocationType $type, int $id, array $includes = [])
    {
        $model = $type->modelClass();
        $location = $model::with(['creator', 'lastUploader'])->findOrFail($id);

        // Get appropriate totals for percentage calculations
        $totals = $this->getTotalsForLocation($type, $location);

        // Add basic metrics
        $this->enrichLocationWithMetrics($location, $type, $totals);

        // Add optional detailed data
        if (in_array('breakdowns', $includes)) {
            $location->category_breakdown = $this->getCategoryBreakdown($type, $id);
            $location->object_breakdown = $this->getObjectBreakdown($type, $id);
            $location->brand_breakdown = $this->getBrandBreakdown($type, $id);
        }

        return $location;
    }

    /**
     * Get global statistics
     */
    public function getGlobalStats(): array
    {
        return Cache::remember('global:stats:v5', 300, function () {
            // Try metrics table first for accurate totals
            $metricsData = DB::table('metrics')
                ->where('timescale', 0) // All-time
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->first(['uploads', 'litter', 'xp']);

            if ($metricsData) {
                $totalLitter = (int) $metricsData->litter;
                $totalPhotos = (int) $metricsData->uploads;
                $totalXp = (int) $metricsData->xp;
            } else {
                // Fallback to Redis
                $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats('{g}')), []);
                $totalLitter = (int) ($stats['litter'] ?? 0);
                $totalPhotos = (int) ($stats['photos'] ?? 0);
                $totalXp = (int) ($stats['xp'] ?? 0);
            }

            // Get contributors from Redis HyperLogLog
            $contributors = $this->safeRedis(fn() => Redis::pfcount(RedisKeys::hll('{g}')), 0);
            if ($contributors === 0) {
                $contributors = DB::table('photos')
                    ->whereNotNull('processed_at')
                    ->distinct('user_id')
                    ->count('user_id');
            }

            // Get countries count
            $countryModel = LocationType::Country->modelClass();
            $countries = $countryModel::where('manual_verify', true)->count();

            $level = $this->calculateGlobalLevel($totalLitter);

            return [
                'total_litter' => $totalLitter,
                'total_photos' => $totalPhotos,
                'total_xp' => $totalXp,
                'total_contributors' => $contributors,
                'total_countries' => $countries,
                'level' => $level['level'],
                'current_xp' => $totalLitter,
                'previous_xp' => $level['previous_xp'],
                'next_xp' => $level['next_xp'],
                'progress' => $level['progress'],
                'top_categories' => $this->getTopGlobalDimension('categories', 5),
                'top_brands' => $this->getTopGlobalDimension('brands', 5),
            ];
        });
    }

    /**
     * Get top tags
     */
    public function getTopTags(LocationType $type, int $id, string $dimension = 'objects', int $limit = 20): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "tags:{$type->value}:{$id}:{$dimension}:{$limit}:v5";

        return Cache::remember($cacheKey, 600, function () use ($scope, $dimension, $limit) {
            // Try ZSET first (fast path)
            $rankKey = RedisKeys::ranking($scope, $dimension);
            $topItems = $this->safeRedis(
                fn() => $this->zrangeWithScores($rankKey, 0, $limit - 1, true),
                []
            );

            if (empty($topItems)) {
                return $this->getTopTagsFromHash($scope, $dimension, $limit);
            }

            // Get totals from metrics table or Redis
            $totalLitter = $this->getTotalLitterForScope($type, $id);
            $dimensionTotal = $this->calculateDimensionTotal($scope, $dimension);
            $denominator = ($dimension === 'objects') ? $totalLitter : $dimensionTotal;

            if ($denominator === 0) {
                return $this->emptyTagsResponse();
            }

            // Build result
            $items = [];
            $sumOfTop = 0;
            $names = $this->resolveTagNames($dimension, array_keys($topItems));

            foreach ($topItems as $tagId => $count) {
                if (!isset($names[$tagId])) continue;

                $count = (int) $count;
                $sumOfTop += $count;

                $items[] = [
                    'id' => (int) $tagId,
                    'name' => $names[$tagId],
                    'count' => $count,
                    'percentage' => round(($count / $denominator) * 100, 2)
                ];
            }

            $other = max(0, $dimensionTotal - $sumOfTop);

            return [
                'items' => $items,
                'total' => $totalLitter,
                'dimension_total' => $dimensionTotal,
                'other' => [
                    'count' => $other,
                    'percentage' => $denominator > 0 ? round(($other / $denominator) * 100, 2) : 0
                ]
            ];
        });
    }

    // ===== PRIVATE METHODS =====

    /**
     * Get locations via ZSET ranking
     */
    private function getLocationsViaRanking(LocationType $type, ?int $parentId, string $sortBy, int $page, int $perPage, string $sortDir): array
    {
        $offset = ($page - 1) * $perPage;
        $metric = $sortBy === 'total_litter' ? 'litter' : 'photos';
        $rankingKey = $type->parentRankingKey($parentId, $metric);

        $total = $this->safeRedis(fn() => Redis::zCard($rankingKey), 0);
        if ($total === 0) {
            return $this->emptyResponse($page, $perPage);
        }

        $results = $this->safeRedis(
            fn() => $this->zrangeWithScores($rankingKey, $offset, $offset + $perPage - 1, $sortDir === 'desc'),
            []
        );

        if (empty($results)) {
            return $this->emptyResponse($page, $perPage);
        }

        // Hydrate and enrich
        $locations = $this->hydrateLocations($type, array_keys($results));
        $sortedLocations = $this->sortByRanking($locations, $results, $metric);
        $totals = $this->getTotalsForList($type, $parentId);
        $this->enrichLocationsBatch($sortedLocations, $type, $totals);

        return [
            'locations' => $sortedLocations->values()->all(),
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => $perPage,
            ],
            'totals' => $totals,
        ];
    }

    /**
     * Get locations via database
     */
    private function getLocationsViaDatabase(LocationType $type, ?int $parentId, string $sortBy, int $page, int $perPage, string $sortDir): array
    {
        $model = $type->modelClass();
        $query = $model::with(['creator', 'lastUploader'])
            ->where('manual_verify', true);

        // Apply parent filter
        if ($parentId && $parentColumn = $type->parentColumn()) {
            $query->where($parentColumn, $parentId);
        }

        // Validate sort column
        $allowedColumns = ['created_at', 'updated_at', 'country', 'state', 'city'];
        if (!in_array($sortBy, $allowedColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $totals = $this->getTotalsForList($type, $parentId);
        $this->enrichLocationsBatch($paginated->getCollection(), $type, $totals);

        return [
            'locations' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ],
            'totals' => $totals,
        ];
    }

    /**
     * Get category breakdown
     */
    private function getCategoryBreakdown(LocationType $type, int $id): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "breakdown:category:{$type->value}:{$id}:v5";

        return Cache::remember($cacheKey, 3600, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'categories', 20);
        });
    }

    /**
     * Get object breakdown
     */
    private function getObjectBreakdown(LocationType $type, int $id): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "breakdown:object:{$type->value}:{$id}:v5";

        return Cache::remember($cacheKey, 3600, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'objects', 20);
        });
    }

    /**
     * Get brand breakdown
     */
    private function getBrandBreakdown(LocationType $type, int $id): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "breakdown:brand:{$type->value}:{$id}:v5";

        return Cache::remember($cacheKey, 3600, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'brands', 10);
        });
    }

    /**
     * Generic dimension breakdown
     */
    private function getDimensionBreakdown(string $scope, string $dimension, int $limit = 0): array
    {
        // Get the correct Redis hash key for this dimension
        $hashKey = match($dimension) {
            'categories' => RedisKeys::categories($scope),
            'objects' => RedisKeys::objects($scope),
            'materials' => RedisKeys::materials($scope),
            'brands' => RedisKeys::brands($scope),
            'custom_tags' => RedisKeys::customTags($scope),
            default => RedisKeys::objects($scope)
        };

        $items = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);

        if (empty($items)) {
            return [];
        }

        // Get total litter for percentages
        $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats($scope)), []);
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
        }

        arsort($items);
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit, true);
        }

        $ids = array_map('intval', array_keys($items));
        $names = $this->resolveTagNames($dimension, $ids);

        $breakdown = [];
        foreach ($items as $id => $count) {
            if (!isset($names[(int)$id])) continue;

            $breakdown[] = [
                'id' => (int) $id,
                'name' => $names[(int)$id],
                'count' => (int) $count,
                'percentage' => $totalLitter > 0 ? round(($count / $totalLitter) * 100, 2) : 0
            ];
        }

        return $breakdown;
    }

    /**
     * Hydrate location models
     */
    private function hydrateLocations(LocationType $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $model = $type->modelClass();
        return $model::with(['creator', 'lastUploader'])
            ->whereIn('id', $ids)
            ->where('manual_verify', true)
            ->get()
            ->keyBy('id');
    }

    /**
     * Sort by ranking scores
     */
    private function sortByRanking(Collection $locations, array $scores, string $metric): Collection
    {
        $sorted = collect();

        foreach (array_keys($scores) as $id) {
            if ($locations->has($id)) {
                $location = $locations->get($id);
                $location->rank_metric = $metric;
                $location->rank_score = (int) $scores[$id];
                $sorted->push($location);
            }
        }

        return $sorted;
    }

    /**
     * Enrich single location with metrics
     */
    private function enrichLocationWithMetrics($location, LocationType $type, array $totals): void
    {
        $scope = $type->scopePrefix($location->id);

        // Try metrics table first
        $metricsData = DB::table('metrics')
            ->where('timescale', 0) // All-time
            ->where('location_type', $type->value)
            ->where('location_id', $location->id)
            ->where('user_id', 0)
            ->first(['uploads', 'litter']);

        if ($metricsData) {
            $location->total_photos = (int) $metricsData->uploads;
            $location->total_litter = (int) $metricsData->litter;
        } else {
            // Fallback to Redis
            $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats($scope)), []);
            $location->total_photos = (int) ($stats['photos'] ?? 0);
            $location->total_litter = (int) ($stats['litter'] ?? 0);

            if ($location->total_litter === 0) {
                $location->total_litter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
            }
        }

        // Get contributor count from Redis
        $location->total_contributors = $this->safeRedis(
            fn() => Redis::zCard(RedisKeys::contributorRanking($scope)),
            0
        );

        // Calculate percentages
        $location->percentage_litter = $totals['litter'] > 0
            ? round(($location->total_litter / $totals['litter']) * 100, 2)
            : 0;
        $location->percentage_photos = $totals['photos'] > 0
            ? round(($location->total_photos / $totals['photos']) * 100, 2)
            : 0;

        // Calculate averages
        $location->avg_litter_per_user = $location->total_contributors > 0
            ? round($location->total_litter / $location->total_contributors, 2)
            : 0;
        $location->avg_photos_per_user = $location->total_contributors > 0
            ? round($location->total_photos / $location->total_contributors, 2)
            : 0;

        $location->last_uploaded_at = $this->getLastUploadDate($type, $location->id);
    }

    /**
     * Enrich multiple locations in batch
     */
    private function enrichLocationsBatch(Collection $locations, LocationType $type, array $totals): void
    {
        if ($locations->isEmpty()) {
            return;
        }

        $locationIds = $locations->pluck('id')->all();

        // Try to get metrics from database in batch
        $metricsData = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', $type->value)
            ->whereIn('location_id', $locationIds)
            ->where('user_id', 0)
            ->get(['location_id', 'uploads', 'litter'])
            ->keyBy('location_id');

        // Pipeline Redis reads for missing data
        $scopes = [];
        foreach ($locations as $location) {
            $scopes[$location->id] = $type->scopePrefix($location->id);
        }

        $bulk = $this->safeRedis(function() use ($scopes) {
            return Redis::pipeline(function($pipe) use ($scopes) {
                foreach ($scopes as $scope) {
                    $pipe->hGetAll(RedisKeys::stats($scope));
                    $pipe->zCard(RedisKeys::contributorRanking($scope));
                }
            });
        }, array_fill(0, count($scopes) * 2, []));

        // Map results
        $i = 0;
        foreach ($locations as $location) {
            // Use metrics table data if available
            if (isset($metricsData[$location->id])) {
                $location->total_photos = (int) $metricsData[$location->id]->uploads;
                $location->total_litter = (int) $metricsData[$location->id]->litter;
            } else {
                // Fallback to Redis
                $stats = $bulk[$i * 2] ?? [];
                $location->total_photos = (int) ($stats['photos'] ?? 0);
                $location->total_litter = (int) ($stats['litter'] ?? 0);

                if ($location->total_litter === 0) {
                    $scope = $scopes[$location->id];
                    $location->total_litter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
                }
            }

            $location->total_contributors = (int) ($bulk[$i * 2 + 1] ?? 0);

            // Calculate percentages
            $location->percentage_litter = $totals['litter'] > 0
                ? round(($location->total_litter / $totals['litter']) * 100, 2)
                : 0;
            $location->percentage_photos = $totals['photos'] > 0
                ? round(($location->total_photos / $totals['photos']) * 100, 2)
                : 0;

            // Calculate averages
            $location->avg_litter_per_user = $location->total_contributors > 0
                ? round($location->total_litter / $location->total_contributors, 2)
                : 0;
            $location->avg_photos_per_user = $location->total_contributors > 0
                ? round($location->total_photos / $location->total_contributors, 2)
                : 0;

            $i++;
        }

        // Batch last upload dates
        $this->batchLastUploadDates($locations, $type);
    }

    /**
     * Batch fetch last upload dates
     */
    private function batchLastUploadDates(Collection $locations, LocationType $type): void
    {
        $ids = $locations->pluck('id')->all();
        $column = $type->dbColumn();

        $lastUploads = DB::table('photos')
            ->select($column, DB::raw('MAX(created_at) as last_upload'))
            ->whereIn($column, $ids)
            ->whereNotNull('processed_at')
            ->groupBy($column)
            ->pluck('last_upload', $column);

        foreach ($locations as $location) {
            $location->last_uploaded_at = $lastUploads[$location->id] ?? null;
        }
    }

    /**
     * Get totals for list
     */
    private function getTotalsForList(LocationType $type, ?int $parentId): array
    {
        if ($parentId && $parentType = $type->parentType()) {
            return $this->getScopeTotals($parentType, $parentId);
        }

        return $this->getGlobalTotals();
    }

    /**
     * Get totals for specific location
     */
    private function getTotalsForLocation(LocationType $type, $location): array
    {
        if ($type === LocationType::Country) {
            return $this->getGlobalTotals();
        }

        if ($type === LocationType::State && $location->country_id) {
            return $this->getScopeTotals(LocationType::Country, $location->country_id);
        }

        if ($type === LocationType::City && $location->state_id) {
            return $this->getScopeTotals(LocationType::State, $location->state_id);
        }

        return $this->getGlobalTotals();
    }

    /**
     * Get scope totals
     */
    private function getScopeTotals(LocationType $type, int $locationId): array
    {
        // Try metrics table first
        $metricsData = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', $type->value)
            ->where('location_id', $locationId)
            ->where('user_id', 0)
            ->first(['uploads', 'litter']);

        if ($metricsData) {
            return [
                'photos' => (int) $metricsData->uploads,
                'litter' => (int) $metricsData->litter
            ];
        }

        // Fallback to Redis
        $scope = $type->scopePrefix($locationId);
        $stats = $this->safeRedis(fn() => Redis::hGetAll(RedisKeys::stats($scope)), []);
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
        }

        return ['photos' => $photos, 'litter' => $litter];
    }

    /**
     * Get global totals
     */
    private function getGlobalTotals(): array
    {
        // Try metrics table first
        $metricsData = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->first(['uploads', 'litter']);

        if ($metricsData) {
            return [
                'photos' => (int) $metricsData->uploads,
                'litter' => (int) $metricsData->litter
            ];
        }

        // Fallback to Redis
        $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats('{g}')), []);
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash(RedisKeys::objects('{g}'));
        }

        return ['photos' => $photos, 'litter' => $litter];
    }

    /**
     * Get total litter for a scope
     */
    private function getTotalLitterForScope(LocationType $type, int $locationId): int
    {
        // Try metrics table first
        $metricsData = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', $type->value)
            ->where('location_id', $locationId)
            ->where('user_id', 0)
            ->value('litter');

        if ($metricsData !== null) {
            return (int) $metricsData;
        }

        // Fallback to Redis
        $scope = $type->scopePrefix($locationId);
        $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats($scope)), []);
        $litter = (int) ($stats['litter'] ?? 0);

        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
        }

        return $litter;
    }

    /**
     * Calculate total from hash
     */
    private function calculateTotalFromHash(string $hashKey): int
    {
        $items = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);
        return array_sum(array_map('intval', $items));
    }

    /**
     * Calculate dimension total
     */
    private function calculateDimensionTotal(string $scope, string $dimension): int
    {
        $hashKey = match($dimension) {
            'objects' => RedisKeys::objects($scope),
            'categories' => RedisKeys::categories($scope),
            'materials' => RedisKeys::materials($scope),
            'brands' => RedisKeys::brands($scope),
            'custom_tags' => RedisKeys::customTags($scope),
            default => RedisKeys::objects($scope)
        };

        return $this->calculateTotalFromHash($hashKey);
    }

    /**
     * Get top tags from hash (fallback when ZSET is empty)
     */
    private function getTopTagsFromHash(string $scope, string $dimension, int $limit): array
    {
        $hashKey = match($dimension) {
            'objects' => RedisKeys::objects($scope),
            'categories' => RedisKeys::categories($scope),
            'materials' => RedisKeys::materials($scope),
            'brands' => RedisKeys::brands($scope),
            'custom_tags' => RedisKeys::customTags($scope),
            default => RedisKeys::objects($scope)
        };

        $allItems = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);
        if (empty($allItems)) {
            return $this->emptyTagsResponse();
        }

        arsort($allItems);
        $topItems = array_slice($allItems, 0, $limit, true);

        $stats = $this->safeRedis(fn() => Redis::hgetall(RedisKeys::stats($scope)), []);
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash(RedisKeys::objects($scope));
        }

        $dimensionTotal = array_sum(array_map('intval', $allItems));
        $denominator = ($dimension === 'objects') ? $totalLitter : $dimensionTotal;

        if ($denominator === 0) {
            return $this->emptyTagsResponse();
        }

        $ids = array_map('intval', array_keys($topItems));
        $names = $this->resolveTagNames($dimension, $ids);

        $items = [];
        $sumOfTop = 0;

        foreach ($topItems as $id => $count) {
            if (!isset($names[(int)$id])) continue;

            $count = (int) $count;
            $sumOfTop += $count;

            $items[] = [
                'id' => (int) $id,
                'name' => $names[(int)$id],
                'count' => $count,
                'percentage' => round(($count / $denominator) * 100, 2)
            ];
        }

        $other = max(0, $dimensionTotal - $sumOfTop);

        return [
            'items' => $items,
            'total' => $totalLitter,
            'dimension_total' => $dimensionTotal,
            'other' => [
                'count' => $other,
                'percentage' => $denominator > 0 ? round(($other / $denominator) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get top global dimension
     */
    private function getTopGlobalDimension(string $dimension, int $limit): array
    {
        $rankKey = RedisKeys::ranking('{g}', $dimension);
        $topItems = $this->safeRedis(
            fn() => $this->zrangeWithScores($rankKey, 0, $limit - 1, true),
            []
        );

        if (empty($topItems)) {
            $hashKey = match($dimension) {
                'categories' => RedisKeys::categories('{g}'),
                'brands' => RedisKeys::brands('{g}'),
                'materials' => RedisKeys::materials('{g}'),
                default => RedisKeys::objects('{g}')
            };

            $items = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);
            if (empty($items)) {
                return [];
            }

            arsort($items);
            $topItems = array_slice($items, 0, $limit, true);
        }

        $ids = array_map('intval', array_keys($topItems));
        $names = $this->resolveTagNames($dimension, $ids);

        $breakdown = [];
        foreach ($topItems as $id => $count) {
            if (isset($names[(int)$id])) {
                $breakdown[] = [
                    'name' => $names[(int)$id],
                    'count' => (int) $count
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Resolve tag names
     */
    private function resolveTagNames(string $dimension, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $dimensionMap = [
            'objects' => 'object',
            'categories' => 'category',
            'materials' => 'material',
            'brands' => 'brand',
            'custom_tags' => 'custom_tag',
        ];

        $tagDimension = $dimensionMap[$dimension] ?? null;
        if (!$tagDimension) {
            return [];
        }

        $names = [];
        $keys = TagKeyCache::getKeysForIds($tagDimension, $ids);

        foreach ($ids as $id) {
            $names[$id] = $keys[$id] ?? null;
        }

        return $names;
    }

    /**
     * Calculate global level
     */
    private function calculateGlobalLevel(int $totalLitter): array
    {
        // Simple level calculation based on litter count
        $levels = [
            1 => ['min' => 0, 'max' => 1000],
            2 => ['min' => 1000, 'max' => 5000],
            3 => ['min' => 5000, 'max' => 10000],
            4 => ['min' => 10000, 'max' => 25000],
            5 => ['min' => 25000, 'max' => 50000],
            6 => ['min' => 50000, 'max' => 100000],
            7 => ['min' => 100000, 'max' => 250000],
            8 => ['min' => 250000, 'max' => 500000],
            9 => ['min' => 500000, 'max' => 1000000],
            10 => ['min' => 1000000, 'max' => PHP_INT_MAX],
        ];

        $currentLevel = 1;
        foreach ($levels as $level => $range) {
            if ($totalLitter >= $range['min'] && $totalLitter < $range['max']) {
                $currentLevel = $level;
                break;
            }
        }

        $prev = $levels[$currentLevel]['min'];
        $next = $levels[$currentLevel]['max'];

        return [
            'level' => $currentLevel,
            'previous_xp' => $prev,
            'next_xp' => $next === PHP_INT_MAX ? $totalLitter + 1000000 : $next,
            'progress' => $next > $prev ? round((($totalLitter - $prev) / ($next - $prev)) * 100, 2) : 100,
        ];
    }

    /**
     * Get last upload date for a location
     */
    private function getLastUploadDate(LocationType $type, int $locationId)
    {
        $column = $type->dbColumn();

        $lastPhoto = DB::table('photos')
            ->where($column, $locationId)
            ->whereNotNull('processed_at')
            ->orderBy('created_at', 'desc')
            ->first(['created_at']);

        return $lastPhoto ? $lastPhoto->created_at : null;
    }

    /**
     * Cross-client ZSET range with scores
     */
    private function zrangeWithScores(string $key, int $start, int $stop, bool $reverse = false): array
    {
        $connection = Redis::connection();

        if ($connection->client() instanceof \Redis) {
            return $reverse
                ? Redis::zRevRange($key, $start, $stop, true)
                : Redis::zRange($key, $start, $stop, true);
        } else {
            return $reverse
                ? Redis::zrevrange($key, $start, $stop, ['withscores' => true])
                : Redis::zrange($key, $start, $stop, ['withscores' => true]);
        }
    }

    /**
     * Safe Redis operation wrapper
     */
    private function safeRedis(callable $operation, $fallback = null)
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            Log::error('Redis operation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($fallback !== null) {
                return $fallback;
            }

            throw $e;
        }
    }

    /**
     * Empty response helpers
     */
    private function emptyResponse(int $page, int $perPage): array
    {
        return [
            'locations' => [],
            'pagination' => [
                'total' => 0,
                'current_page' => $page,
                'last_page' => 1,
                'per_page' => $perPage,
            ],
            'totals' => ['photos' => 0, 'litter' => 0],
        ];
    }

    private function emptyTagsResponse(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'dimension_total' => 0,
            'other' => ['count' => 0, 'percentage' => 0]
        ];
    }
}
