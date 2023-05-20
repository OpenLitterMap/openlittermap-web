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

            echo "First countryId: $firstCountryId";

            foreach ($countries as $index => $country)
            {
                if ($index === 0)
                {
                    echo "Skipping first countryId: $country->id \n";
                    // we will update the states + cities for each country in another command.
                }
                else
                {
                    echo "Duplicated countryId: " . $index . " \n";

                    // Get the states for the duplicated country
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
                        echo "First stateId for $state->state: $firstStateId \n";

                        $firstState->country_id = $firstCountryId;
                        $firstState->save();

                        // Get cities for each state
                        $cities = City::where('state_id', $state->id)->get();

                        echo "Cities found for state: " . sizeof($cities) . " \n";

                        foreach ($cities as $city)
                        {
                            // Check if there is a duplicate of the city
                            $firstCity = City::where('city', $city->city)
                                ->whereIn('state_id', $states->pluck('id')->toArray())
                                ->orderBy('id')
                                ->first();

                            $firstCityId = $firstCity->id;
                            echo "First cityId for $city->city: $firstCityId \n";

                            $firstCity->country_id = $firstCountryId;
                            $firstCity->state_id = $firstStateId;
                            $firstCity->save();

                            // Update PhotoIds for duplicate cities
                            // Then delete duplicate city
                            if ($city->id > $firstCityId)
                            {
                                // Update Photos For city
                                $cityPhotos = Photo::where('city_id', $city->id)
                                    ->select('id', 'country_id', 'state_id', 'city_id')
                                    ->get();

                                echo "Photos found for cityId $city->id " . sizeof($cityPhotos) . " \n";

                                foreach ($cityPhotos as $photo)
                                {
                                    echo "Updating photoId :$photo->id \n";
                                    $photo->country_id = $firstCountryId;
                                    $photo->state_id = $firstStateId;
                                    $photo->city_id = $firstCityId;
                                    $photo->save();
                                }

                                echo sizeof($cityPhotos) . " photos updated \n";

                                $cityPhotosCount = Photo::where('city_id', $city->id)
                                    ->select('id', 'country_id', 'state_id', 'city_id')
                                    ->count();

                                if ($cityPhotosCount === 0)
                                {
                                    $city->delete();

                                    echo "... duplicate city deleted \n\n";
                                }
                            }
                        }

                        // Delete duplicate states
                        if ($state->id > $firstStateId)
                        {
                            $statePhotos = Photo::where('state_id', $state->id)
                                ->select('id', 'country_id', 'state_id', 'city_id')
                                ->get();

                            echo "Photos found for stateId $state->id " . sizeof($statePhotos) . " \n";

                            foreach ($statePhotos as $statePhoto)
                            {
                                $statePhoto->country_id = $firstCountryId;
                                $statePhoto->state_id = $firstStateId;
                                $statePhoto->save();
                            }

                            echo sizeof($statePhotos) . " photos updated \n";

                            $statePhotosCount = Photo::where('state_id', $state->id)->count();

                            if ($statePhotosCount === 0)
                            {
                                $state->delete();

                                echo "... duplicate state deleted \n\n";
                            }
                        }
                    }

                    // Delete duplicate countries
                    if ($country->id > $firstCountryId)
                    {
                        $countryPhotos = Photo::where('country_id', $country->id)
                            ->select('id', 'country_id')
                            ->get();

                        echo "Photos found for duplicate country:" . sizeof($countryPhotos) . " \n";

                        foreach ($countryPhotos as $countryPhoto)
                        {
                            $countryPhoto->country_id = $firstCountryId;
                            $countryPhoto->save();
                        }

                        $countryPhotosCount = Photo::where('country_id', $country->id)->count();

                        if ($countryPhotosCount === 0)
                        {
                            $country->delete();

                            echo "... duplicate country deleted";
                        }
                    }
                }
            }
        }
    }
}
