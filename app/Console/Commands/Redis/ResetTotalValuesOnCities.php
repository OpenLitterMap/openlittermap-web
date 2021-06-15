<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\City;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetTotalValuesOnCities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:reset-total-on-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For each city, move all total values to redis';

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
        $cities = City::select('id')->where('total_images', '>', 0)->get();

        foreach ($cities as $city)
        {
            $query = Photo::where([
                ['verified', '>=', 2],
                'city_id' => $city->id
            ]);

            $total_photos = $query->count();
            $total_litter = $query->sum('total_litter');

            Redis::hincrby("city:$city->id", "total_photos", $total_photos);
            Redis::hincrby("city:$city->id", "total_litter", $total_litter);
        }
    }
}
