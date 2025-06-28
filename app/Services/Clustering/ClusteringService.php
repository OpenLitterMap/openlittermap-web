<?php

namespace App\Services\Clustering;

use Illuminate\Support\Facades\DB;

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
     * Update tile keys for a batch of photos - optimized with range queries
     */
    public function updatePhotoTileKeys(int $batchSize = null): int
    {
        $batchSize = $batchSize ?? config('clustering.update_chunk_size', 1000);
        $updated = 0;
        $lastId = 0;

        do {
            // Get max ID for this batch
            $maxId = DB::table('photos')
                ->where('id', '>', $lastId)
                ->whereNull('tile_key')
                ->whereBetween('lat', [-90, 90])
                ->whereBetween('lon', [-180, 180])
                ->orderBy('id')
                ->limit($batchSize)
                ->max('id');

            if (!$maxId) {
                break;
            }

            // Get photos in this range
            $photos = DB::table('photos')
                ->whereBetween('id', [$lastId + 1, $maxId])
                ->whereNull('tile_key')
                ->whereBetween('lat', [-90, 90])
                ->whereBetween('lon', [-180, 180])
                ->get(['id', 'lat', 'lon']);

            // Build updates
            $updates = [];
            foreach ($photos as $photo) {
                $tileKey = $this->computeTileKey($photo->lat, $photo->lon);
                if ($tileKey !== null) {
                    $updates[] = "({$photo->id}, {$tileKey})";
                }
            }

            // Bulk update
            if (!empty($updates)) {
                DB::statement("
                    INSERT INTO photos (id, tile_key) VALUES
                    " . implode(',', $updates) . "
                    ON DUPLICATE KEY UPDATE tile_key = VALUES(tile_key)
                ");
                $updated += count($updates);
            }

            $lastId = $maxId;

        } while (true);

        return $updated;
    }

    /**
     * Cluster all photos in a tile for all zoom levels
     * Uses grid_size for optimized queries
     */
    public function clusterTile(int $tileKey): void
    {
        $zoomConfigs = config('clustering.zoom_levels', [
            8 => ['grid' => 1.0, 'min_points' => 3],
            12 => ['grid' => 0.25, 'min_points' => 2],
            16 => ['grid' => 0.05, 'min_points' => 1],
        ]);

        foreach ($zoomConfigs as $zoom => $config) {
            $this->clusterTileAtZoom($tileKey, $zoom, $config);
        }
    }

    /**
     * Cluster a single tile at a specific zoom level
     * Uses INSERT ON DUPLICATE KEY UPDATE to avoid gaps
     */
    private function clusterTileAtZoom(int $tileKey, int $zoom, array $cfg): void
    {
        $g   = $cfg['grid'];
        $min = $cfg['min_points'];

        DB::statement("
            INSERT INTO clusters
                (tile_key, zoom, year, cell_x, cell_y,
                 lat, lon, location, point_count)
            SELECT
                ?, ?, 0,
                FLOOR((lon + 180)/?) AS cell_x,
                FLOOR((lat + 90)/?)  AS cell_y,
                AVG(lat)             AS lat,
                AVG(lon)             AS lon,
                ST_SRID(POINT(AVG(lon), AVG(lat)), 4326)  AS location,
                COUNT(*)             AS point_count
            FROM   photos
            WHERE  tile_key = ?
              AND  verified = 2
              AND  lat IS NOT NULL
              AND  lon IS NOT NULL
            GROUP  BY cell_x, cell_y
            HAVING COUNT(*) >= ?
            ON DUPLICATE KEY UPDATE
                lat         = VALUES(lat),
                lon         = VALUES(lon),
                location    = VALUES(location),
                point_count = VALUES(point_count),
                updated_at  = CURRENT_TIMESTAMP
        ", [$tileKey, $zoom, $g, $g, $tileKey, $min]);
    }


    /**
     * Mark a tile as dirty with optional backoff
     */
    public function markTileDirty(int $tileKey, bool $withBackoff = false): void
    {
        $changedAt = $withBackoff
            ? now()->addMinutes(1) // Exponential backoff for retries
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
     * Fill tile_key for the next N un-processed photos.
     *
     * @return int rows updated
     */
    public function backfillPhotoTileKeys(int $chunk = 50_000): int
    {
        // Grab the highest id that still needs a key
        $max = DB::table('photos')
            ->whereNull('tile_key')
            ->whereBetween('lat', [-90, 90])
            ->whereBetween('lon', [-180, 180])
            ->orderBy('id')
            ->limit($chunk)
            ->max('id');

        if (!$max) {              // nothing left
            return 0;
        }

        $tileSize  = config('clustering.tile_size', 0.25);
        $tileWidth = (int)(360 / $tileSize);

        // One set-based UPDATE – **no** insert, uses MySQL maths only
        return DB::affectingStatement("
            UPDATE photos
            SET    tile_key =
                   FLOOR((lat + 90) / {$tileSize}) * {$tileWidth} +
                   FLOOR((lon + 180) / {$tileSize})
            WHERE  id <= ?
              AND  tile_key IS NULL
              AND  lat BETWEEN -90 AND 90
              AND  lon BETWEEN -180 AND 180
        ", [$max]);
    }
}
