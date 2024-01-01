<?php

namespace App\Console\Commands\Clusters;

use Illuminate\Support\Facades\Log;
use GeoHash;
use App\Models\Photo;
use App\Models\Cluster;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateClusters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clusters:generate-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all clusters for all photos';

    /**
     * Generate Clusters for All Photos
     */
    public function handle()
    {
        Log::info('--- Clustering began ---');

        $start = microtime(true);

        foreach ($this->getYearsWithNewPhotos() as $year) {
            $this->line("\nYear: " . ($year ?: 'All Time'));
            $this->generateFeatures($year);
            $this->generateClusters($year);
        }

        $finish = microtime(true);
        $this->newLine();
        $this->info("Total Time: " . ($finish - $start) . "\n");

        Log::info('--- Clustering finished ---');
    }

    /**
     * Generates features.json
     *
     * This is a geojson file containing all the features we want to cluster.
     *
     * Currently, this is used to create 1 large file representing all our data
     *
     * We save this file to storage and use it to populate the clusters with a node script in the backend
     */
    protected function generateFeatures (int $year = null): void
    {
        $this->info('Generating features...');

        $photos = Photo::query()->select('lat', 'lon');

        if ($year) {
            $photos->whereYear('datetime', $year);
        }

        $progressBar = $this->output->createProgressBar($photos->count());
        $progressBar->setFormat('debug');
        $progressBar->start();

        $features = [];

        foreach ($photos->cursor() as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ]
            ];

            $features[] = $feature;

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->info("\nFeatures finished...");

        $features = json_encode($features, JSON_NUMERIC_CHECK);

        Storage::put('/data/features.json', $features);
    }

    /**
     * Using the features.json file, we cluster our data at various zoom levels.
     *
     * First, we need to delete all clusters. Then we re-create them from scratch.
     *
     */
    protected function generateClusters (int $year = null): void
    {
        $this->info("Generating clusters for each zoom level...");

        // Delete all clusters for year
        if ($year) {
            Cluster::where('year', $year)->delete();
        } else {
            Cluster::whereNull('year')->delete();
        }

        $bar = $this->output->createProgressBar(16);
        $bar->setFormat('debug');
        $bar->start();

        // We want to create clusters for each of these zoom levels
        $zoomLevels = range(2, 16);

        // For each zoom level, create clusters.
        foreach ($zoomLevels as $zoomLevel)
        {
            // Supercluster is awesome open-source javascript code from MapBox that we made executable on the backend with php
            // This file uses features.json to create clusters.json for a specific zoom level.
            exec('node app/Node/supercluster-php ' . base_path() . ' ' . $zoomLevel);

            // We then use the clusters.json and save it to the clusters table
            collect(json_decode(Storage::get('/data/clusters.json')))
                ->filter(function ($cluster) {
                    return isset($cluster->properties);
                })
                ->map(function ($cluster) use ($zoomLevel, $year) {
                    return [
                        'lat' => $cluster->geometry->coordinates[1],
                        'lon' => $cluster->geometry->coordinates[0],
                        'point_count' => $cluster->properties->point_count,
                        'point_count_abbreviated' => $cluster->properties->point_count_abbreviated,
                        'geohash' => GeoHash::encode($cluster->geometry->coordinates[1], $cluster->geometry->coordinates[0]),
                        'zoom' => $zoomLevel,
                        'year' => $year
                    ];
                })
                ->chunk(1000)
                ->each(function ($chunk) {
                    Cluster::insert($chunk->toArray());
                });

            $bar->advance();
        }

        $bar->finish();

        $this->info("\nClusters finished...");
    }

    /**
     * Checks the photos uploaded in the last day
     * If any of them has been taken in the years 2017-current, that year needs re-clustering
     * We always cluster for all years, regardless
     */
    private function getYearsWithNewPhotos(): array
    {
        $yearsWithData = [];
        $years = range(2017, now()->year);

        foreach ($years as $year) {
            $hasRecentPhotosForYear = Photo::query()->where([
                ['created_at', '>=', now()->subDay()->startOfDay()],
                [DB::raw('year(datetime)'), '=', $year]
            ])->exists();

            if ($hasRecentPhotosForYear) {
                $yearsWithData[] = $year;
            } else {
                $this->line("\nNo new photos for $year.");
            }
        }

        return $yearsWithData === []
            ? []
            : array_merge([null], $yearsWithData);
    }
}
