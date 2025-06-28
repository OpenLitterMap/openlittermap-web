<?php

namespace App\Http\Controllers\Clusters;

use App\Http\Controllers\Controller;
use App\Traits\GeoJson\CreateGeoJsonPoints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClusterController extends Controller
{
    use CreateGeoJsonPoints;

    /**
     * GET /api/clusters
     *
     * • bbox  – optional array ?bbox[left]=-180&bbox[bottom]=-90&bbox[right]=180&bbox[top]=90
     * • zoom  – optional float|int (we snap to the next configured level)
     * • lat/lon – optional center point (creates bbox if no bbox provided)
     */
    public function index(Request $request)
    {
        // DEBUG: Log incoming request
        Log::info('Cluster request', [
            'url' => $request->fullUrl(),
            'params' => $request->all(),
        ]);

        // DEBUG: Toggle this to ignore grid_size filtering
        $ignoreGridSize = env('CLUSTER_IGNORE_GRID_SIZE', false);

        /* ------------------------------------------------------------
         * Work out the zoom level we'll actually use
         * ------------------------------------------------------------ */
        $available = array_map('intval', array_keys(config('clustering.zoom_levels', [])));
        sort($available, SORT_NUMERIC);

        if (empty($available)) {
            return response()->json(
                ['error' => 'No zoom_levels configured in clustering.php'], 500
            );
        }

        $request->validate([
            'zoom' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'bbox' => ['nullable', 'array'],
            'lat' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'lon' => ['nullable', 'numeric', 'min:-180', 'max:180'],
        ]);

        // Handle fractional zoom levels
        $requested = (float) $request->input('zoom', $available[0]);

        // Round fractional zoom to help with snapping
        $requestedRounded = round($requested);

        // Find the closest available zoom level
        // For zoom 2.88, we want to snap to the nearest available level (likely 2 or 4)
        $zoom = null;

        // First try to find exact match or next higher
        $zoom = collect($available)->first(fn ($z) => $z >= $requestedRounded);

        // If no higher zoom found (e.g., requested 20 but max is 16), use the highest available
        if ($zoom === null) {
            $zoom = end($available);
        }

        // For very low zoom requests, ensure we don't go below minimum
        if ($requestedRounded < $available[0]) {
            $zoom = $available[0];
        }

        // DEBUG: Log zoom calculation
        Log::info('Cluster zoom calculation', [
            'requested_raw' => $requested,
            'requested_rounded' => $requestedRounded,
            'available' => $available,
            'selected' => $zoom,
            'config_for_selected' => config("clustering.zoom_levels.{$zoom}"),
        ]);

        /* ------------------------------------------------------------
         * Parse bbox – fall back to world
         * ------------------------------------------------------------ */
        // Check if lat/lon provided instead of bbox (common in map applications)
        $centerLat = $request->input('lat');
        $centerLon = $request->input('lon');

        if ($request->filled('bbox')) {
            $bbox = [
                (float) ($request->input('bbox.left')   ?? -180),
                (float) ($request->input('bbox.bottom') ?? -90 ),
                (float) ($request->input('bbox.right')  ??  180),
                (float) ($request->input('bbox.top')    ??   90),
            ];
        } else if ($centerLat !== null && $centerLon !== null) {
            // If center coordinates provided, create a reasonable bbox around them
            // The size depends on zoom level - higher zoom = smaller area
            $bboxSize = 180 / pow(2, $zoom / 2); // Rough approximation

            $bbox = [
                max(-180, (float)$centerLon - $bboxSize),  // left
                max(-90,  (float)$centerLat - $bboxSize),  // bottom
                min(180,  (float)$centerLon + $bboxSize),  // right
                min(90,   (float)$centerLat + $bboxSize),  // top
            ];

            Log::info('Created bbox from center point', [
                'center' => ['lat' => $centerLat, 'lon' => $centerLon],
                'zoom' => $zoom,
                'bbox_size' => $bboxSize,
                'bbox' => $bbox,
            ]);
        } else {
            // Default to world bbox
            $bbox = [-180, -90, 180, 90];
        }

        [$minLon, $minLat, $maxLon, $maxLat] = $bbox;

        // Handle dateline crossing (e.g., bbox.right > 180)
        if ($maxLon > 180) {
            Log::info('Handling dateline crossing', ['original_maxLon' => $maxLon]);
            // For now, cap at 180 - you might want more sophisticated handling
            $maxLon = 180;
        }

        $bboxKey = sprintf('%.4f:%.4f:%.4f:%.4f', $minLon, $minLat, $maxLon, $maxLat);

        // DEBUG: Log bbox
        Log::info('Cluster bbox', [
            'minLon' => $minLon,
            'minLat' => $minLat,
            'maxLon' => $maxLon,
            'maxLat' => $maxLat,
        ]);

        /* ------------------------------------------------------------
         * Caching
         * ------------------------------------------------------------ */
        $ttl      = (int) config('clustering.cache_ttl', 300);
        $limit    = (int) config('clustering.max_clusters_per_request', 5000);
        $cacheKey = "clusters:v3:{$zoom}:{$bboxKey}";
        $etag     = '"' . md5($cacheKey) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        // DEBUG: Check what's in the database before querying
        $clusterStats = DB::select("
            SELECT zoom, grid_size, COUNT(*) as count
            FROM clusters
            GROUP BY zoom, grid_size
            ORDER BY zoom, grid_size
        ");
        Log::info('Cluster stats in database', $clusterStats);

        // DEBUG: Check sample clusters at requested zoom
        $sampleClusters = DB::select("
            SELECT zoom, grid_size, lon, lat, point_count
            FROM clusters
            WHERE zoom = ?
            LIMIT 5
        ", [$zoom]);
        Log::info("Sample clusters at zoom {$zoom}", $sampleClusters);

        /* ------------------------------------------------------------
         * Fetch (or read from cache)
         * ------------------------------------------------------------ */
        $gridSize = config("clustering.zoom_levels.{$zoom}.grid");

        // DEBUG: Log grid size
        Log::info('Grid size for query', [
            'zoom' => $zoom,
            'gridSize' => $gridSize,
            'ignoreGridSize' => $ignoreGridSize,
            'config' => config("clustering.zoom_levels.{$zoom}"),
        ]);

        // Clear cache for debugging - remove this line after debugging
        Cache::forget($cacheKey);

        $geojson = Cache::remember($cacheKey, $ttl, function () use (
            $zoom, $gridSize, $ignoreGridSize,
            $minLon, $minLat, $maxLon, $maxLat, $limit
        ) {
            $sql = "
                SELECT lon, lat, point_count AS count
                FROM   clusters
                WHERE  zoom = ?
            ";
            $bind = [$zoom];

            // Handle grid_size - be more flexible with matching
            if ($gridSize !== null && !$ignoreGridSize) {
                // Check if clusters have NULL grid_size
                $hasNullGridSize = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM clusters
                    WHERE zoom = ? AND grid_size IS NULL
                ", [$zoom]);

                if ($hasNullGridSize && $hasNullGridSize->count > 0) {
                    Log::warning("Found clusters with NULL grid_size at zoom {$zoom}");
                    // Don't filter by grid_size if many are NULL
                } else {
                    $sql  .= " AND grid_size = ?";
                    $bind[] = $gridSize;
                }
            }

            // portable bbox filter (no ST_MakeEnvelope)
            $sql .= "
                AND lon BETWEEN ? AND ?
                AND lat BETWEEN ? AND ?
                LIMIT ?
            ";
            array_push($bind, $minLon, $maxLon, $minLat, $maxLat, $limit);

            // DEBUG: Log the actual query
            Log::info('Cluster query', [
                'sql' => $sql,
                'bind' => $bind,
            ]);

            $rows = DB::select($sql, $bind);

            // DEBUG: Log query results
            Log::info('Cluster query results', [
                'count' => count($rows),
                'first_5' => array_slice($rows, 0, 5),
            ]);

            // DEBUG: If no results, try diagnostic queries
            if (empty($rows)) {
                Log::warning('No clusters found with current query');

                // Check if ANY clusters exist at this zoom
                $anyAtZoom = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM clusters
                    WHERE zoom = ?
                ", [$zoom]);

                Log::info('Total clusters at zoom', [
                    'zoom' => $zoom,
                    'total' => $anyAtZoom->count,
                ]);

                // Check clusters in the bbox without zoom filter
                $inBbox = DB::select("
                    SELECT zoom, COUNT(*) as count
                    FROM clusters
                    WHERE lon BETWEEN ? AND ?
                    AND lat BETWEEN ? AND ?
                    GROUP BY zoom
                    ORDER BY zoom
                ", [$minLon, $maxLon, $minLat, $maxLat]);

                Log::info('Clusters in bbox by zoom', $inBbox);

                // Sample of what's actually in the area
                $samples = DB::select("
                    SELECT zoom, grid_size, lon, lat, point_count
                    FROM clusters
                    WHERE lon BETWEEN ? AND ?
                    AND lat BETWEEN ? AND ?
                    LIMIT 10
                ", [$minLon, $maxLon, $minLat, $maxLat]);

                Log::info('Sample clusters in bbox area', $samples);
            }

            return $this->createGeoJsonPoints('clusters', $rows);
        });

        // DEBUG: Log final response
        Log::info('Cluster response', [
            'feature_count' => count($geojson['features'] ?? []),
            'cache_key' => $cacheKey,
        ]);

        return response()
            ->json($geojson)
            ->header('Cache-Control', "public, max-age={$ttl}")
            ->header('ETag', $etag);
    }
}

/*
 * QUICK FIX OPTIONS:
 *
 * 1. To ignore grid_size filtering (if clusters have NULL or mismatched grid_size):
 *    Add to .env: CLUSTER_IGNORE_GRID_SIZE=true
 *
 * 2. To update existing clusters with correct grid_size values, run:
 *    UPDATE clusters SET grid_size = CASE
 *      WHEN zoom = 0 THEN 32.0
 *      WHEN zoom = 2 THEN 8.0
 *      WHEN zoom = 4 THEN 4.0
 *      WHEN zoom = 6 THEN 2.0
 *      WHEN zoom = 8 THEN 1.0
 *      WHEN zoom = 10 THEN 0.5
 *      WHEN zoom = 12 THEN 0.25
 *      WHEN zoom = 14 THEN 0.10
 *      WHEN zoom = 16 THEN 0.05
 *      ELSE grid_size
 *    END;
 *
 * 3. After debugging, remove:
 *    - The Cache::forget() line
 *    - The debug Log:: statements
 *    - The CLUSTER_IGNORE_GRID_SIZE check
 */
