<?php

namespace App\Console\Commands\Locations\CreatedBy\Locations\CreatedBy\Locations\CreatedBy\Locations;

use App\Models\Location\City;
use App\Models\Photo;
use Illuminate\Console\Command;

class UpdateCities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:test-update-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all values for cities verified data';

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
     * @return mixed
     */
    public function handle()
    {
        $cities = City::all();

        $total = 0;

        foreach ($cities as $city)
        {
            $state_total = 0;

            $categories = [
                'alcohol',
                'coastal',
                'coffee',
                'dumping',
                'food',
                'industrial',
                'other',
                'sanitary',
                'softdrinks',
                'smoking'
            ];

            echo "City.id " . $city->id . "\n";

            $count = Photo::where([
                'city_id' => $city->id,
                'verified' => 2
            ])->count();

            echo "Total photos " . $count . "\n";

            foreach ($categories as $category)
            {
                $category_id = $category . '_id';
                $category_total = 0;

                $photos = Photo::where('verified', 2)->where('city_id', $city->id)->whereNotNull($category_id)->get();

                echo "category count " . sizeof($photos). "\n";

                foreach ($photos as $photo)
                {
                    if ($photo->$category) $category_total += $photo->$category->total();
                }

                echo "Category total " . $category_total . "\n";

                $state_total += $category_total;

                echo "Country total " . $state_total . "\n";
            }

            $city->total_litter = $state_total;
            $city->save();

            $total += $state_total;

            echo "\n \n";
        }

        echo "Total " . $total . "\n";
    }
}
