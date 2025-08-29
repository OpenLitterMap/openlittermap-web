<?php

namespace App\Services\Locations;

use App\Enums\LocationType;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Core performance optimizations:
 * - O(1) reads via denormalized totals
 * - ZSET-based pagination
 * - Pipeline operations
 * - Smart caching
 */
class LocationService
{
    /**
     * Get paginated locations
     */
    public function getLocations(LocationType $type, array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(
            config('locations.pagination.max_per_page'),
            (int) ($params['per_page'] ?? config('locations.pagination.default_per_page'))
        );
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

        // Add optional detailed data (for v1.1)
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
        return Cache::remember('global:stats:v1', config('locations.cache.ttl_short'), function () {
            $stats = $this->safeRedis(fn() => Redis::hgetall('{g}:stats'), []);
            $totalLitter = (int) ($stats['litter'] ?? 0);
            $totalPhotos = (int) ($stats['photos'] ?? 0);

            // Migration fallback
            if ($totalLitter === 0) {
                $totalLitter = $this->calculateTotalFromHash('{g}:t');
            }

            // Get contributors
            $contributors = $this->safeRedis(fn() => Redis::scard('{g}:users'), 0);
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
                'total_photos' => $totalPhotos ?: $this->countTotalPhotosFromDB(),
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
        $cacheKey = "tags:{$type->value}:{$id}:{$dimension}:{$limit}:v1";

        return Cache::remember($cacheKey, config('locations.cache.ttl_medium'), function () use ($scope, $dimension, $limit) {
            // Try ZSET first (fast path)
            $rankKey = "$scope:rank:$dimension";
            $topItems = $this->safeRedis(
                fn() => $this->zrangeWithScores($rankKey, 0, $limit - 1, true),
                []
            );

            if (empty($topItems)) {
                return $this->getTopTagsFromHash($scope, $dimension, $limit);
            }

            // Get totals
            $stats = $this->safeRedis(fn() => Redis::hgetall("$scope:stats"), []);
            $totalLitter = (int) ($stats['litter'] ?? 0);

            if ($totalLitter === 0) {
                $totalLitter = $this->calculateTotalFromHash("$scope:t");
            }

            $dimensionTotal = $this->calculateDimensionTotal($scope, $dimension);
            $denominator = ($dimension === 'objects') ? $totalLitter : $dimensionTotal;

            if ($denominator === 0) {
                return $this->emptyTagsResponse();
            }

            // Build result
            $items = [];
            $sumOfTop = 0;
            $names = $this->resolveTagNames($dimension, array_keys($topItems));

            foreach ($topItems as $id => $count) {
                if (!isset($names[$id])) continue;

                $count = (int) $count;
                $sumOfTop += $count;

                $items[] = [
                    'id' => (int) $id,
                    'name' => $names[$id],
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
        // Validate sort column
        if (!in_array($sortBy, config('locations.allowed_sort_columns'))) {
            $sortBy = 'created_at';
        }

        $model = $type->modelClass();
        $query = $model::with(['creator', 'lastUploader'])
            ->where('manual_verify', true);

        // Apply parent filter
        if ($parentId && $parentColumn = $type->parentColumn()) {
            $query->where($parentColumn, $parentId);
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
        $cacheKey = "breakdown:category:{$type->value}:{$id}:v1";

        return Cache::remember($cacheKey, config('locations.cache.ttl_long'), function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'categories', 'c');
        });
    }

    /**
     * Get object breakdown
     */
    private function getObjectBreakdown(LocationType $type, int $id): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "breakdown:object:{$type->value}:{$id}:v1";

        return Cache::remember($cacheKey, config('locations.cache.ttl_long'), function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'objects', 't', 20);
        });
    }

    /**
     * Get brand breakdown
     */
    private function getBrandBreakdown(LocationType $type, int $id): array
    {
        $scope = $type->scopePrefix($id);
        $cacheKey = "breakdown:brand:{$type->value}:{$id}:v1";

        return Cache::remember($cacheKey, config('locations.cache.ttl_long'), function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'brands', 'brands', 10);
        });
    }

    /**
     * Generic dimension breakdown
     */
    private function getDimensionBreakdown(string $scope, string $dimension, string $hashSuffix, int $limit = 0): array
    {
        $hashKey = "$scope:$hashSuffix";
        $items = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);

        if (empty($items)) {
            return [];
        }

        $stats = $this->safeRedis(fn() => Redis::hgetall("$scope:stats"), []);
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash("$scope:t");
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
     * Enrich single location
     */
    private function enrichLocationWithMetrics($location, LocationType $type, array $totals): void
    {
        $scope = $type->scopePrefix($location->id);

        $stats = $this->safeRedis(fn() => Redis::hgetall("$scope:stats"), []);
        $location->total_photos = (int) ($stats['photos'] ?? 0);
        $location->total_litter = (int) ($stats['litter'] ?? 0);

        if ($location->total_litter === 0) {
            $location->total_litter = $this->calculateTotalFromHash("$scope:t");
        }

        $location->total_contributors = $this->safeRedis(fn() => Redis::scard("$scope:users"), 0);

        if ($location->total_photos === 0) {
            $location->total_photos = $this->countPhotosForLocation($type, $location->id);
        }

        $location->percentage_litter = $totals['litter'] > 0
            ? round(($location->total_litter / $totals['litter']) * 100, 2)
            : 0;
        $location->percentage_photos = $totals['photos'] > 0
            ? round(($location->total_photos / $totals['photos']) * 100, 2)
            : 0;

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

        $scopes = [];
        foreach ($locations as $location) {
            $scopes[$location->id] = $type->scopePrefix($location->id);
        }

        // Pipeline Redis reads
        $bulk = $this->safeRedis(function() use ($scopes) {
            return Redis::pipeline(function($pipe) use ($scopes) {
                foreach ($scopes as $scope) {
                    $pipe->hGetAll("$scope:stats");
                    $pipe->sCard("$scope:users");
                }
            });
        }, array_fill(0, count($scopes) * 2, []));

        // Map results
        $i = 0;
        foreach ($locations as $location) {
            $stats = $bulk[$i * 2] ?? [];
            $contributors = $bulk[$i * 2 + 1] ?? 0;

            $location->total_photos = (int) ($stats['photos'] ?? 0);
            $location->total_litter = (int) ($stats['litter'] ?? 0);

            if ($location->total_litter === 0) {
                $scope = $scopes[$location->id];
                $location->total_litter = $this->calculateTotalFromHash("$scope:t");
            }

            $location->total_contributors = (int) $contributors;

            $location->percentage_litter = $totals['litter'] > 0
                ? round(($location->total_litter / $totals['litter']) * 100, 2)
                : 0;
            $location->percentage_photos = $totals['photos'] > 0
                ? round(($location->total_photos / $totals['photos']) * 100, 2)
                : 0;

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
            return $this->getScopeTotals($parentType->scopePrefix($parentId));
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
            return $this->getScopeTotals(LocationType::Country->scopePrefix($location->country_id));
        }

        if ($type === LocationType::City && $location->state_id) {
            return $this->getScopeTotals(LocationType::State->scopePrefix($location->state_id));
        }

        return $this->getGlobalTotals();
    }

    /**
     * Get scope totals
     */
    private function getScopeTotals(string $scope): array
    {
        $stats = $this->safeRedis(fn() => Redis::hGetAll("$scope:stats"), []);
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash("$scope:t");
        }

        return ['photos' => $photos, 'litter' => $litter];
    }

    /**
     * Get global totals
     */
    private function getGlobalTotals(): array
    {
        $stats = $this->safeRedis(fn() => Redis::hgetall('{g}:stats'), []);
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash('{g}:t');
        }

        return ['photos' => $photos, 'litter' => $litter];
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
            'objects' => "$scope:t",
            'categories' => "$scope:c",
            'materials' => "$scope:m",
            'brands' => "$scope:brands",
            default => "$scope:t"
        };

        return $this->calculateTotalFromHash($hashKey);
    }

    /**
     * Get top tags from hash (fallback)
     */
    private function getTopTagsFromHash(string $scope, string $dimension, int $limit): array
    {
        $hashKey = match($dimension) {
            'objects' => "$scope:t",
            'categories' => "$scope:c",
            'materials' => "$scope:m",
            'brands' => "$scope:brands",
            default => "$scope:t"
        };

        $allItems = $this->safeRedis(fn() => Redis::hgetall($hashKey), []);
        if (empty($allItems)) {
            return $this->emptyTagsResponse();
        }

        arsort($allItems);
        $topItems = array_slice($allItems, 0, $limit, true);

        $stats = $this->safeRedis(fn() => Redis::hgetall("$scope:stats"), []);
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash("$scope:t");
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
        $rankKey = "{g}:rank:$dimension";
        $topItems = $this->safeRedis(
            fn() => $this->zrangeWithScores($rankKey, 0, $limit - 1, true),
            []
        );

        if (empty($topItems)) {
            $hashKey = match($dimension) {
                'categories' => '{g}:c',
                'brands' => '{g}:brands',
                'materials' => '{g}:m',
                default => '{g}:t'
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
        $levels = config('locations.levels');
        $currentLevel = 0;

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
     * Get last upload date (single)
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
     * Count photos for location
     */
    private function countPhotosForLocation(LocationType $type, int $locationId): int
    {
        return DB::table('photos')
            ->where($type->dbColumn(), $locationId)
            ->whereNotNull('processed_at')
            ->count();
    }

    /**
     * Count total photos from DB
     */
    private function countTotalPhotosFromDB(): int
    {
        return DB::table('photos')
            ->whereNotNull('processed_at')
            ->count();
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
