<?php

namespace App\Console\Commands\Locations;

use App\Models\Location\Country;
use App\Models\Photo;

use Illuminate\Console\Command;

class UpdateCountries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-countries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all values for countries verified data';

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
        $countries = Country::all();

        $total = 0;

        foreach ($countries as $country)
        {
            $country_total = 0;

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

            echo "Country.id " . $country->id . "\n";

            $count = Photo::where([
                'country_id' => $country->id,
                'verified' => 2
            ])->count();

            echo "Total photos " . $count . "\n";

            foreach ($categories as $category)
            {
                $category_id = $category . '_id';
                $category_total = 0;

                $photos = Photo::where('verified', 2)->where('country_id', $country->id)->whereNotNull($category_id)->get();

                echo "category count " . sizeof($photos). "\n";

                foreach ($photos as $photo)
                {
                    if ($photo->$category) $category_total += $photo->$category->total();
                }

                echo "Category total " . $category_total . "\n";

                $country_total += $category_total;

                echo "Country total " . $country_total . "\n";
            }

            $country->total_litter = $country_total;
            $country->save();

            $total += $country_total;

            echo "\n \n";
        }

        echo "Total " . $total . "\n";
    }
}
