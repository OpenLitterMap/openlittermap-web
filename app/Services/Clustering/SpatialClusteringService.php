<?php

namespace App\Services\Clustering;

use Illuminate\Support\Facades\DB;

/**
 * Spatial clustering using MySQL 8's native geospatial features
 */
class SpatialClusteringService
{
    private int $minZoom;
    private int $maxZoom;
    private string $singletonPolicy;
    private int $lockTimeout;
    private array $gridSizes = []; // Grid size in degrees per zoom level

    public function __construct()
    {
        $this->minZoom = config('clustering.min_zoom', 2);
        $this->maxZoom = config('clustering.max_zoom', 16);
        $this->singletonPolicy = config('clustering.singleton_policy', 'max_zoom_only');
        $this->lockTimeout = config('clustering.lock_timeout', 30);

        // Calculate grid sizes for each zoom level
        // At zoom 0, we have 1 tile of 360°. Each zoom level doubles the tiles.
        $pixelRadius = config('clustering.pixel_radius', 80);
        for ($z = $this->minZoom; $z <= $this->maxZoom; $z++) {
            // Degrees per pixel at this zoom level
            $degreesPerPixel = 360 / (256 * pow(2, $z));
            $this->gridSizes[$z] = $degreesPerPixel * $pixelRadius;
        }
    }

    /**
     * Rebuild clusters for a tile using spatial operations
     */
    public function rebuildTile(int $tileKey, ?int $year = null): array
    {
        // Get tile bounds from spatial grid
        $tileBounds = DB::selectOne("
            SELECT
                ST_AsText(bounds) as bounds_wkt,
                ST_XMin(bounds) as min_lon,
                ST_XMax(bounds) as max_lon,
                ST_YMin(bounds) as min_lat,
                ST_YMax(bounds) as max_lat
            FROM spatial_tile_grid
            WHERE tile_key = ?
        ", [$tileKey]);

        if (!$tileBounds) {
            throw new \RuntimeException("Invalid tile key: $tileKey");
        }

        // Expand bounds by one tile in each direction for edge handling
        $expandedBounds = $this->getExpandedBounds($tileBounds);

        // Count photos in expanded area
        $photoCount = (int) DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM photos
            WHERE verified = 2
                AND location IS NOT NULL
                AND ST_Within(location, ST_GeomFromText(?, 4326))
                " . ($year ? "AND YEAR(created_at) = ?" : "") . "
        ", array_filter([$expandedBounds, $year]))->cnt;

        // Lock for this tile
        $lockName = sprintf("spatial_tile_%d_year_%s", $tileKey, $year ?? 'all');
        if (!$this->acquireLock($lockName)) {
            throw new \RuntimeException("Could not obtain lock for tile $tileKey");
        }

        $clustersByZoom = [];

        try {
            DB::transaction(function() use ($tileKey, $tileBounds, $expandedBounds, $year, $photoCount, &$clustersByZoom) {
                // Delete existing clusters
                DB::table('clusters')
                    ->where('tile_key', $tileKey)
                    ->where('year', $year ?? 0)
                    ->delete();

                // Process each zoom level
                for ($z = $this->minZoom; $z <= $this->maxZoom; $z++) {
                    $allowSingletons = $this->shouldAllowSingletons($z, $photoCount);
                    $gridSize = $this->gridSizes[$z];

                    $inserted = $this->createClustersForZoom(
                        $tileKey,
                        $z,
                        $gridSize,
                        $tileBounds,
                        $expandedBounds,
                        $year,
                        $allowSingletons
                    );

                    if (config('clustering.debug')) {
                        $clustersByZoom[$z] = $inserted;
                    }
                }
            });

            $clusterCount = (int) DB::table('clusters')
                ->where('tile_key', $tileKey)
                ->where('year', $year ?? 0)
                ->count();

        } finally {
            $this->releaseLock($lockName);
        }

        $result = ['photos' => $photoCount, 'clusters' => $clusterCount];
        if (config('clustering.debug')) {
            $result['clusters_by_zoom'] = $clustersByZoom;
        }

        return $result;
    }

    /**
     * Create clusters for a specific zoom level using spatial grid snapping
     */
    private function createClustersForZoom(
        int $tileKey,
        int $zoom,
        float $gridSize,
        object $tileBounds,
        string $expandedBounds,
        ?int $year,
        bool $allowSingletons
    ): int {
        $minPoints = $allowSingletons ? 1 : 2;

        // Use ST_SnapToGrid for clustering
        $sql = "
    INSERT INTO clusters
        (tile_key, zoom, cell_x, cell_y, lat, lon, point_count, year, center_point, cluster_bounds)
    SELECT
        ?,
        ?,
        FLOOR(ST_X(grid_point) / ?) as cell_x,
        FLOOR(ST_Y(grid_point) / ?) as cell_y,
        AVG(ST_Y(location)) as lat,
        AVG(ST_X(location)) as lon,
        COUNT(*) as point_count,
        ?,
        ST_Centroid(ST_Collect(location)) as center_point,
        CASE
            WHEN COUNT(*) = 1 THEN NULL  -- Single point clusters don't need bounds
            ELSE ST_ConvexHull(ST_Collect(location))
        END as cluster_bounds
    FROM (
        SELECT
            location,
            ST_SnapToGrid(location, 0, 0, ?, ?) as grid_point
        FROM photos
        WHERE verified = 2
            AND location IS NOT NULL
            AND ST_Within(location, ST_GeomFromText(?, 4326))
            " . ($year ? "AND YEAR(created_at) = ?" : "") . "
    ) AS snapped
    GROUP BY grid_point
    HAVING COUNT(*) >= ?
        AND ST_Within(ST_Centroid(ST_Collect(location)), ST_GeomFromText(?, 4326))
    ON DUPLICATE KEY UPDATE
        lat = VALUES(lat),
        lon = VALUES(lon),
        point_count = VALUES(point_count),
        center_point = VALUES(center_point),
        cluster_bounds = VALUES(cluster_bounds),
        updated_at = CURRENT_TIMESTAMP
";

        $params = array_filter([
            $tileKey,
            $zoom,
            $gridSize,
            $gridSize,
            $year ?? 0,
            $gridSize,
            $gridSize,
            $expandedBounds,
            $year,
            $minPoints,
            $tileBounds->min_lon,
            $tileBounds->max_lon,
            $tileBounds->min_lat,
            $tileBounds->max_lat
        ], fn($v) => $v !== null);

        return DB::affectingStatement($sql, $params);
    }

    /**
     * Get expanded bounds for edge handling
     */
    private function getExpandedBounds(object $tileBounds): string
    {
        $expansion = 0.25; // One tile in each direction

        $minLon = max(-180, $tileBounds->min_lon - $expansion);
        $maxLon = min(180, $tileBounds->max_lon + $expansion);
        $minLat = max(-90, $tileBounds->min_lat - $expansion);
        $maxLat = min(90, $tileBounds->max_lat + $expansion);

        return "POLYGON(($minLon $minLat, $maxLon $minLat, $maxLon $maxLat, $minLon $maxLat, $minLon $minLat))";
    }

    /**
     * Find tiles that contain photos within a bounding box
     */
    public function findTilesInBounds(float $minLat, float $maxLat, float $minLon, float $maxLon): array
    {
        $bounds = "POLYGON(($minLon $minLat, $maxLon $minLat, $maxLon $maxLat, $minLon $maxLat, $minLon $minLat))";

        return DB::table('spatial_tile_grid')
            ->whereRaw('ST_Intersects(bounds, ST_GeomFromText(?, 4326))', [$bounds])
            ->pluck('tile_key')
            ->toArray();
    }

    /**
     * Get clusters within a bounding box for a specific zoom
     */
    public function getClustersInBounds(
        float $minLat,
        float $maxLat,
        float $minLon,
        float $maxLon,
        int $zoom,
        ?int $year = null
    ): array {
        $bounds = "POLYGON(($minLon $minLat, $maxLon $minLat, $maxLon $maxLat, $minLon $maxLat, $minLon $minLat))";

        return DB::table('clusters')
            ->select('id', 'lat', 'lon', 'point_count')
            ->whereRaw('ST_Within(center_point, ST_GeomFromText(?, 4326))', [$bounds])
            ->where('zoom', $zoom)
            ->where('year', $year ?? 0)
            ->get()
            ->toArray();
    }

    /**
     * Find nearest clusters to a point
     */
    public function findNearestClusters(float $lat, float $lon, int $zoom, int $limit = 10): array
    {
        return DB::select("
            SELECT
                id,
                lat,
                lon,
                point_count,
                ST_Distance_Sphere(center_point, ST_PointFromText(?, 4326)) as distance_meters
            FROM clusters
            WHERE zoom = ?
            ORDER BY center_point <-> ST_PointFromText(?, 4326)
            LIMIT ?
        ", [
            "POINT($lon $lat)",
            $zoom,
            "POINT($lon $lat)",
            $limit
        ]);
    }

    /**
     * Get photos within a cluster using spatial bounds
     */
    public function getPhotosInCluster(int $clusterId): array
    {
        return DB::select("
            SELECT
                p.id,
                ST_Y(p.location) as lat,
                ST_X(p.location) as lon,
                p.filename,
                p.created_at
            FROM photos p
            JOIN clusters c ON ST_Within(p.location, c.cluster_bounds)
            WHERE c.id = ?
                AND p.verified = 2
            ORDER BY p.created_at DESC
        ", [$clusterId]);
    }

    /**
     * Calculate spatial statistics for a tile
     */
    public function getTileStatistics(int $tileKey): array
    {
        $stats = DB::selectOne("
            SELECT
                COUNT(DISTINCT p.id) as photo_count,
                COUNT(DISTINCT c.id) as cluster_count,
                AVG(c.point_count) as avg_cluster_size,
                MAX(c.point_count) as max_cluster_size,
                ST_Area(ST_ConvexHull(ST_Collect(p.location))) * 111319.9 * 111319.9 as coverage_m2
            FROM spatial_tile_grid g
            LEFT JOIN photos p ON ST_Within(p.location, g.bounds) AND p.verified = 2
            LEFT JOIN clusters c ON c.tile_key = g.tile_key
            WHERE g.tile_key = ?
        ", [$tileKey]);

        return [
            'photo_count' => (int) $stats->photo_count,
            'cluster_count' => (int) $stats->cluster_count,
            'avg_cluster_size' => round($stats->avg_cluster_size ?? 0, 2),
            'max_cluster_size' => (int) $stats->max_cluster_size,
            'coverage_km2' => round(($stats->coverage_m2 ?? 0) / 1_000_000, 2)
        ];
    }

    private function shouldAllowSingletons(int $zoom, int $photoCount): bool
    {
        return match($this->singletonPolicy) {
            'none' => false,
            'all' => true,
            'max_zoom_only' => ($zoom === $this->maxZoom || $photoCount > 1),
            default => ($zoom === $this->maxZoom || $photoCount > 1)
        };
    }

    private function acquireLock(string $lockName): bool
    {
        DB::statement('SET SESSION innodb_lock_wait_timeout = ?', [$this->lockTimeout]);

        for ($i = 0; $i < 3; $i++) {
            $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $this->lockTimeout]);
            if ($result && $result->acquired) {
                return true;
            }
            if ($i < 2) sleep(1);
        }

        return false;
    }

    private function releaseLock(string $lockName): void
    {
        DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
}
