<?php

namespace App\Console\Commands\Locations;

use App\Models\Photo;
use App\Models\Location\Country;

use Illuminate\Console\Command;

class UpdateCountry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'olm:update-a-country {country_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pass ID to refresh a single countries total values';

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
        $country = Country::find($this->argument('country_id'));

        $country_total = 0;

        $categories = Photo::categories();

        echo "Country: " . $country->country . "\n";

        $count = Photo::where([
            'country_id' => $country->id,
            'verified' => 2
        ])->count();

        echo "Total photos " . $count . "\n";

        foreach ($categories as $category)
        {
            $category_id = $category . '_id';
            $category_total = 0;
            $total_category = 'total_' . $category;

            $photos = Photo::where('verified', 2)->where('country_id', $country->id)->whereNotNull($category_id)->get();

            echo "category count " . sizeof($photos). "\n";

            foreach ($photos as $photo)
            {
                if ($photo->$category) $category_total += $photo->$category->total();
            }

            echo "Category total " . $category_total . "\n";

            $country->$total_category = $category_total;

            $country_total += $category_total;

            echo "Country total " . $country_total . "\n";
        }

        $country->total_litter = $country_total;
        $country->save();
    }
}
