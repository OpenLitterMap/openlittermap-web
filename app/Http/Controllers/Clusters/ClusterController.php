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
        /* ── 1. Available Zoom Levels from Config ──────────────────────── */
        $levels = config('clustering.zoom_levels.all', [0, 2, 4, 6, 8, 10, 12, 14, 16]);
        abort_if(empty($levels), 500, 'No zoom levels configured');

        /* ── 2. Validate Inputs ─────────────────────────────────────── */
        $request->validate([
            'zoom' => ['nullable', 'numeric', 'between:' . min($levels) . ',' . max($levels)],
            'bbox' => ['nullable', 'array'],
            'lat'  => ['nullable', 'numeric', 'between:-90,90'],
            'lon'  => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        /* ── 3. Snap Zoom to Available Level ────────────────────────── */
        $requested = (float) $request->input('zoom', $levels[0]);
        $zoom = collect($levels)->first(fn($z) => $z >= round($requested)) ?? end($levels);

        /* ── 4. Build Bounding Box ──────────────────────────── */
        $bbox = [-180, -90, 180, 90];

        if ($request->has('bbox')) {
            // a) Named keys or numeric indices → $arr
            $arr = $request->input('bbox');

            if (is_array($arr)) {
                $bbox = [
                    (float) ($arr['left']   ?? $arr[0] ?? -180),
                    (float) ($arr['bottom'] ?? $arr[1] ?? -90),
                    (float) ($arr['right']  ?? $arr[2] ?? 180),
                    (float) ($arr['top']    ?? $arr[3] ?? 90),
                ];
            }
            // b) Comma-separated string → explode
            elseif (is_string($arr = $request->query('bbox'))) {
                $parts = array_map('floatval', explode(',', $arr));
                if (count($parts) === 4) {
                    [$bbox[0], $bbox[1], $bbox[2], $bbox[3]] = $parts;
                }
            }
        }
        // c) Check for numeric index query params (bbox[0], bbox[1], etc)
        elseif ($request->has('bbox.0') || $request->query('bbox.0') !== null) {
            $bbox = [
                (float) $request->input('bbox.0', -180),
                (float) $request->input('bbox.1', -90),
                (float) $request->input('bbox.2', 180),
                (float) $request->input('bbox.3', 90),
            ];
        } elseif ($request->filled(['lat', 'lon'])) {
            // Create bbox from center point
            $size = 180 / pow(2, $zoom / 2);
            $bbox = [
                $request->lon - $size, $request->lat - $size,
                $request->lon + $size, $request->lat + $size,
            ];
        }

        // Clamp values to valid ranges
        [$west, $south, $east, $north] = [
            max($bbox[0], -180),
            max($bbox[1], -90),
            min($bbox[2], 180),
            min($bbox[3], 90),
        ];

        // Ensure south ≤ north (swap if inverted)
        if ($south > $north) {
            [$south, $north] = [$north, $south];
        }

        $crossesDateline = $west > $east;
        $bboxKey = sprintf('%.4f:%.4f:%.4f:%.4f', $west, $south, $east, $north);

        /* ── 5. Cache Settings ──────────────────────────── */
        $ttl = (int) config('clustering.cache_ttl', 300);
        $limit = (int) config('clustering.max_clusters_per_request', 5000);

        /* ── 6. ETag Computation (Data-based) ───────────────────────── */
        $etagCacheKey = "clusters:v5:etag:$zoom:$bboxKey";
        $etag = Cache::remember($etagCacheKey, $ttl, function () use ($zoom) {
            $stats = DB::table('clusters')
                ->where('zoom', $zoom)
                ->selectRaw('MAX(updated_at) as latest, COUNT(*) as cnt')
                ->first();

            return '"' . md5(($stats->latest ?? '') . '|' . ($stats->cnt ?? 0)) . '"';
        });

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        /* ── 7. Fetch Clusters (Cached) ─────────────────────────────── */
        $dataKey = "clusters:v5:$zoom:$bboxKey";

        $geojson = Cache::remember($dataKey, $ttl, function () use (
            $zoom,
            $west, $south, $east, $north,
            $limit, $crossesDateline
        ) {
            $query = DB::table('clusters')
                ->select('lon', 'lat', 'point_count as count')
                ->where('zoom', $zoom)
                ->whereBetween('lat', [$south, $north])
                ->limit($limit);

            // Apply longitude filter based on dateline crossing
            if ($crossesDateline) {
                $query->where(function ($q) use ($west, $east) {
                    $q->where('lon', '>=', $west)
                        ->orWhere('lon', '<=', $east);
                });
            } else {
                $query->whereBetween('lon', [$west, $east]);
            }

            // Order for deterministic results
            $query->orderBy('lat')->orderBy('lon');

            $rows = $query->get();

            // Log performance metrics
            if (config('app.debug')) {
                Log::debug('Cluster query performance', [
                    'zoom' => $zoom,
                    'bbox' => [$west, $south, $east, $north],
                    'clusters_returned' => $rows->count(),
                    'memory_usage_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
                ]);
            }

            return $this->createGeoJsonPoints('clusters', $rows, true);
        });

        return response()
            ->json($geojson)
            ->header('Cache-Control', "public, max-age=$ttl")
            ->header('ETag', $etag)
            ->header('X-Cluster-Zoom', $zoom);
    }

    /**
     * Get available zoom levels
     */
    public function zoomLevels()
    {
        return response()->json([
            'zoom_levels' => config('clustering.zoom_levels.all', []),
            'global_zooms' => config('clustering.zoom_levels.global', []),
            'tile_zooms' => config('clustering.zoom_levels.tile', []),
        ]);
    }
}
