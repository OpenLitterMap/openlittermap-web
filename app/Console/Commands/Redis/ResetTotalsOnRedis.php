<?php

namespace App\Console\Commands\Redis;

use App\Models\Photo;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ResetTotalsOnRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:reset-all-totals-for {type}';

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
     * Reset and Update the total values for a location type
     *
     * Countries, States or Cities
     */
    public function handle()
    {
        $featureType = $this->argument('type');

        if ($featureType === "country") {
            $features = Country::select('id')->orderBy('id')->get();
            $feature_id = "country_id";
        } elseif ($featureType === "state") {
            $features = State::select('id')->orderBy('id')->get();
            $feature_id = "state_id";
        } elseif ($featureType === "city") {
            $features = City::select('id')->orderBy('id')->get();
            $feature_id = "city_id";
        } elseif ($featureType === "user") {
            $features = User::select('id')->where('has_uploaded', 1)->orderBy('id')->get();
            $feature_id = "user_id";
        } else
        {
            echo "Wrong location type provided. Must be 'country', 'state', or 'city'";

            return;
        }

        $categories = Photo::categories();
        $brands = Photo::getBrands();

        foreach ($features as $feature)
        {
            echo "$featureType.id $feature->id \n";

            // country:1 total_photos
            // state:1 total_photos
            // city:1 total_photos
            // user:1 total_photos
            Redis::hdel("$featureType:$feature->id", "total_photos");
            Redis::hdel("$featureType:$feature->id", "total_litter");
            Redis::hdel("$featureType:$feature->id", "total_brands");

            $total_photos = Photo::where([
                $feature_id => $feature->id,
                ['verified', '>=', 2]
            ])->count();

            Redis::hincrby("$featureType:$feature->id", "total_photos", $total_photos);

            $total_litter = 0;
            $total_brands = 0;

            foreach ($categories as $category)
            {
                echo "Category $category \n";

                $total_category = 0;
                $categoryId = $category."_id";

                // Load all of the verified photos for this feature,
                // for this category
                $photos = Photo::where([
                    $feature_id => $feature->id,
                    [$categoryId, '!=', null],
                    ['verified', '>=', 2]
                ])->get();

                foreach ($photos as $photo)
                {
                    echo "Photo.id $photo->id \n";

                    if ($category === "brands")
                    {
                        $total_brands += $photo->brands->total();

                        foreach ($brands as $brand)
                        {
                            if ($photo->brands->$brand)
                            {
                                Redis::hincrby("$featureType:$feature->id", $brand, $photo->brands->$brand);

                                $this->$brand = $photo->brands->$brand;
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
                    Redis::hdel("$featureType:$feature->id", $category);

                    Redis::hincrby("$featureType:$feature->id", $category, $total_category);

                    $total_litter += $total_category;
                }
            }

            if ($total_litter > 0)
            {
                Redis::hincrby("$featureType:$feature->id", "total_litter", $total_litter);
            }

            if ($total_brands > 0)
            {
                Redis::hincrby("$featureType:$feature->id", "total_brands", $total_brands);
            }
        }
    }
}
