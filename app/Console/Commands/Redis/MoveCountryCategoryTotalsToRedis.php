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
            foreach ($categories as $category)
            {
                $categoryTotal = 0;
                $categoryId = $category."_id";

                $photos = Photo::where([
                    'country_id' => $country->id,
                    [$categoryId, '!=', null]
                ])->get();

                foreach ($photos as $photo)
                {
                    if ($category === "brands")
                    {

                    }

                    if ($photo->$category)
                    {
                        $categoryTotal += $photo->$category->total();
                    }
                }

                if ($categoryTotal >= 0)
                {
                    Redis::hdel("country:$country->id", $category);

                    Redis::hincrby("country:$country->id", $category, $categoryTotal);
                }
            }

            foreach ($brands as $brand)
            {
                $total_brand = "total_$brand";

                if ($country->$total_brand)
                {
                    Redis::hdel("country:$country->id", $brand);

                    Redis::hincrby("country:$country->id", $brand, $country->$total_brand);
                }
            }
        }
    }
}
