<?php

namespace App\Console\Commands\Locations;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;

class FixDeleteLocations extends Command
{
    /**
     * Do this after the photo location_ids have been fixed (FixMergeLocations.php)
     *
     * @var string
     */
    protected $signature = 'locations:fix-and-merge-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete any locations that are not used';

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

        foreach ($cities as $city)
        {
            $photoExists = Photo::where('city_id', $city->id)->first();

            if (!$photoExists)
            {
                echo "No photo found for city:id $city->id : $city->city \n";

                $city->delete();
            }
        }

        $states = State::all();

        foreach ($states as $state)
        {
            $photoExists = Photo::where('state_id', $state->id)->first();

            if (!$photoExists)
            {
                echo "No photo found for state:id $state->id : $state->state \n";

                $cityExists = City::where('state_id', $state->id)->first();

                if (!$cityExists)
                {
                    $state->delete();
                }
            }
        }

        $countries = Country::all();

        foreach ($countries as $country)
        {
            $photoExists = Photo::where('country_id', $country->id)->first();

            if (!$photoExists)
            {
                echo "No photo found for country: $country->id $country->$country ($country->shortcode) \n";

                $cityExists = City::where('country_id', $country->id)->first();
                $stateExists = State::where('country_id', $country->id)->first();

                if (!$cityExists && !$stateExists)
                {
                    $country->delete();
                }
            }
        }
    }
}
