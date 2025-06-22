<?php

namespace App\Services\Clustering;

/**
 * Tile mathematics for 0.25° grid system
 */
class TileMath
{
    private const TILE_SIZE = 0.25;
    private const LAT_MIN = -90.0;
    private const LAT_MAX = 90.0;
    private const LON_MIN = -180.0;
    private const LON_MAX = 180.0;

    /**
     * Get tile key for a coordinate
     * @param float $lat Latitude (-90 to 90)
     * @param float $lon Longitude (-180 to 180)
     * @return int Tile key
     */
    public static function getTileKey(float $lat, float $lon): int
    {
        // Clamp coordinates to valid ranges
        $lat = max(self::LAT_MIN, min(self::LAT_MAX - 0.000001, $lat));
        $lon = max(self::LON_MIN, min(self::LON_MAX - 0.000001, $lon));

        // Calculate tile indices
        $latIndex = (int) round(($lat + 90) * 4);
        $lonIndex = (int) round(($lon + 180) * 4);

        // Combine into single key
        return $latIndex * 10000 + $lonIndex;
    }

    /**
     * Get bounds of a tile
     * @param int $tileKey
     * @return array{minLat: float, maxLat: float, minLon: float, maxLon: float}
     */
    public static function getTileBounds(int $tileKey): array
    {
        $latIndex = intval($tileKey / 10000);
        $lonIndex = $tileKey % 10000;

        $minLat = ($latIndex / 4.0) - 90;
        $maxLat = $minLat + self::TILE_SIZE;
        $minLon = ($lonIndex / 4.0) - 180;
        $maxLon = $minLon + self::TILE_SIZE;

        return [
            'minLat' => $minLat,
            'maxLat' => min($maxLat, self::LAT_MAX),
            'minLon' => $minLon,
            'maxLon' => min($maxLon, self::LON_MAX),
        ];
    }

    /**
     * Get all 9 adjacent tiles (3x3 grid) for proper edge handling
     * @param int $tileKey Center tile
     * @return array Array of 9 tile keys
     * @throws \LogicException if tile structure is invalid
     */
    public static function getAdjacentTiles(int $tileKey): array
    {
        $latIndex = intval($tileKey / 10000);
        $lonIndex = $tileKey % 10000;

        $tiles = [];

        // 3x3 grid centered on the given tile
        for ($dLat = -1; $dLat <= 1; $dLat++) {
            for ($dLon = -1; $dLon <= 1; $dLon++) {
                $adjLatIndex = $latIndex + $dLat;
                $adjLonIndex = $lonIndex + $dLon;

                // Skip tiles outside valid ranges
                if ($adjLatIndex < 0 || $adjLatIndex >= 720) continue;
                if ($adjLonIndex < 0 || $adjLonIndex >= 1440) continue;

                // Handle longitude wrap-around at date line
                if ($adjLonIndex >= 1440) {
                    $adjLonIndex -= 1440;
                } elseif ($adjLonIndex < 0) {
                    $adjLonIndex += 1440;
                }

                $tiles[] = $adjLatIndex * 10000 + $adjLonIndex;
            }
        }

        // Ensure we always return exactly 9 tiles for interior tiles
        // Edge tiles might have fewer
        if (count($tiles) < 6) {
            throw new \LogicException("Invalid tile structure: expected at least 6 tiles, got " . count($tiles));
        }

        // For most tiles we should get exactly 9
        if (count($tiles) !== 9) {
            // This is OK for edge tiles (at poles or near boundaries)
            // but log it for debugging
            \Log::debug('Edge tile detected', [
                'tile_key' => $tileKey,
                'adjacent_count' => count($tiles),
                'lat_index' => $latIndex,
                'lon_index' => $lonIndex
            ]);
        }

        return array_values(array_unique($tiles));
    }

    /**
     * Calculate distance between two points using Haversine formula
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in meters
     */
    public static function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get tile dimensions at a given latitude
     * @param float $lat Latitude
     * @return array{width: float, height: float} Dimensions in meters
     */
    public static function getTileDimensions(float $lat): array
    {
        // Height is constant
        $height = self::haversineDistance($lat, 0, $lat + self::TILE_SIZE, 0);

        // Width varies with latitude due to longitude convergence
        $width = self::haversineDistance($lat, 0, $lat, self::TILE_SIZE);

        return [
            'width' => $width,
            'height' => $height
        ];
    }

    /**
     * Check if a coordinate is near a tile edge
     * @param float $lat
     * @param float $lon
     * @param float $threshold Distance from edge in degrees (default 0.01°)
     * @return bool
     */
    public static function isNearTileEdge(float $lat, float $lon, float $threshold = 0.01): bool
    {
        $tileKey = self::getTileKey($lat, $lon);
        $bounds = self::getTileBounds($tileKey);

        $distToEdge = min(
            $lat - $bounds['minLat'],
            $bounds['maxLat'] - $lat,
            $lon - $bounds['minLon'],
            $bounds['maxLon'] - $lon
        );

        return $distToEdge < $threshold;
    }

    /**
     * Get statistics about tile distribution
     * @param array $tileKeys Array of tile keys
     * @return array Statistics
     */
    public static function getTileStatistics(array $tileKeys): array
    {
        if (empty($tileKeys)) {
            return [
                'count' => 0,
                'lat_range' => ['min' => null, 'max' => null],
                'lon_range' => ['min' => null, 'max' => null],
                'coverage_km2' => 0
            ];
        }

        $lats = [];
        $lons = [];

        foreach ($tileKeys as $key) {
            $bounds = self::getTileBounds($key);
            $lats[] = $bounds['minLat'];
            $lats[] = $bounds['maxLat'];
            $lons[] = $bounds['minLon'];
            $lons[] = $bounds['maxLon'];
        }

        $uniqueTiles = array_unique($tileKeys);
        $avgLat = (min($lats) + max($lats)) / 2;
        $dimensions = self::getTileDimensions($avgLat);
        $coverageKm2 = count($uniqueTiles) * ($dimensions['width'] * $dimensions['height']) / 1_000_000;

        return [
            'count' => count($uniqueTiles),
            'lat_range' => ['min' => min($lats), 'max' => max($lats)],
            'lon_range' => ['min' => min($lons), 'max' => max($lons)],
            'coverage_km2' => round($coverageKm2, 2)
        ];
    }
}
