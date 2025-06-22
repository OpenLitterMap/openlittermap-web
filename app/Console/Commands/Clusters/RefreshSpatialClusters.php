<?php

namespace App\Console\Commands\Clusters;

use App\Services\Clustering\SpatialClusteringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshSpatialClusters extends Command
{
    protected $signature = 'clusters:spatial-refresh
                            {--bbox= : Process tiles within bounds (minLat,maxLat,minLon,maxLon)}
                            {--radius= : Process tiles within radius km of a point (lat,lon,radius)}
                            {--hours=24 : Look back N hours for changes}
                            {--all : Rebuild all tiles}
                            {--year= : Limit to specific year}
                            {--parallel=1 : Number of parallel workers}';

    protected $description = 'Rebuild clusters using spatial operations';

    private SpatialClusteringService $svc;

    public function __construct(SpatialClusteringService $svc)
    {
        parent::__construct();
        $this->svc = $svc;
    }

    public function handle(): int
    {
        $startedAt = microtime(true);
        $year = $this->option('year') ? (int)$this->option('year') : null;

        // Determine which tiles to process
        $tiles = $this->determineTilesToProcess();

        if (empty($tiles)) {
            $this->info('No tiles to process.');
            return 0;
        }

        $this->info(sprintf(
            "Processing %d tiles%s using spatial operations...\n",
            count($tiles),
            $year ? " for year $year" : ""
        ));

        $bar = $this->output->createProgressBar(count($tiles));
        $bar->start();

        $stats = [
            'tiles' => 0,
            'photos' => 0,
            'clusters' => 0,
            'failed' => 0,
        ];

        // Process tiles (could be parallelized in production)
        foreach ($tiles as $tileKey) {
            try {
                $result = $this->svc->rebuildTile($tileKey, $year);
                $stats['tiles']++;
                $stats['photos'] += $result['photos'];
                $stats['clusters'] += $result['clusters'];
            } catch (\Exception $e) {
                $stats['failed']++;
                $this->error("\nFailed to process tile $tileKey: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $duration = microtime(true) - $startedAt;
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tiles processed', number_format($stats['tiles'])],
                ['Tiles failed', number_format($stats['failed'])],
                ['Photos clustered', number_format($stats['photos'])],
                ['Clusters created', number_format($stats['clusters'])],
                ['Duration', sprintf('%.2f seconds', $duration)],
                ['Throughput', sprintf('%.2f tiles/sec', $stats['tiles'] / max(1, $duration))],
            ]
        );

        // Show spatial statistics if in debug mode
        if (config('clustering.debug')) {
            $this->showSpatialStats($tiles);
        }

        return $stats['failed'] > 0 ? 1 : 0;
    }

    private function determineTilesToProcess(): array
    {
        // Process by bounding box
        if ($bbox = $this->option('bbox')) {
            [$minLat, $maxLat, $minLon, $maxLon] = explode(',', $bbox);
            return $this->svc->findTilesInBounds(
                (float)$minLat,
                (float)$maxLat,
                (float)$minLon,
                (float)$maxLon
            );
        }

        // Process by radius from point
        if ($radius = $this->option('radius')) {
            [$lat, $lon, $radiusKm] = explode(',', $radius);
            return $this->findTilesInRadius((float)$lat, (float)$lon, (float)$radiusKm);
        }

        // Process all tiles
        if ($this->option('all')) {
            return DB::table('photos')
                ->where('verified', 2)
                ->whereNotNull('location')
                ->distinct()
                ->pluck('tile_key')
                ->toArray();
        }

        // Process recently updated tiles
        $hours = (int) $this->option('hours');
        return DB::select("
            SELECT DISTINCT p.tile_key
            FROM photos p
            JOIN spatial_tile_grid g ON p.tile_key = g.tile_key
            WHERE p.verified = 2
                AND p.location IS NOT NULL
                AND p.updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ", [$hours]);
    }

    private function findTilesInRadius(float $lat, float $lon, float $radiusKm): array
    {
        // Convert radius to degrees (approximate)
        $radiusDeg = $radiusKm / 111.0;

        return DB::select("
            SELECT tile_key
            FROM spatial_tile_grid
            WHERE ST_Distance_Sphere(
                center,
                ST_PointFromText(?, 4326)
            ) <= ?
        ", [
            "POINT($lon $lat)",
            $radiusKm * 1000 // Convert to meters
        ]);
    }

    private function showSpatialStats(array $tiles): void
    {
        $this->newLine();
        $this->info('📊 Spatial Statistics:');

        // Sample a few tiles for statistics
        $sampleTiles = array_slice($tiles, 0, min(5, count($tiles)));

        foreach ($sampleTiles as $tileKey) {
            $stats = $this->svc->getTileStatistics($tileKey);
            $this->line(sprintf(
                "  Tile %d: %d photos, %d clusters, %.2f km² coverage",
                $tileKey,
                $stats['photo_count'],
                $stats['cluster_count'],
                $stats['coverage_km2']
            ));
        }

        // Global statistics
        $globalStats = DB::selectOne("
            SELECT
                COUNT(DISTINCT p.id) as total_photos,
                COUNT(DISTINCT c.id) as total_clusters,
                COUNT(DISTINCT g.tile_key) as active_tiles,
                ST_Area(ST_ConvexHull(ST_Collect(p.location))) * 111319.9 * 111319.9 / 1000000 as total_coverage_km2
            FROM photos p
            LEFT JOIN clusters c ON ST_Within(p.location, c.cluster_bounds)
            LEFT JOIN spatial_tile_grid g ON ST_Within(p.location, g.bounds)
            WHERE p.verified = 2
        ");

        $this->newLine();
        $this->info('🌍 Global Coverage:');
        $this->line(sprintf("  Active tiles: %s", number_format($globalStats->active_tiles)));
        $this->line(sprintf("  Total photos: %s", number_format($globalStats->total_photos)));
        $this->line(sprintf("  Total clusters: %s", number_format($globalStats->total_clusters)));
        $this->line(sprintf("  Coverage area: %s km²", number_format($globalStats->total_coverage_km2, 2)));
    }
}
