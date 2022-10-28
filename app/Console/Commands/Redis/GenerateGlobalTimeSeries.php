<?php

namespace App\Console\Commands\Redis;

use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\Location;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Console\Output\ConsoleOutput;

class GenerateGlobalTimeSeries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:Generate-Global-Time-Series';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all photos_per_month on the countries table and move them to redis';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $countries = Country::where('photos_per_month', '!=', null)->get();;

        foreach($countries as $country)
        {
            Redis::del("countries:$country->id:ppm");
            $ppm = json_decode($country->photos_per_month, true);
            $keys = array_keys($ppm);

            $i = 0;
            foreach($ppm as $value) {
                Redis::hincrby("countries:$country->id:ppm", $keys[$i], $value);
                echo ($i+1)." out of ".count($ppm)."\n";
                $i++;
            }
        }
    }
}
