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
     * Todo - Chunk photos (ideally as geojson) without having to loop over a very large array (155k+)
     * Todo - Append to file instead of re-writing it
     * Todo - Split file into multiple files
     * Todo - Find a way to update clusters instead of deleting all and re-writing all every time..
     * Todo - Cluster data by "today", "one-week", "one-month", "one-year"
     * Todo - Cluster data by year, 2021, 2020...
     */
    public function handle()
    {
        // 100,000 photos and growing...
        // ->whereDate('created_at', '>', '2020-10-01 00:00:00') // for testing smaller amounts of data

        // begin timer
        $start = microtime(true);

        $photos = Photo::select('lat', 'lon')->get();

        echo "size of photos " . sizeof($photos) . "\n";

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
        }

        unset($photos); // free up memory

        $features = json_encode($features, JSON_NUMERIC_CHECK);

        Storage::put('/data/features.json', $features);


        if (app()->environment() === 'local')
        {
            $prefix = '/home/vagrant/Code/olm';
        }
        else if (app()->environment() === 'staging')
        {
            $prefix = '/home/forge/olmdev.online';
        }
        else
        {
            $prefix = '/home/forge/openlittermap.com';
        }

        // delete all clusters?
        // Or update existing ones?

        Cluster::truncate();

        // Put "recompiling data" onto Global Map

        $zoomLevels = [2,3,4,5,6,7,8,9,10,11,12,13,14,15,16];

        foreach ($zoomLevels as $zoomLevel)
        {
            echo "Zoom level " . $zoomLevel . " \n";
            exec('node app/Node/supercluster-php ' . $prefix . ' ' . $zoomLevel);

            $clusters = json_decode(Storage::get('/data/clusters.json'));

            foreach ($clusters as $cluster)
            {
                if (isset($cluster->properties))
                {
                    Cluster::create([
                        'lat' => $cluster->geometry->coordinates[1],
                        'lon' => $cluster->geometry->coordinates[0],
                        'point_count' => $cluster->properties->point_count,
                        'point_count_abbreviated' => $cluster->properties->point_count_abbreviated,
                        'geohash' => \GeoHash::encode($cluster->geometry->coordinates[1], $cluster->geometry->coordinates[0]),
                        'zoom' => $zoomLevel
                    ]);
                }
            }
        }

        // Remove "compiling data" from global map

        // end timer
        $finish = microtime(true);
        echo "Total Time: " . ($finish - $start) . "\n";
    }
}
