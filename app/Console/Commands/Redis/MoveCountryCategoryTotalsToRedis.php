<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Country;
use App\Models\Location\Location;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MoveCountryCategoryTotalsToRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:move-countries-category-totals';

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
        $countries = Country::all();

        $categories = Photo::categories();

        $brands = Photo::getBrands();

        foreach ($countries as $country)
        {
            // total_smoking, etc
            foreach ($categories as $category)
            {
                $total_category = "total_$category";

                if ($country->$total_category)
                {
                    Redis::del("country:$country->id", $category);

                    Redis::hincrby("country:$country->id", $category, $country->$total_category);
                }
            }

            // total_coke, total_pepsi, etc
            foreach ($brands as $brand)
            {
                $total_brand = "total_$brand";

                if ($country->$total_brand)
                {
                    Redis::del("country:$country->id", $brand);

                    Redis::hincrby("country:$country->id", $brand, $country->$total_brand);
                }
            }
        }
    }
}
