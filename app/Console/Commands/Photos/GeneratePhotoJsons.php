<?php

namespace App\Console\Commands\Clusters;

use App\Models\Photo;
use App\Models\Cluster;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class GeneratePhotoJsons extends Command
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
    protected $description = 'Generate photo json files in data/yyyy/mm/dd-mm-yyyy-photos.json';

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
        $start = microtime(true);

        $lastPhotoGenerated = (int)Storage::get('/data/lastPhotoGenerated.txt');

        $lastPhotoGenerated ?
            $photos = Photo::select('lat', 'lon', 'created_at','datetime','id')->where('id','>',$lastPhotoGenerated)->get()
        :
            $photos = Photo::select('lat', 'lon', 'created_at','datetime','id')->get();

        if(sizeof($photos) == 0){
            echo "Nothing to create/update\n";
        }else{
            Storage::put('/data/lastPhotoGenerated.txt', $photos[sizeof($photos)-1]->id);
        }
        echo "Creating/updating yyyy/m/dd-mm-yyyy-photos.json\n";
        echo "Appending " . sizeof($photos) . " photos\n";

        foreach ($photos as $photo)
        {
            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$photo->lon, $photo->lat]
                ],
                'datetimes' => [
                    'taken' => $photo->datetime
                ],
                'photo_id' => $photo->id
            ];

            $date = Carbon::parse($photo->datetime);

            $y = $date->year;
            $m = $date->month;
            $d = $date->day;

            if (!Storage::disk('local')->exists("data/".$y."/".$m)) {
                Storage::disk('local')->makeDirectory("data/".$y."/".$m);
                echo "created directory "."data/".$y."/".$m."\n";
            }
            if (Storage::disk('local')->exists("data/".$y."/".$m."/".$d."-".$m."-".$y."-photos.json")) {
                $exist = json_decode(Storage::get("data/".$y."/".$m."/".$d."-".$m."-".$y."-photos.json"), true);
                array_push($exist, $feature);
                $exist = json_encode($exist, JSON_NUMERIC_CHECK);
                Storage::put("data/".$y."/".$m."/".$d."-".$m."-".$y."-photos.json", $exist);
            }else{
                $features = [];
                array_push($features, $feature);
                $features = json_encode($features, JSON_NUMERIC_CHECK);
                Storage::put("data/".$y."/".$m."/".$d."-".$m."-".$y."-photos.json", $features);
            }
        }

        // Remove "compiling data" from global map

        // end timer
        $finish = microtime(true);
        echo "Total Time: " . ($finish - $start) . "\n";
    }
}
