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
     * Generate Clusters for All Photos
     *
     * Todo - Load photos as geojson without looping over them and inserting into another array
     * Todo - Chunk photos (ideally as geojson) without having to loop over a very large array (155k+)
     * Todo - Append to file instead of re-writing it
     * Todo - Split file into multiple files
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
     */
    protected function generateFeatures(): void
    {
        // 100,000 photos and growing...
        // ->whereDate('created_at', '>', '2020-10-01 00:00:00') // for testing smaller amounts of data

        $this->info('Generating features...');

        $bar = $this->output->createProgressBar(Photo::count());
        $bar->setFormat('debug');
        $bar->start();

        $photos = Photo::select('lat', 'lon')->cursor();

        $features = [];

        foreach ($photos as $photo) {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ]
            ];

            $features[] = $feature;

            $bar->advance();
        }

        $bar->finish();

        $this->info("\nFeatures finished...");

        $features = json_encode($features, JSON_NUMERIC_CHECK);

        Storage::put('/data/features.json', $features);
    }

    protected function generateClusters(): void
    {
        // delete all clusters?
        // Or update existing ones?
        Cluster::truncate();

        $zoomLevels = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

        foreach ($zoomLevels as $zoomLevel) {
            $this->line("Zoom level " . $zoomLevel);

            exec('node app/Node/supercluster-php ' . config('app.root_dir') . ' ' . $zoomLevel);

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
