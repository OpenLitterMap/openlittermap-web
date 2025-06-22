<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Services\Clustering\TileMath;

class PhotoRepository
{
    /**
     * Load photos from a tile into a temporary table with Mercator coordinates
     *
     * @param string $tempTable Target temporary table name
     * @param int $tileKey Tile identifier
     * @return int Number of photos loaded
     */
    public function loadTileIntoTemp(string $tempTable, int $tileKey): int
    {
        // Clear any existing data
        DB::statement("DELETE FROM `{$tempTable}`");

        // Single clipping value for consistency
        $maxLatitude = TileMath::MAX_LATITUDE - 0.000001;

        // Insert with Mercator conversion (simplified clipping)
        $affected = DB::affectingStatement("
            INSERT INTO `{$tempTable}` (lat, lon, x_meters, y_meters)
            SELECT
                lat,
                lon,
                lon * ? * ? as x_meters,
                ? * LN(TAN(RADIANS(45 + LEAST(lat, ?) / 2))) as y_meters
            FROM photos
            WHERE tile_key = ?
              AND verified = 2
              AND lat BETWEEN ? AND ?
              AND lon BETWEEN -180 AND 180
        ", [
            TileMath::DEG_TO_RAD,  // 0.017453292519943295
            TileMath::EARTH_RADIUS, // 6378137.0
            TileMath::EARTH_RADIUS,
            $maxLatitude,
            $tileKey,
            -$maxLatitude,
            $maxLatitude
        ]);

        return $affected;
    }
}
