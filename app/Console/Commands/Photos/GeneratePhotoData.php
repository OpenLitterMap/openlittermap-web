<?php

namespace App\Console\Commands\Photos;

use App\Models\Photo;
use App\Models\Cluster;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class GeneratePhotoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:generate-jsons';

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

    private function generateData($photos, $lastPhotoGenerated, $chunkCounter){
        $chunkCounter++;
        $last = floor($lastPhotoGenerated / 5000);
        if(sizeof($photos) == 0){
            echo "Nothing to create/update\n";
        }else{
            Storage::put('/data/lastPhotoGenerated.txt', $photos[sizeof($photos)-1]->id);
        }
        echo "Chunk " . $chunkCounter . " out of " . $last ."\n";

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
        }if(sizeof($photos) == 0){
            echo "Nothing to create/update\n";
        }else{
            Storage::put('/data/lastPhotoGenerated.txt', $photos[sizeof($photos)-1]->id);
        }
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
    }

    public function handle()
    {
        $start = microtime(true);

        $lastPhotoGenerated = (int)Storage::get('/data/lastPhotoGenerated.txt');
        $chunkCounter = 0;
        echo "Creating/updating yyyy/m/dd-mm-yyyy-photos.json\nDO NOT INTERRUPT THIS PROCESS\n";
        $lastPhotoGenerated ?
            Photo::select('lat', 'lon', 'created_at','datetime','id')->where('id','>',$lastPhotoGenerated)->chunk(5000, function ($photos) use ($lastPhotoGenerated, $chunkCounter) {
                $this->generateData($photos, $lastPhotoGenerated, $chunkCounter);
            })
            :
            Photo::select('lat', 'lon', 'created_at','datetime','id')->chunk(5000, function ($photos) use ($lastPhotoGenerated, $chunkCounter) {
                $this->generateData($photos, $lastPhotoGenerated, $chunkCounter);
            });


        // Remove "compiling data" from global map

        // end timer
        $finish = microtime(true);
        echo "Total Time: " . ($finish - $start) . "\n";
    }
}
