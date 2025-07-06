<?php

namespace App\Services\Clustering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClusteringService
{
    /**
     * Compute tile key from coordinates
     * Handles boundary cases properly with clamping
     */
    public function computeTileKey(float $lat, float $lon): ?int
    {
        // Validate coordinates
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        // Clamp to prevent overflow (matching migration logic)
        $lat = min($lat, 89.999999);
        $lon = min($lon, 179.999999);

        // Use config for tile size
        $tileSize = config('clustering.tile_size', 0.25);
        $tileWidth = (int) (360 / $tileSize);

        // Simple formula with origin at (-90, -180)
        $latIndex = (int) floor(($lat + 90) / $tileSize);
        $lonIndex = (int) floor(($lon + 180) / $tileSize);

        return $latIndex * $tileWidth + $lonIndex;
    }

    /**
     * Get grid size for a given zoom level
     * Now uses configured grid sizes from config
     */
    private function gridSizeForZoom(int $zoom): float
    {
        // First check if we have a configured grid size for this zoom
        $configuredSize = config("clustering.grid_sizes.$zoom");
        if ($configuredSize !== null) {
            return $configuredSize;
        }

        // Fall back to calculated size
        $g = config('clustering.base_grid_deg', 90.0) / pow(2, $zoom / 2);

        // Never go smaller than the physical tile
        return max($g, config('clustering.tile_size', 0.25));
    }

    /**
     * Get minimum points required for a zoom level
     * Now uses configured value for all zoom levels
     */
    private function minPointsForZoom(int $zoom): int
    {
        // Use configured minimum cluster size (default 1 for single photo clusters)
        return config('clustering.min_cluster_size', 1);
    }

    /**
     * Get cell division factor for a zoom level
     * This converts from smallest grid cells to zoom-specific cells
     */
    private function getCellFactor(int $zoom): int
    {
        $gridSize = $this->gridSizeForZoom($zoom);
        $smallestGrid = config('clustering.smallest_grid', 0.0025);
        return max(1, (int) round($gridSize / $smallestGrid));
    }

    /**
     * Fill tile_key for the next N un-processed photos
     */
    public function backfillPhotoTileKeys(int $chunk = 50000): int
    {
        $chunk = config('clustering.update_chunk_size', $chunk);

        // Grab the highest id that still needs a key
        $max = DB::table('photos')
            ->whereNull('tile_key')
            ->whereBetween('lat', [-90, 90])
            ->whereBetween('lon', [-180, 180])
            ->orderBy('id')
            ->limit($chunk)
            ->max('id');

        if (!$max) {
            return 0;
        }

        $tileSize = config('clustering.tile_size', 0.25);
        $tileWidth = (int)(360 / $tileSize);

        // One set-based UPDATE - no insert, uses MySQL maths only
        return DB::affectingStatement("
            UPDATE photos
            SET tile_key =
                FLOOR((lat + 90) / {$tileSize}) * {$tileWidth} +
                FLOOR((lon + 180) / {$tileSize})
            WHERE id <= ?
              AND tile_key IS NULL
              AND lat BETWEEN -90 AND 90
              AND lon BETWEEN -180 AND 180
        ", [$max]);
    }

    /**
     * Cluster all photos globally for a given zoom level
     */
    public function clusterGlobal(int $zoom): int
    {
        $g = $this->gridSizeForZoom($zoom);
        $min = $this->minPointsForZoom($zoom);
        $globalTileKey = config('clustering.global_tile_key');

        // Delete existing clusters for this zoom
        DB::table('clusters')->where('zoom', $zoom)->delete();

        // Insert new clusters
        // Note: We use the grouped columns in the outer SELECT to comply with ONLY_FULL_GROUP_BY
        DB::statement("
            INSERT INTO clusters
              (tile_key, zoom, year, cell_x, cell_y,
               lat, lon, location, point_count, grid_size)
            SELECT
              ? AS tile_key, ?, 0,
              cell_x, cell_y,
              AVG(lat), AVG(lon),
              ST_SRID(POINT(AVG(lon), AVG(lat)), 4326),
              COUNT(*),
              ?
            FROM (
              SELECT
                lat, lon,
                FLOOR((lon + 180)/?) AS cell_x,
                FLOOR((lat + 90)/?) AS cell_y
              FROM photos
              WHERE verified = 2
            ) AS grouped_photos
            GROUP BY cell_x, cell_y
            HAVING COUNT(*) >= ?
        ", [$globalTileKey, $zoom, $g, $g, $g, $min]);

        return DB::table('clusters')
            ->where('zoom', $zoom)
            ->where('tile_key', $globalTileKey)
            ->count();
    }

    /**
     * Cluster all tiles for deep zoom levels - OPTIMIZED VERSION
     * Uses generated columns for massive performance improvement
     */
    public function clusterAllTilesForZoom(int $zoom): int
    {
        // Check if generated columns exist
        if (!Schema::hasColumn('photos', 'cell_x') || !Schema::hasColumn('photos', 'cell_y')) {
            throw new \RuntimeException('Generated columns cell_x and cell_y are required. Run migrations first.');
        }

        $min = $this->minPointsForZoom($zoom);
        $factor = $this->getCellFactor($zoom);
        $gridSize = $this->gridSizeForZoom($zoom);
        $globalTileKey = config('clustering.global_tile_key');

        // Delete existing clusters for this zoom (except global ones)
        DB::table('clusters')
            ->where('zoom', $zoom)
            ->where('tile_key', '!=', $globalTileKey)
            ->delete();

        // Process all tiles in one query using generated columns
        // This uses the covering index and avoids floating point operations
        DB::statement("
            INSERT INTO clusters
              (tile_key, zoom, year, cell_x, cell_y,
               lat, lon, location, point_count, grid_size)
            SELECT
              tile_key, ?, 0,
              cluster_x, cluster_y,
              AVG(lat), AVG(lon),
              ST_SRID(POINT(AVG(lon), AVG(lat)), 4326),
              COUNT(*),
              ?
            FROM (
              SELECT
                tile_key, lat, lon,
                FLOOR(cell_x / ?) AS cluster_x,
                FLOOR(cell_y / ?) AS cluster_y
              FROM photos
              WHERE verified = 2
                AND tile_key IS NOT NULL
            ) AS grouped_photos
            GROUP BY tile_key, cluster_x, cluster_y
            HAVING COUNT(*) >= ?
        ", [$zoom, $gridSize, $factor, $factor, $min]);

        return DB::table('clusters')
            ->where('zoom', $zoom)
            ->where('tile_key', '!=', $globalTileKey)
            ->count();
    }

    /**
     * EXPERIMENTAL: Hierarchical clustering using previous zoom results
     * This is even faster for deep zooms but requires careful testing
     */
    public function clusterHierarchical(int $fromZoom, int $toZoom): int
    {
        $min = $this->minPointsForZoom($toZoom);
        $fromGrid = $this->gridSizeForZoom($fromZoom);
        $toGrid = $this->gridSizeForZoom($toZoom);
        $gridRatio = $fromGrid / $toGrid;

        // Create temporary table from previous zoom
        DB::statement("
            CREATE TEMPORARY TABLE tmp_clusters_z{$fromZoom} AS
            SELECT * FROM clusters WHERE zoom = ?
        ", [$fromZoom]);

        // Delete existing clusters for target zoom
        DB::table('clusters')->where('zoom', $toZoom)->delete();

        // Generate new clusters from previous zoom
        DB::statement("
            INSERT INTO clusters
              (tile_key, zoom, year, cell_x, cell_y,
               lat, lon, location, point_count)
            SELECT
              tile_key, ?, 0,
              FLOOR(cell_x * ?),
              FLOOR(cell_y * ?),
              AVG(lat), AVG(lon),
              ST_SRID(POINT(AVG(lon), AVG(lat)), 4326),
              SUM(point_count)
            FROM tmp_clusters_z{$fromZoom}
            GROUP BY tile_key,
                     FLOOR(cell_x * ?),
                     FLOOR(cell_y * ?)
            HAVING SUM(point_count) >= ?
        ", [$toZoom, $gridRatio, $gridRatio, $gridRatio, $gridRatio, $min]);

        // Drop temporary table
        DB::statement("DROP TEMPORARY TABLE tmp_clusters_z{$fromZoom}");

        return DB::table('clusters')->where('zoom', $toZoom)->count();
    }

    /**
     * Mark a tile as dirty with optional backoff
     */
    public function markTileDirty(int $tileKey, bool $withBackoff = false): void
    {
        $changedAt = $withBackoff
            ? now()->addMinutes(1)
            : now();

        // Upsert with attempt increment on conflict
        DB::statement('
            INSERT INTO dirty_tiles (tile_key, changed_at, attempts)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                changed_at = IF(attempts < 3, VALUES(changed_at), changed_at + INTERVAL 5 MINUTE),
                attempts = attempts + 1
        ', [$tileKey, $changedAt, $withBackoff ? 1 : 0]);
    }

    /**
     * Get clustering statistics
     */
    public function getStats(): array
    {
        return [
            'photos_total' => DB::table('photos')->count(),
            'photos_with_tiles' => DB::table('photos')->whereNotNull('tile_key')->count(),
            'photos_verified' => DB::table('photos')->where('verified', 2)->count(),
            'unique_tiles' => DB::table('photos')
                ->whereNotNull('tile_key')
                ->where('verified', 2)
                ->distinct('tile_key')
                ->count('tile_key'),
            'clusters_total' => DB::table('clusters')->count(),
            'clusters_by_zoom' => DB::table('clusters')
                ->select('zoom', DB::raw('COUNT(*) as count'))
                ->groupBy('zoom')
                ->pluck('count', 'zoom')
                ->toArray(),
        ];
    }

    /**
     * Debug clustering for a specific tile
     */
    public function debugClustering(int $tileKey): array
    {
        $photos = DB::table('photos')
            ->where('tile_key', $tileKey)
            ->where('verified', 2)
            ->count();

        $clusters = DB::table('clusters')
            ->where('tile_key', $tileKey)
            ->get();

        return [
            'tile_key' => $tileKey,
            'photo_count' => $photos,
            'cluster_count' => $clusters->count(),
            'clusters_by_zoom' => $clusters->groupBy('zoom')->map->count(),
            'total_points_in_clusters' => $clusters->sum('point_count'),
        ];
    }
}
