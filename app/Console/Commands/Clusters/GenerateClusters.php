<?php

namespace App\Console\Commands\Clusters;

use App\Models\Photo;
use App\Models\Cluster;

use Illuminate\Console\Command;
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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Generate Clusters for All Photos
     *
     * Todo - Load photos as geojson without looping over them and inserting into another array
     * Todo - Find a way to update clusters instead of deleting all and re-writing all every time..
     * Todo - Cluster data by "today", "one-week", "one-month", "one-year"
     * Todo - Cluster data by year, 2021, 2020...
     */
    public function handle()
    {
        $start = microtime(true);

        $this->generateFeatures();
        $this->generateClusters();

        $finish = microtime(true);
        $this->newLine();
        $this->info("Total Time: " . ($finish - $start) . "\n");
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
    protected function generateFeatures (): void
    {
        $this->info('Generating features...');

        $progressBar = $this->output->createProgressBar(Photo::count());
        $progressBar->setFormat('debug');
        $progressBar->start();

        $photos = Photo::select('lat', 'lon')->cursor();

        $features = [];

        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ]
            ];

            array_push($features, $feature);

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
     *
     */
    protected function generateClusters (): void
    {
        // Delete all clusters
        Cluster::truncate();

        // We want to create clusters for each of these zoom levels
        $zoomLevels = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

        // For each zoom level, create clusters.
        foreach ($zoomLevels as $zoomLevel)
        {
            $this->line("Zoom level: $zoomLevel");

            // Supercluster is awesome open-source javascript code from MapBox that we made executable on the backend with php
            // This file uses features.json to create clusters.json for a specific zoom level.
            exec('node app/Node/supercluster-php ' . config('app.root_dir') . ' ' . $zoomLevel);

            // We then use the clusters.json and save it to the clusters table
            collect(json_decode(Storage::get('/data/clusters.json')))
                ->filter(function ($cluster) {
                    return isset($cluster->properties);
                })
                ->map(function ($cluster) use ($zoomLevel) {
                    return [
                        'lat' => $cluster->geometry->coordinates[1],
                        'lon' => $cluster->geometry->coordinates[0],
                        'point_count' => $cluster->properties->point_count,
                        'point_count_abbreviated' => $cluster->properties->point_count_abbreviated,
                        'geohash' => \GeoHash::encode($cluster->geometry->coordinates[1], $cluster->geometry->coordinates[0]),
                        'zoom' => $zoomLevel
                    ];
                })
                ->chunk(1000)
                ->each(function ($chunk) {
                    Cluster::insert($chunk->toArray());
                });
        }
    }
}
