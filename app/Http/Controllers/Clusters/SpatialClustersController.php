<?php

namespace App\Http\Controllers\Clusters;

use App\Services\Clustering\SpatialClusteringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpatialClustersController extends Controller
{
    private SpatialClusteringService $clustering;

    public function __construct(SpatialClusteringService $clustering)
    {
        $this->clustering = $clustering;
    }

    /**
     * Get clusters for map viewport using spatial queries
     *
     * GET /api/v2/clusters?bbox=minLat,maxLat,minLon,maxLon&zoom=12&year=2024
     */
    public function getClusters(Request $request)
    {
        $request->validate([
            'bbox' => 'required|string',
            'zoom' => 'required|integer|between:2,20',
            'year' => 'nullable|integer|min:2000|max:2100'
        ]);

        [$minLat, $maxLat, $minLon, $maxLon] = array_map('floatval', explode(',', $request->bbox));
        $zoom = (int) $request->zoom;
        $year = $request->year ? (int) $request->year : null;

        // Use spatial index for efficient bbox query
        $clusters = $this->clustering->getClustersInBounds(
            $minLat,
            $maxLat,
            $minLon,
            $maxLon,
            $zoom,
            $year
        );

        // Calculate viewport statistics
        $stats = $this->getViewportStats($minLat, $maxLat, $minLon, $maxLon, $year);

        return response()->json([
            'clusters' => $clusters,
            'stats' => $stats,
            'zoom' => $zoom,
            'bounds' => [
                'minLat' => $minLat,
                'maxLat' => $maxLat,
                'minLon' => $minLon,
                'maxLon' => $maxLon
            ]
        ]);
    }

    /**
     * Get nearby clusters using spatial distance queries
     *
     * GET /api/v2/clusters/nearby?lat=51.5&lon=-0.1&radius=5&zoom=12
     */
    public function getNearbyClusters(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lon' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0.1|max:100', // km
            'zoom' => 'required|integer|between:2,20',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $lat = (float) $request->lat;
        $lon = (float) $request->lon;
        $radiusKm = (float) $request->radius;
        $zoom = (int) $request->zoom;
        $limit = (int) ($request->limit ?? 50);

        // Use spatial distance function
        $clusters = DB::select("
            SELECT
                id,
                lat,
                lon,
                point_count,
                ST_Distance_Sphere(center_point, ST_PointFromText(?, 4326)) / 1000 as distance_km
            FROM clusters
            WHERE zoom = ?
                AND ST_Distance_Sphere(center_point, ST_PointFromText(?, 4326)) <= ?
            ORDER BY distance_km
            LIMIT ?
        ", [
            "POINT($lon $lat)",
            $zoom,
            "POINT($lon $lat)",
            $radiusKm * 1000,
            $limit
        ]);

        return response()->json([
            'clusters' => $clusters,
            'center' => ['lat' => $lat, 'lon' => $lon],
            'radius_km' => $radiusKm,
            'zoom' => $zoom
        ]);
    }

    /**
     * Get cluster details with spatial photo relationships
     *
     * GET /api/v2/clusters/123
     */
    public function getClusterDetails(int $clusterId)
    {
        $cluster = DB::selectOne("
            SELECT
                c.*,
                ST_AsGeoJSON(c.cluster_bounds) as bounds_geojson,
                ST_Area(c.cluster_bounds) * 111319.9 * 111319.9 as area_m2,
                (
                    SELECT COUNT(*)
                    FROM photos p
                    WHERE ST_Within(p.location, c.cluster_bounds)
                        AND p.verified = 2
                ) as actual_photo_count
            FROM clusters c
            WHERE c.id = ?
        ", [$clusterId]);

        if (!$cluster) {
            return response()->json(['error' => 'Cluster not found'], 404);
        }

        // Get photos within cluster bounds
        $photos = $this->clustering->getPhotosInCluster($clusterId);

        return response()->json([
            'cluster' => [
                'id' => $cluster->id,
                'lat' => $cluster->lat,
                'lon' => $cluster->lon,
                'point_count' => $cluster->point_count,
                'actual_count' => $cluster->actual_photo_count,
                'zoom' => $cluster->zoom,
                'area_m2' => round($cluster->area_m2),
                'bounds' => json_decode($cluster->bounds_geojson)
            ],
            'photos' => $photos
        ]);
    }

    /**
     * Get heatmap data using spatial aggregation
     *
     * GET /api/v2/heatmap?bbox=...&resolution=0.1
     */
    public function getHeatmap(Request $request)
    {
        $request->validate([
            'bbox' => 'required|string',
            'resolution' => 'nullable|numeric|min:0.01|max:1' // degrees
        ]);

        [$minLat, $maxLat, $minLon, $maxLon] = array_map('floatval', explode(',', $request->bbox));
        $resolution = (float) ($request->resolution ?? 0.1);

        // Create heatmap using spatial grid aggregation
        $heatmap = DB::select("
            SELECT
                ROUND(ST_Y(location) / ?) * ? as lat,
                ROUND(ST_X(location) / ?) * ? as lon,
                COUNT(*) as intensity
            FROM photos
            WHERE verified = 2
                AND location IS NOT NULL
                AND ST_Within(
                    location,
                    ST_GeomFromText(?, 4326)
                )
            GROUP BY lat, lon
            HAVING intensity > 0
        ", [
            $resolution, $resolution,
            $resolution, $resolution,
            "POLYGON(($minLon $minLat, $maxLon $minLat, $maxLon $maxLat, $minLon $maxLat, $minLon $minLat))"
        ]);

        return response()->json([
            'heatmap' => $heatmap,
            'resolution' => $resolution,
            'bounds' => [
                'minLat' => $minLat,
                'maxLat' => $maxLat,
                'minLon' => $minLon,
                'maxLon' => $maxLon
            ]
        ]);
    }

    private function getViewportStats(float $minLat, float $maxLat, float $minLon, float $maxLon, ?int $year): array
    {
        $bbox = "POLYGON(($minLon $minLat, $maxLon $minLat, $maxLon $maxLat, $minLon $maxLat, $minLon $minLat))";

        $stats = DB::selectOne("
            SELECT
                COUNT(DISTINCT p.id) as photo_count,
                COUNT(DISTINCT p.user_id) as contributor_count,
                MIN(p.created_at) as oldest_photo,
                MAX(p.created_at) as newest_photo
            FROM photos p
            WHERE p.verified = 2
                AND p.location IS NOT NULL
                AND ST_Within(p.location, ST_GeomFromText(?, 4326))
                " . ($year ? "AND YEAR(p.created_at) = ?" : "") . "
        ", array_filter([$bbox, $year]));

        return [
            'photo_count' => (int) $stats->photo_count,
            'contributor_count' => (int) $stats->contributor_count,
            'date_range' => [
                'oldest' => $stats->oldest_photo,
                'newest' => $stats->newest_photo
            ]
        ];
    }
}
