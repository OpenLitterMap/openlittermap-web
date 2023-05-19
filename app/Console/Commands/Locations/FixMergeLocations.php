<?php

namespace App\Console\Commands\Locations;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMergeLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:fix-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge locations together and delete the old ones';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $duplicatedCountries = DB::table('countries')
            ->select('shortcode', DB::raw('COUNT(*) as `count`'))
            ->groupBy('shortcode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        echo "Found " . sizeof($duplicatedCountries) . " duplicates \n";

        foreach ($duplicatedCountries as $duplicatedCountry)
        {
            echo "Shortcode: " . $duplicatedCountry->shortcode . " found " . $duplicatedCountry->count . " times \n";

            // Get All Countries, First + Duplicates.
            $countries = Country::where('shortcode', $duplicatedCountry->shortcode)
                ->select('id', 'country', 'shortcode')
                ->orderBy('id')
                ->get();

            $firstCountryId = $countries[0]->id;

            foreach ($countries as $index => $country)
            {
                echo "Country id: " . $index . " \n";

                if ($index === 0)
                {
                    echo "Skipping first country id = $firstCountryId \n";
                }
                else
                {
                    // Get the states for the duplicated countries
                    $states = State::where('country_id', $country->id)->get();

                    echo sizeof($states) . " states found \n";

                    foreach ($states as $state)
                    {
                        // Check for duplicate states to see which one is earliest
                        $firstState = State::where('state', $state->state)
                            ->whereIn('country_id', $countries->pluck('id')->toArray())
                            ->orderBy('id')
                            ->first();

                        $firstStateId = $firstState->id;
                        $firstState->country_id = $firstCountryId;
                        $firstState->save();

                        // Get cities for each state
                        $cities = City::where('state_id', $state->id)->get();

                        foreach ($cities as $city)
                        {
                            // Check if there is a duplicate of the city
                            $firstCity = City::where('city', $city->city)
                                ->whereIn('state_id', $states->pluck('id')->toArray())
                                ->orderBy('id')
                                ->first();

                            $firstCityId = $firstCity->id;
                            $firstCity->country_id = $firstCountryId;
                            $firstCity->state_id = $firstStateId;
                            $firstCity->save();

                            $photos = Photo::where('city_id', $city->id)->get();

                            foreach ($photos as $photo)
                            {
                                echo "Photo id $photo->id \n";
                                $photo->country_id = $firstCountryId;
                                $photo->state_id = $firstStateId;
                                $photo->city_id = $firstCityId;
                                $photo->save();
                            }

                            echo sizeof($photos) . " photos updated \n";

                            // Delete duplicate cities
                            if ($city->id > $firstCityId)
                            {
                                $city->delete();
                            }
                        }

                        // Delete duplicate states
                        if ($state->id > $firstStateId)
                        {
                            $state->delete();
                        }
                    }

                    // Delete duplicate countries
                    $country->delete();
                }
            }
        }
    }
}
