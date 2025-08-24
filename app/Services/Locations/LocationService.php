<?php

namespace App\Services\Locations;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * LocationService - Production-ready location data service
 *
 * Provides fast, accurate location statistics with:
 * - O(1) reads via denormalized totals
 * - Correct global ordering via ZSET-based pagination
 * - Parent-scoped percentages for accurate comparisons
 * - Pipeline-optimized batch operations
 * - Dimension-aware percentage calculations
 * - Cross-client Redis compatibility
 */
class LocationService
{
    // Cache TTLs
    private const CACHE_TTL_SHORT = 300;      // 5 minutes
    private const CACHE_TTL_MEDIUM = 1800;    // 30 minutes
    private const CACHE_TTL_LONG = 3600;      // 1 hour

    // Pagination limits
    private const MAX_PER_PAGE = 100;
    private const DEFAULT_PER_PAGE = 50;

    /**
     * Get paginated locations with proper sorting
     */
    public function getLocations(string $type = 'country', array $params = [])
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(self::MAX_PER_PAGE, (int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE));
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
     * Get location details with optional includes
     */
    public function getLocationDetails(string $type, int $id, array $includes = [])
    {
        $model = $this->getLocationModel($type);
        $location = $model::with(['creator', 'lastUploader'])->findOrFail($id);

        // Get appropriate totals for percentage calculations
        $totals = $this->getTotalsForLocation($type, $location);

        // Add basic metrics
        $this->enrichLocationWithMetrics($location, $type, $totals);

        // Add optional detailed data
        if (empty($includes) || in_array('breakdowns', $includes)) {
            $location->category_breakdown = $this->getCategoryBreakdown($type, $id);
            $location->object_breakdown = $this->getObjectBreakdown($type, $id);
            $location->brand_breakdown = $this->getBrandBreakdown($type, $id);
        }

        if (empty($includes) || in_array('timeseries', $includes)) {
            $location->time_series = $this->getTimeSeries($type, $id);
        }

        if (empty($includes) || in_array('leaderboard', $includes)) {
            $location->leaderboard = $this->getLeaderboard($type, $id);
        }

        if (empty($includes) || in_array('activity', $includes)) {
            $location->recent_activity = $this->getRecentActivity($type, $id);
        }

        return $location;
    }

    /**
     * Get locations using ZSET rankings for correct global ordering
     */
    private function getLocationsViaRanking(string $type, ?int $parentId, string $sortBy, int $page, int $perPage, string $sortDir)
    {
        $offset = ($page - 1) * $perPage;
        $metric = $sortBy === 'total_litter' ? 'litter' : 'photos';

        // Get the appropriate ranking ZSET
        $rankingKey = $this->getRankingKey($type, $parentId, $metric);

        // Get total count
        $total = Redis::zCard($rankingKey);

        if ($total === 0) {
            return $this->emptyResponse($page, $perPage);
        }

        // Get page of IDs with scores
        $results = $this->zrangeWithScores($rankingKey, $offset, $offset + $perPage - 1, $sortDir === 'desc');

        if (empty($results)) {
            return $this->emptyResponse($page, $perPage);
        }

        // Hydrate models
        $locations = $this->hydrateLocations($type, array_keys($results));

        // Sort by ranking order and add rank scores
        $sortedLocations = $this->sortByRanking($locations, $results, $metric);

        // Get appropriate totals (parent-scoped if applicable)
        $totals = $this->getTotalsForList($type, $parentId);

        // Enrich all locations in batch
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
     * Get locations via database query
     */
    private function getLocationsViaDatabase(string $type, ?int $parentId, string $sortBy, int $page, int $perPage, string $sortDir)
    {
        $model = $this->getLocationModel($type);

        $query = $model::with(['creator', 'lastUploader'])
            ->where('manual_verify', true);

        // Apply parent filter
        if ($parentId) {
            if ($type === 'state') $query->where('country_id', $parentId);
            if ($type === 'city') $query->where('state_id', $parentId);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDir);

        // Paginate
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Get appropriate totals
        $totals = $this->getTotalsForList($type, $parentId);

        // Enrich with Redis data
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
    public function getCategoryBreakdown(string $type, int $id)
    {
        $scope = $this->getLocationScope($type, $id);
        $cacheKey = "breakdown:category:{$type}:{$id}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_LONG, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'categories', 'category', 'c');
        });
    }

    /**
     * Get object breakdown
     */
    public function getObjectBreakdown(string $type, int $id)
    {
        $scope = $this->getLocationScope($type, $id);
        $cacheKey = "breakdown:object:{$type}:{$id}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_LONG, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'objects', 'object', 't', 20);
        });
    }

    /**
     * Get brand breakdown
     */
    public function getBrandBreakdown(string $type, int $id)
    {
        $scope = $this->getLocationScope($type, $id);
        $cacheKey = "breakdown:brand:{$type}:{$id}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_LONG, function () use ($scope) {
            return $this->getDimensionBreakdown($scope, 'brands', 'brand', 'brands', 10);
        });
    }

    /**
     * Get time series data
     */
    public function getTimeSeries(string $type, int $id, string $period = 'daily')
    {
        $scope = $this->getLocationScope($type, $id);
        $cacheKey = "timeseries:{$type}:{$id}:{$period}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function () use ($scope, $period) {
            $tsKey = "$scope:t:p";
            $data = Redis::hgetall($tsKey);

            if (empty($data)) {
                return [];
            }

            // Convert to array of date/count pairs
            $series = [];
            foreach ($data as $date => $count) {
                $series[] = [
                    'date' => $date,
                    'photos' => (int) $count
                ];
            }

            // Sort by date
            usort($series, fn($a, $b) => strcmp($a['date'], $b['date']));

            // Group by period if needed
            if ($period === 'weekly') {
                $series = $this->groupTimeSeriesByWeek($series);
            } elseif ($period === 'monthly') {
                $series = $this->groupTimeSeriesByMonth($series);
            }

            // Limit to last year
            $cutoff = now('UTC')->subYear()->format('Y-m-d');
            $series = array_filter($series, fn($item) => $item['date'] >= $cutoff);

            return array_values($series);
        });
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(string $type, int $id, string $period = 'all_time')
    {
        $column = $this->getLocationColumn($type);
        $cacheKey = "leaderboard:{$type}:{$id}:{$period}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_SHORT, function () use ($column, $id, $period) {
            $query = DB::table('photos')
                ->join('users', 'photos.user_id', '=', 'users.id')
                ->where("photos.{$column}", $id)
                ->whereNotNull('photos.processed_at')
                ->select(
                    'users.id',
                    'users.name',
                    'users.username',
                    'users.show_name',
                    'users.show_username',
                    DB::raw('COUNT(photos.id) as photo_count'),
                    DB::raw('COALESCE(SUM(photos.xp), 0) as total_xp')
                )
                ->groupBy('users.id', 'users.name', 'users.username', 'users.show_name', 'users.show_username');

            // Apply time filter
            $this->applyTimeFilter($query, $period);

            // Get top users
            $leaders = $query->orderByDesc('total_xp')->limit(10)->get();

            // Enrich with litter counts
            return $this->enrichLeaderboard($leaders);
        });
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(string $type, int $id, int $days = 7): array
    {
        $scope = $this->getLocationScope($type, $id);
        $tsKey = "$scope:t:p";

        // Build date list
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = now('UTC')->subDays($i)->format('Y-m-d');
        }

        // Pipeline all reads
        $counts = Redis::pipeline(function($pipe) use ($tsKey, $dates) {
            foreach ($dates as $date) {
                $pipe->hGet($tsKey, $date);
            }
        });

        // Build result
        $activity = [];
        $total = 0;

        foreach ($dates as $i => $date) {
            $count = (int) ($counts[$i] ?? 0);
            $activity[] = [
                'date' => $date,
                'count' => $count
            ];
            $total += $count;
        }

        return [
            'days' => $activity,
            'total' => $total,
            'average' => $days > 0 ? round($total / $days, 2) : 0
        ];
    }

    /**
     * Get global statistics
     */
    public function getGlobalStats()
    {
        return Cache::remember('global:stats:v1', self::CACHE_TTL_SHORT, function () {
            // Get denormalized totals in O(1)
            $stats = Redis::hgetall('{g}:stats');
            $totalLitter = (int) ($stats['litter'] ?? 0);
            $totalPhotos = (int) ($stats['photos'] ?? 0);

            // Migration fallback
            if ($totalLitter === 0) {
                $totalLitter = $this->calculateTotalFromHash('{g}:t');
            }

            // Get countries count
            $countries = Country::where('manual_verify', true)->count();

            // Get global contributors
            $contributors = DB::table('photos')
                ->whereNotNull('processed_at')
                ->distinct('user_id')
                ->count('user_id');

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
     * Get top tags with correct percentages
     */
    public function getTopTags(string $type, int $id, string $dimension = 'objects', int $limit = 20)
    {
        $scope = $this->getLocationScope($type, $id);
        $cacheKey = "tags:{$type}:{$id}:{$dimension}:{$limit}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_MEDIUM, function () use ($scope, $dimension, $limit) {
            // Try ZSET first (fast path)
            $rankKey = "$scope:rank:$dimension";
            $topItems = $this->zrangeWithScores($rankKey, 0, $limit - 1, true);

            if (empty($topItems)) {
                // Fallback to hash
                return $this->getTopTagsFromHash($scope, $dimension, $limit);
            }

            // Get totals for percentages
            $stats = Redis::hgetall("$scope:stats");
            $totalLitter = (int) ($stats['litter'] ?? 0);

            if ($totalLitter === 0) {
                $totalLitter = $this->calculateTotalFromHash("$scope:t");
            }

            // Calculate dimension total for correct percentages
            $dimensionTotal = $this->calculateDimensionTotal($scope, $dimension);

            // Use appropriate denominator
            $denominator = ($dimension === 'objects') ? $totalLitter : $dimensionTotal;

            if ($denominator === 0) {
                return $this->emptyTagsResponse();
            }

            // Build result
            $items = [];
            $sumOfTop = 0;

            // Batch resolve tag names
            $ids = array_keys($topItems);
            $names = $this->resolveTagNames($dimension, $ids);

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

            // Calculate "other" bucket
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

    /**
     * Get tag summary
     */
    public function getTagSummary(string $type, int $id)
    {
        $cacheKey = "summary:{$type}:{$id}:v1";

        return Cache::remember($cacheKey, self::CACHE_TTL_SHORT, function () use ($type, $id) {
            return [
                'top_objects' => $this->getTopTags($type, $id, 'objects', 10),
                'top_brands' => $this->getTopTags($type, $id, 'brands', 5),
                'top_materials' => $this->getTopTags($type, $id, 'materials', 5),
                'cleanup_stats' => $this->getCleanupStats($type, $id),
                'categories' => $this->getCategoryBreakdown($type, $id),
                'recent_activity' => $this->getRecentActivity($type, $id)
            ];
        });
    }

    /**
     * Get cleanup statistics
     */
    public function getCleanupStats(string $type, int $id)
    {
        $scope = $this->getLocationScope($type, $id);
        $stats = Redis::hgetall("$scope:stats");

        $totalLitter = (int) ($stats['litter'] ?? 0);
        $pickedUp = (int) ($stats['picked_up'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash("$scope:t");
        }

        if ($totalLitter === 0) {
            return [
                'cleanup_rate' => 0,
                'total_picked_up' => 0,
                'total_litter' => 0,
                'remaining' => 0
            ];
        }

        return [
            'cleanup_rate' => round(($pickedUp / $totalLitter) * 100, 2),
            'total_picked_up' => $pickedUp,
            'total_litter' => $totalLitter,
            'remaining' => $totalLitter - $pickedUp
        ];
    }

    // ===== HELPER METHODS =====

    /**
     * Get location model class
     */
    private function getLocationModel(string $type)
    {
        return match($type) {
            'country' => Country::class,
            'state' => State::class,
            'city' => City::class,
            default => Country::class,
        };
    }

    /**
     * Get location scope with hash-tag
     */
    private function getLocationScope(string $type, int $id): string
    {
        return match($type) {
            'country' => "{c:$id}",
            'state' => "{s:$id}",
            'city' => "{ci:$id}",
            default => "{c:$id}"
        };
    }

    /**
     * Get ranking ZSET key
     */
    private function getRankingKey(string $type, ?int $parentId, string $metric): string
    {
        return match($type) {
            'country' => "{g}:rank:c:$metric",
            'state' => $parentId ? "{c:$parentId}:rank:s:$metric" : "{g}:rank:s:$metric",
            'city' => $parentId ? "{s:$parentId}:rank:ci:$metric" : "{g}:rank:ci:$metric",
            default => "{g}:rank:c:$metric"
        };
    }

    /**
     * Get location column for database queries
     */
    private function getLocationColumn(string $type): string
    {
        return match($type) {
            'country' => 'country_id',
            'state' => 'state_id',
            'city' => 'city_id',
            default => 'country_id'
        };
    }

    /**
     * Get totals for list (parent-scoped if applicable)
     */
    private function getTotalsForList(string $type, ?int $parentId): array
    {
        if ($type === 'state' && $parentId) {
            return $this->getScopeTotals("{c:$parentId}");
        }

        if ($type === 'city' && $parentId) {
            return $this->getScopeTotals("{s:$parentId}");
        }

        return $this->getGlobalTotals();
    }

    /**
     * Get totals for a specific location
     */
    private function getTotalsForLocation(string $type, $location): array
    {
        // For countries, use global totals
        if ($type === 'country') {
            return $this->getGlobalTotals();
        }

        // For states, use country totals
        if ($type === 'state' && $location->country_id) {
            return $this->getScopeTotals("{c:{$location->country_id}}");
        }

        // For cities, use state totals
        if ($type === 'city' && $location->state_id) {
            return $this->getScopeTotals("{s:{$location->state_id}}");
        }

        return $this->getGlobalTotals();
    }

    /**
     * Get scope totals
     */
    private function getScopeTotals(string $scope): array
    {
        $stats = Redis::hGetAll("$scope:stats");
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        // Migration fallback
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
        $stats = Redis::hgetall('{g}:stats');
        $photos = (int) ($stats['photos'] ?? 0);
        $litter = (int) ($stats['litter'] ?? 0);

        // Migration fallback
        if ($litter === 0) {
            $litter = $this->calculateTotalFromHash('{g}:t');
        }

        return ['photos' => $photos, 'litter' => $litter];
    }

    /**
     * Cross-client compatible ZSET range with scores
     */
    private function zrangeWithScores(string $key, int $start, int $stop, bool $reverse = false): array
    {
        $connection = Redis::connection();

        if ($connection->client() instanceof \Redis) {
            // PhpRedis
            return $reverse
                ? Redis::zRevRange($key, $start, $stop, true)
                : Redis::zRange($key, $start, $stop, true);
        } else {
            // Predis
            return $reverse
                ? Redis::zrevrange($key, $start, $stop, ['withscores' => true])
                : Redis::zrange($key, $start, $stop, ['withscores' => true]);
        }
    }

    /**
     * Calculate total from hash (migration fallback)
     */
    private function calculateTotalFromHash(string $hashKey): int
    {
        $items = Redis::hgetall($hashKey);
        $total = 0;

        foreach ($items as $count) {
            $total += (int) $count;
        }

        return $total;
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
     * Hydrate location models
     */
    private function hydrateLocations(string $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $model = $this->getLocationModel($type);

        return $model::with(['creator', 'lastUploader'])
            ->whereIn('id', $ids)
            ->where('manual_verify', true)
            ->get()
            ->keyBy('id');
    }

    /**
     * Sort locations by ranking and add scores
     */
    private function sortByRanking(Collection $locations, array $scores, string $metric): Collection
    {
        $sorted = collect();

        foreach (array_keys($scores) as $id) {
            if ($locations->has($id)) {
                $location = $locations->get($id);
                // Keep rank score separate from actual totals
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
    private function enrichLocationWithMetrics($location, string $type, array $totals): void
    {
        $scope = $this->getLocationScope($type, $location->id);

        // Get stats in O(1)
        $stats = Redis::hgetall("$scope:stats");
        $location->total_photos = (int) ($stats['photos'] ?? 0);
        $location->total_litter = (int) ($stats['litter'] ?? 0);

        // Migration fallback
        if ($location->total_litter === 0) {
            $location->total_litter = $this->calculateTotalFromHash("$scope:t");
        }

        // Get contributors
        $location->total_contributors = Redis::scard("$scope:users");

        // Database fallback
        if ($location->total_photos === 0) {
            $location->total_photos = $this->countPhotosForLocation($type, $location->id);
        }

        // Calculate percentages with correct totals
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

        // Get last upload date
        $location->last_uploaded_at = $this->getLastUploadDate($type, $location->id);
    }

    /**
     * Enrich multiple locations in batch (optimized)
     */
    private function enrichLocationsBatch(Collection $locations, string $type, array $totals): void
    {
        if ($locations->isEmpty()) {
            return;
        }

        // Prepare scopes
        $scopes = [];
        foreach ($locations as $location) {
            $scopes[$location->id] = $this->getLocationScope($type, $location->id);
        }

        // Pipeline all Redis reads
        $bulk = Redis::pipeline(function($pipe) use ($scopes) {
            foreach ($scopes as $scope) {
                $pipe->hGetAll("$scope:stats");
                $pipe->sCard("$scope:users");
            }
        });

        // Map results back to locations
        $i = 0;
        foreach ($locations as $location) {
            $stats = $bulk[$i * 2] ?? [];
            $contributors = $bulk[$i * 2 + 1] ?? 0;

            // Don't overwrite rank_score if it exists
            if (!isset($location->rank_score)) {
                $location->total_photos = (int) ($stats['photos'] ?? 0);
                $location->total_litter = (int) ($stats['litter'] ?? 0);

                // Migration fallback
                if ($location->total_litter === 0) {
                    $scope = $scopes[$location->id];
                    $location->total_litter = $this->calculateTotalFromHash("$scope:t");
                }
            }

            $location->total_contributors = (int) $contributors;

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

        // Get last upload dates (could be pipelined if needed)
        foreach ($locations as $location) {
            $location->last_uploaded_at = $this->getLastUploadDate($type, $location->id);
        }
    }

    /**
     * Get dimension breakdown (generic)
     */
    private function getDimensionBreakdown(string $scope, string $dimension, string $tagDimension, string $hashSuffix, int $limit = 0): array
    {
        $hashKey = "$scope:$hashSuffix";
        $items = Redis::hgetall($hashKey);

        if (empty($items)) {
            return [];
        }

        // Get total for percentages
        $stats = Redis::hgetall("$scope:stats");
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash("$scope:t");
        }

        // Sort by count
        arsort($items);

        // Apply limit if specified
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit, true);
        }

        // Batch resolve names
        $ids = array_map('intval', array_keys($items));
        $names = $this->resolveTagNames($dimension, $ids);

        // Build breakdown
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
     * Batch resolve tag names
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
            'custom' => 'customTag',
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
     * Get top tags from hash (fallback when ZSETs unavailable)
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

        $allItems = Redis::hgetall($hashKey);
        if (empty($allItems)) {
            return $this->emptyTagsResponse();
        }

        // Sort and limit
        arsort($allItems);
        $topItems = array_slice($allItems, 0, $limit, true);

        // Get totals
        $stats = Redis::hgetall("$scope:stats");
        $totalLitter = (int) ($stats['litter'] ?? 0);

        if ($totalLitter === 0) {
            $totalLitter = $this->calculateTotalFromHash("$scope:t");
        }

        $dimensionTotal = array_sum(array_map('intval', $allItems));
        $denominator = ($dimension === 'objects') ? $totalLitter : $dimensionTotal;

        if ($denominator === 0) {
            return $this->emptyTagsResponse();
        }

        // Batch resolve names
        $ids = array_map('intval', array_keys($topItems));
        $names = $this->resolveTagNames($dimension, $ids);

        // Build result
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
     * Get top global dimension items
     */
    private function getTopGlobalDimension(string $dimension, int $limit): array
    {
        // Try ZSET first
        $rankKey = "{g}:rank:$dimension";
        $topItems = $this->zrangeWithScores($rankKey, 0, $limit - 1, true);

        if (empty($topItems)) {
            // Fallback to hash
            $hashKey = match($dimension) {
                'categories' => '{g}:c',
                'brands' => '{g}:brands',
                'materials' => '{g}:m',
                default => '{g}:t'
            };

            $items = Redis::hgetall($hashKey);
            if (empty($items)) {
                return [];
            }

            arsort($items);
            $topItems = array_slice($items, 0, $limit, true);
        }

        // Batch resolve names
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
     * Apply time filter to query
     */
    private function applyTimeFilter($query, string $period): void
    {
        switch ($period) {
            case 'today':
                $query->whereDate('photos.created_at', today('UTC'));
                break;
            case 'this_week':
                $query->whereBetween('photos.created_at', [
                    now('UTC')->startOfWeek(),
                    now('UTC')->endOfWeek()
                ]);
                break;
            case 'this_month':
                $query->whereMonth('photos.created_at', now('UTC')->month)
                    ->whereYear('photos.created_at', now('UTC')->year);
                break;
            case 'this_year':
                $query->whereYear('photos.created_at', now('UTC')->year);
                break;
        }
    }

    /**
     * Enrich leaderboard with litter counts
     */
    private function enrichLeaderboard(Collection $leaders): array
    {
        return $leaders->map(function ($user) {
            $display = 'Anonymous';
            if ($user->show_name && $user->name) {
                $display = $user->name;
            } elseif ($user->show_username && $user->username) {
                $display = $user->username;
            }

            // Get user's litter total (O(1) with denormalized stats)
            $stats = Redis::hget("{u:{$user->id}}:stats", 'litter');
            $totalLitter = $stats !== null ? (int) $stats : $this->calculateUserLitter($user->id);

            return [
                'user_id' => $user->id,
                'display_name' => $display,
                'photo_count' => $user->photo_count,
                'total_xp' => (int) $user->total_xp,
                'total_litter' => $totalLitter,
            ];
        })->toArray();
    }

    /**
     * Calculate user litter (fallback)
     */
    private function calculateUserLitter(int $userId): int
    {
        $objects = Redis::hgetall("{u:$userId}:t");
        $total = 0;

        foreach ($objects as $count) {
            $total += (int) $count;
        }

        // Store for next time
        if ($total > 0) {
            Redis::hset("{u:$userId}:stats", 'litter', $total);
        }

        return $total;
    }

    /**
     * Group time series by week
     */
    private function groupTimeSeriesByWeek(array $series): array
    {
        $grouped = [];

        foreach ($series as $item) {
            $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($item['date'])));
            if (!isset($grouped[$weekStart])) {
                $grouped[$weekStart] = [
                    'date' => $weekStart,
                    'photos' => 0
                ];
            }
            $grouped[$weekStart]['photos'] += $item['photos'];
        }

        return array_values($grouped);
    }

    /**
     * Group time series by month
     */
    private function groupTimeSeriesByMonth(array $series): array
    {
        $grouped = [];

        foreach ($series as $item) {
            $month = substr($item['date'], 0, 7) . '-01';
            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'date' => $month,
                    'photos' => 0
                ];
            }
            $grouped[$month]['photos'] += $item['photos'];
        }

        return array_values($grouped);
    }

    /**
     * Calculate global level
     */
    private function calculateGlobalLevel(int $totalLitter): array
    {
        $levels = [
            0 => ['min' => 0, 'max' => 1000],
            1 => ['min' => 1000, 'max' => 10000],
            2 => ['min' => 10000, 'max' => 100000],
            3 => ['min' => 100000, 'max' => 250000],
            4 => ['min' => 250000, 'max' => 500000],
            5 => ['min' => 500000, 'max' => 1000000],
            6 => ['min' => 1000000, 'max' => 2500000],
            7 => ['min' => 2500000, 'max' => 5000000],
            8 => ['min' => 5000000, 'max' => 10000000],
            9 => ['min' => 10000000, 'max' => PHP_INT_MAX],
        ];

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
     * Get last upload date for location
     */
    private function getLastUploadDate(string $type, int $locationId)
    {
        $column = $this->getLocationColumn($type);

        $lastPhoto = DB::table('photos')
            ->where($column, $locationId)
            ->whereNotNull('processed_at')
            ->orderBy('created_at', 'desc')
            ->first(['created_at']);

        return $lastPhoto ? $lastPhoto->created_at : null;
    }

    /**
     * Count photos for location from database
     */
    private function countPhotosForLocation(string $type, int $locationId): int
    {
        $column = $this->getLocationColumn($type);

        return DB::table('photos')
            ->where($column, $locationId)
            ->whereNotNull('processed_at')
            ->count();
    }

    /**
     * Count total photos from database
     */
    private function countTotalPhotosFromDB(): int
    {
        return DB::table('photos')
            ->whereNotNull('processed_at')
            ->count();
    }

    /**
     * Empty response for no data
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

    /**
     * Empty tags response
     */
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
