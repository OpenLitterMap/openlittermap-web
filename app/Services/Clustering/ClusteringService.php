<?php

namespace App\Services\Clustering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClusteringService
{
    /**
     * Grid size in degrees for each zoom level
     *
     * WARNING: These are in degrees, so physical distance shrinks at higher latitudes!
     * At latitude φ, 1° longitude = 111km * cos(φ)
     */
    private array $zoomRadii = [
        2 => 10.0,    // 10 degrees
        4 => 5.0,     // 5 degrees
        6 => 2.5,     // 2.5 degrees
        8 => 1.0,     // 1 degree
        10 => 0.5,    // 0.5 degrees
        12 => 0.25,   // 0.25 degrees
        14 => 0.1,    // 0.1 degrees
        16 => 0.05,   // 0.05 degrees
    ];

    /**
     * Cluster all photos in a tile
     */
    public function clusterTile(int $tileKey, ?int $year = null): array
    {
        $stats = ['photos' => 0, 'clusters' => 0];

        // Add concurrency lock
        $lockName = "cluster_tile_{$tileKey}_year_" . ($year ?? 'all');
        $lockAcquired = DB::selectOne("SELECT GET_LOCK(?, 5) as acquired", [$lockName])->acquired;

        if (!$lockAcquired) {
            throw new \RuntimeException("Could not acquire lock for tile $tileKey");
        }

        try {
            DB::transaction(function() use ($tileKey, $year, &$stats) {
                // Delete old clusters for this tile
                DB::table('clusters')
                    ->where('tile_key', $tileKey)
                    ->where('year', $year ?? 0)  // year is now NOT NULL DEFAULT 0
                    ->delete();

                // Count photos in this tile
                $photoCount = DB::table('photos')
                    ->where('tile_key', $tileKey)
                    ->where('verified', 2)
                    ->when($year, fn($q) => $q->whereYear('created_at', $year))
                    ->count();

                $stats['photos'] = $photoCount;

                if ($photoCount == 0) {
                    return;
                }

                // Create clusters for each zoom level
                foreach ($this->zoomRadii as $zoom => $gridSize) {
                    $clustersCreated = $this->createClustersForZoom(
                        $tileKey,
                        $zoom,
                        $gridSize,
                        $year
                    );

                    $stats['clusters'] += $clustersCreated;
                }
            });
        } catch (\Exception $e) {
            Log::error("Failed to cluster tile $tileKey: " . $e->getMessage());
            throw $e;
        } finally {
            DB::selectOne("SELECT RELEASE_LOCK(?)", [$lockName]);
        }

        return $stats;
    }

    /**
     * Create clusters for a specific zoom level
     */
    private function createClustersForZoom(int $tileKey, int $zoom, float $gridSize, ?int $year): int
    {
        // Allow single photo clusters only at max zoom
        $minPoints = ($zoom == 16) ? 1 : 2;

        // Fix parameter order - build query with correct placeholder sequence
        if ($year) {
            $sql = "
                INSERT INTO clusters (tile_key, zoom, year, cell_x, cell_y, lat, lon, point_count)
                SELECT
                    ?,
                    ?,
                    ?,
                    FLOOR(lon / ?) as cell_x,
                    FLOOR(lat / ?) as cell_y,
                    AVG(lat) as lat,
                    AVG(lon) as lon,
                    COUNT(*) as point_count
                FROM photos
                WHERE tile_key = ?
                    AND verified = 2
                    AND YEAR(created_at) = ?
                GROUP BY cell_x, cell_y
                HAVING COUNT(*) >= ?
                ON DUPLICATE KEY UPDATE
                    lat = VALUES(lat),
                    lon = VALUES(lon),
                    point_count = VALUES(point_count),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $bindings = [
                $tileKey,
                $zoom,
                $year,
                $gridSize,
                $gridSize,
                $tileKey,
                $year,
                $minPoints
            ];
        } else {
            $sql = "
                INSERT INTO clusters (tile_key, zoom, year, cell_x, cell_y, lat, lon, point_count)
                SELECT
                    ?,
                    ?,
                    ?,
                    FLOOR(lon / ?) as cell_x,
                    FLOOR(lat / ?) as cell_y,
                    AVG(lat) as lat,
                    AVG(lon) as lon,
                    COUNT(*) as point_count
                FROM photos
                WHERE tile_key = ?
                    AND verified = 2
                GROUP BY cell_x, cell_y
                HAVING COUNT(*) >= ?
                ON DUPLICATE KEY UPDATE
                    lat = VALUES(lat),
                    lon = VALUES(lon),
                    point_count = VALUES(point_count),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $bindings = [
                $tileKey,
                $zoom,
                0,
                $gridSize,
                $gridSize,
                $tileKey,
                $minPoints
            ];
        }

        return DB::affectingStatement($sql, $bindings);
    }

    /**
     * Get tile bounds for a given tile key
     */
    public function getTileBounds(int $tileKey): array
    {
        $latIdx = floor($tileKey / 1440);
        $lonIdx = $tileKey % 1440;

        return [
            'min_lat' => -90 + $latIdx * 0.25,
            'max_lat' => -90 + ($latIdx + 1) * 0.25,
            'min_lon' => -180 + $lonIdx * 0.25,
            'max_lon' => -180 + ($lonIdx + 1) * 0.25,
        ];
    }
}
