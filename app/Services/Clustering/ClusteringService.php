<?php

namespace App\Services\Clustering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pure-SQL tile clustering with atomic operations and improved error handling
 */
class ClusteringService
{
    /* metres-per-pixel cache, keyed by zoom */
    private array $mPerPx = [];

    private int $pxRadius;
    private int $minZoom;
    private int $maxZoom;
    private string $singletonPolicy;
    private int $lockTimeout;

    public function __construct()
    {
        $this->pxRadius = config('clustering.pixel_radius', 80);
        $this->minZoom  = config('clustering.min_zoom', 2);
        $this->maxZoom  = config('clustering.max_zoom', 16);
        $this->singletonPolicy = config('clustering.singleton_policy', 'max_zoom_only');
        $this->lockTimeout = config('clustering.lock_timeout', 30);

        $circ = 2 * M_PI * 6378137.0;                       // earth circumference
        for ($z = $this->minZoom; $z <= $this->maxZoom; $z++) {
            $this->mPerPx[$z] = $circ / (256 * (1 << $z));
        }
    }

    /**
     * @param  int         $tileKey  0·25° tile identifier
     * @param  int|null    $year     limit to a calendar year (NULL = all)
     * @param  string|null $dummy    kept only so the old test-suite signature lives on
     * @return array{photos:int, clusters:int, clusters_by_zoom?:array}
     */
    public function rebuildTile(int $tileKey, ?int $year = null, ?string $dummy = null): array
    {
        // Get adjacent tiles for proper edge handling
        $tiles = TileMath::getAdjacentTiles($tileKey);

        // Validate we got exactly 9 tiles (3x3 grid)
        if (count($tiles) !== 9) {
            throw new \LogicException("Expected 9 adjacent tiles, got " . count($tiles));
        }

        $photoCount = (int) DB::table('photos')
            ->whereIn('tile_key', $tiles)
            ->where('verified', 2)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->count();

        // Improved lock naming to include year
        $lockName = sprintf("tile_%d_year_%s", $tileKey, $year ?? 'all');

        // Set session lock timeout
        DB::statement('SET SESSION innodb_lock_wait_timeout = ?', [$this->lockTimeout]);

        // Retry logic for lock acquisition
        $attempts = 0;
        $maxAttempts = 3;
        $lockAcquired = false;

        while ($attempts < $maxAttempts && !$lockAcquired) {
            $lockResult = DB::selectOne('SELECT GET_LOCK(?, ?) AS l', [$lockName, $this->lockTimeout]);
            if ($lockResult && $lockResult->l === 1) {
                $lockAcquired = true;
                break;
            }
            $attempts++;
            if ($attempts < $maxAttempts) {
                sleep(1); // Wait before retry
            }
        }

        if (!$lockAcquired) {
            throw new \RuntimeException("Could not obtain lock for tile $tileKey after $maxAttempts attempts");
        }

        $clustersByZoom = [];

        try {
            // Use transaction for atomicity
            DB::transaction(function() use ($tileKey, $year, $tiles, $photoCount, &$clustersByZoom) {
                // Delete existing clusters
                DB::table('clusters')
                    ->where('tile_key', $tileKey)
                    ->where('year', $year ?? 0)
                    ->delete();

                // Determine singleton policy
                $allowSingletons = $this->shouldAllowSingletons($photoCount);

                // Insert clusters for each zoom level
                for ($z = $this->minZoom; $z <= $this->maxZoom; $z++) {
                    $shouldAllowAtThisZoom = match($this->singletonPolicy) {
                        'none' => false,
                        'all' => true,
                        'max_zoom_only' => ($z === $this->maxZoom || $photoCount > 1),
                        default => ($z === $this->maxZoom || $photoCount > 1)
                    };

                    $inserted = $this->insertClusters($tileKey, $z, $year, $shouldAllowAtThisZoom, $tiles);

                    if (config('clustering.debug')) {
                        $clustersByZoom[$z] = $inserted;
                    }

                    if ($inserted === 0 && $photoCount > 0 && config('clustering.debug')) {
                        Log::debug('No clusters at zoom level', [
                            'tile' => $tileKey,
                            'zoom' => $z,
                            'photos' => $photoCount,
                            'singleton_policy' => $this->singletonPolicy
                        ]);
                    }
                }
            });

            $clusterCount = (int) DB::table('clusters')
                ->where('tile_key', $tileKey)
                ->where('year', $year ?? 0)
                ->count();
        } catch (\Throwable $e) {
            // Ensure lock is released even on error
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            throw $e;
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }

        $result = ['photos' => $photoCount, 'clusters' => $clusterCount];

        if (config('clustering.debug')) {
            $result['clusters_by_zoom'] = $clustersByZoom;
        }

        return $result;
    }

    /**
     * Process only tiles that actually contain photos (optimization)
     */
    public function rebuildAffectedTiles(int $tileKey, ?int $year = null): array
    {
        $adjacentTiles = TileMath::getAdjacentTiles($tileKey);

        // Only process tiles that actually have photos
        $affectedTiles = DB::table('photos')
            ->whereIn('tile_key', $adjacentTiles)
            ->where('verified', 2)
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->distinct()
            ->pluck('tile_key');

        $results = [
            'tiles_processed' => 0,
            'total_photos' => 0,
            'total_clusters' => 0
        ];

        foreach ($affectedTiles as $tile) {
            $result = $this->rebuildTile($tile, $year);
            $results['tiles_processed']++;
            $results['total_photos'] += $result['photos'];
            $results['total_clusters'] += $result['clusters'];
        }

        return $results;
    }

    private function shouldAllowSingletons(int $photoCount): bool
    {
        return match($this->singletonPolicy) {
            'none' => false,
            'all' => true,
            'max_zoom_only' => $photoCount > 1,
            default => $photoCount > 1
        };
    }

    public function insertClusters(
        int $tileKey,
        int $zoom,
        ?int $year,
        bool $allowSingletons,
        array $tiles
    ): int {
        $grid   = $this->mPerPx[$zoom] * $this->pxRadius;
        $deg2   = M_PI / 180;
        $R      = 6378137.0;
        $maxLat = 85.0511287798;

        $minPts = $allowSingletons ? 1 : 2;

        // Validate tiles array size before building query
        if (count($tiles) > 100) {
            throw new \RuntimeException("Too many tiles provided: " . count($tiles));
        }

        /** @noinspection SqlResolve */
        $affected = DB::affectingStatement("
            INSERT INTO clusters
                (tile_key, zoom, cell_x, cell_y, lat, lon, point_count, year)
            SELECT
                ?, ?,
                FLOOR(p.x / ?)  AS cx,
                FLOOR(p.y / ?)  AS cy,
                AVG(p.lat), AVG(p.lon), COUNT(*), ?
            FROM (
                SELECT
                    lat, lon,
                    lon * ? * ?                                   AS x,
                    ? * LN(TAN(RADIANS(45 +
                        LEAST(GREATEST(lat, ?), ?)/2)))           AS y
                FROM photos
                WHERE tile_key IN (" . implode(',', array_fill(0, count($tiles), '?')) . ")
                  AND verified  = 2
                  AND lat IS NOT NULL AND lon IS NOT NULL"
            . ($year ? " AND YEAR(created_at) = ?" : '') . "
            ) AS p
            GROUP BY cx, cy
            HAVING COUNT(*) >= ?
            ON DUPLICATE KEY UPDATE
                lat         = VALUES(lat),
                lon         = VALUES(lon),
                point_count = VALUES(point_count),
                updated_at  = CURRENT_TIMESTAMP
        ", array_values(array_filter([
            $tileKey, $zoom,
            $grid, $grid,
            $year ?? 0,
            $deg2, $R,
            $R, -$maxLat, $maxLat,
            ...$tiles,
            $year,
            $minPts,
        ], static fn ($v) => $v !== null)));

        // Log if debug mode and no rows affected despite photos existing
        if (config('clustering.debug') && $affected === 0) {
            $eligiblePhotos = DB::table('photos')
                ->whereIn('tile_key', $tiles)
                ->where('verified', 2)
                ->whereNotNull('lat')
                ->whereNotNull('lon')
                ->when($year, fn($q) => $q->whereYear('created_at', $year))
                ->count();

            if ($eligiblePhotos > 0) {
                Log::debug('No clusters created despite eligible photos', [
                    'tile' => $tileKey,
                    'zoom' => $zoom,
                    'eligible_photos' => $eligiblePhotos,
                    'min_points' => $minPts,
                    'grid_size' => $grid
                ]);
            }
        }

        return $affected;
    }

    /* Test-suite compatibility stubs */
    public function initWorkerTemp(): string { return 'stub_' . uniqid(); }
    public function dropWorkerTemp(string $n): void { /* no-op */ }
}
