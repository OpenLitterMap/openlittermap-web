<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\City;
use App\Models\Location\Location;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MoveCityCategoryTotalsToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:move-cities-category-totals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get all category totals and move them to redis';

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
        $cities = City::all();

        $categories = Photo::categories();
        $brands = Photo::getBrands();

        foreach ($cities as $city)
        {
            // total_smoking, etc
            foreach ($categories as $category)
            {
                $total_category = "total_$category";

                if ($city->$total_category)
                {
                    Redis::del("city:$city->id", $total_category);

                    Redis::hincrby("city:$city->id", $total_category, $city->$total_category);
                }
            }

            // total_coke, total_pepsi, etc
            foreach ($brands as $brand)
            {
                $total_brand = "total_$brand";

                if ($city->$total_brand)
                {
                    Redis::del("city:$city->id", $total_brand);

                    Redis::hincrby("city:$city->id", $total_brand, $city->$total_brand);
                }
            }
        }
    }
}
