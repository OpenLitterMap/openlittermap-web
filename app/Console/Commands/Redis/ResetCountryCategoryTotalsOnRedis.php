<?php

namespace App\Console\Commands\Redis;

use App\Models\Location\Country;
use App\Models\Location\Location;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetCountryCategoryTotalsOnRedis extends Command
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
            Redis::hdel("country:$country->id", "total_photos");
            Redis::hdel("country:$country->id", "total_litter");
            Redis::hdel("country:$country->id", "total_brands");

            $total_photos = Photo::where([
                'country_id' => $country->id,
                ['verified', '>=', 2]
            ])->count();
            $total_litter = 0;
            $total_brands = 0;

            Redis::hincrby("country:$country->id", "total_photos", $total_photos);

            foreach ($categories as $category)
            {
                $total_category = 0;
                $categoryId = $category."_id";

                // Load all of the photos for this country, for this category
                $photos = Photo::where([
                    'country_id' => $country->id,
                    [$categoryId, '!=', null],
                    ['verified', '>=', 2]
                ])->get();

                foreach ($photos as $photo)
                {
                    if ($category === "brands")
                    {
                        $total_brands += $photo->brands->total();

                        foreach ($brands as $brand)
                        {
                            if ($photo->brands->$brand)
                            {
                                Redis::hincrby("country:$country->id", $brand, $photo->brands->$brand);
                            }
                        }
                    }
                    else
                    {
                        $total_category += $photo->$category->total();
                    }
                }

                if ($total_category >= 0 && $category !== "brands")
                {
                    Redis::hdel("country:$country->id", $category);

                    Redis::hincrby("country:$country->id", $category, $total_category);

                    $total_litter += $total_category;
                }
            }

            if ($total_litter > 0)
            {
                Redis::hincrby("country:$country->id", "total_litter", $total_litter);
            }

            if ($total_brands > 0)
            {
                Redis::del("country:$country->id", "total_brands", $total_brands);
            }
        }
    }
}
