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
    public function handle ()
    {
        $duplicatedCountries = DB::table('countries')
            ->select('shortcode', DB::raw('COUNT(*) as `count`'))
            ->groupBy('shortcode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        echo "\nFound " . sizeof($duplicatedCountries) . " duplicates \n";

        foreach ($duplicatedCountries as $duplicatedCountry)
        {
            echo "\n\n*** Shortcode: " . $duplicatedCountry->shortcode . " found " . $duplicatedCountry->count . " times \n";

            // Get All Countries for a shortcode: Original + Duplicates.
            $countries = Country::where('shortcode', $duplicatedCountry->shortcode)
                ->select('id', 'country', 'shortcode')
                ->orderBy('id')
                ->get();

            $countryIds = $countries->pluck('id')->toArray();

            // Get the ID of the first Country that was uploaded.
            $firstCountryId = $countryIds[0];
            echo "First countryId: $firstCountryId \n";

            foreach ($countries as $index => $country)
            {
                if ($index > 0)
                {
                    echo "\n---Duplicated countryId: " . $country->id . " \n";

                    // Get the states for the duplicated country
                    $statesForCountry = State::where('country_id', $country->id)->count();
                    echo sizeof($statesForCountry) . " states found \n"; // 3 states found

                    if (sizeof($statesForCountry) > 0)
                    {
                        $this->processStatesForCountry($country->id, $countryIds);
                    }

                    // Check Cities for Country
                    // Check Photos for Country

                    // Delete duplicate countries
                    if ($country->id > $firstCountryId)
                    {
                        $countryPhotos = Photo::where('country_id', $country->id)
                            ->select('id', 'country_id')
                            ->get();

                        echo "Photos found for duplicate country:" . sizeof($countryPhotos) . " \n";

                        // foreach ($countryPhotos as $countryPhoto)
                        // {
                        //     // echo "Updating photoId : $countryPhoto->id \n";
                        //
                        //     $countryPhoto->country_id = $firstCountryId;
                        //     $countryPhoto->save();
                        // }
                    }
                }
            }
        }
    }

    /**
     * For each countryId,
     *
     * Look at each States -> Cities
     *
     * @param int   $countryId
     * @param array $countryIds
     */
    public function processStatesForCountry (int $countryId, array $countryIds)
    {
        $statesForCountry = State::where('country_id', $countryId)->get();

        foreach ($statesForCountry as $state)
        {
            echo "State: $state->state \n";

            // Look for Duplicate States
            $duplicateStatesByName = State::where('state', $state->state)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo sizeof($duplicateStatesByName)  . " states by name \n";

            $firstStateId = $duplicateStatesByName[0]->id;
            echo "First stateId: $firstStateId \n";

            // $firstState = State::find($firstStateId);
            // $firstState->country_id = $firstCountryId;
            // $firstState->save();

            // All states incl original and duplicate
            foreach ($duplicateStatesByName as $duplicateState)
            {
                // Get cities for each state
                $citiesForState = City::where('state_id', $duplicateState->id)->count();
                echo sizeof($citiesForState) . " cities found for state \n";

                if (sizeof($citiesForState) > 0)
                {
                    $this->processCitiesForState($duplicateState->id, $countryIds);
                }

                // Get photos for each state
                $photosForState = Photo::where('state_id', $state->id)->get();
                echo "Photos found for state: " . sizeof($photosForState) . " \n";

                // foreach ($photosForState as $photoForState)
                // {
                //     $photoForState->country_id = $firstCountryId;
                //     $photoForState->state_id = $firstStateId;
                //     $photoForState->save();
                // }
            }


            // Delete duplicate states
            if ($state->id > $firstStateId)
            {
                $statePhotos = Photo::where('state_id', $state->id)
                    ->select('id', 'country_id', 'state_id', 'city_id')
                    ->get();

                echo "\nPhotos found for stateId $state->id " . sizeof($statePhotos) . " \n\n";

                // foreach ($statePhotos as $statePhoto)
                // {
                //     echo "Updating photoId : $statePhoto->id \n";
                //
                //     $statePhoto->country_id = $firstCountryId;
                //     $statePhoto->state_id = $firstStateId;
                //     $statePhoto->save();
                // }

                echo sizeof($statePhotos) . " photos updated \n";
            }
        }
    }

    /**
     * For each State, process Cities
     *
     * @param $stateId
     * @param $countryIds
     */
    public function processCitiesForState ($stateId, $countryIds)
    {
        $citiesForState = City::where('state_id', $stateId)->get();

        // Look for duplicate cities
        foreach ($citiesForState as $cityForState)
        {
            // Find all cities by name
            $citiesByName = City::where('city', $cityForState->city)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo sizeof($citiesByName) . " cities found with the same name \n";

            if (sizeof($citiesByName) > 0)
            {
                $firstCityId = $citiesByName[0]->id;
                echo "First cityId for $cityForState->city: $firstCityId \n";

                // Check if there is a duplicate of the city
                $firstCity = City::find($firstCityId);
                // $firstCity->country_id = $firstCountryId;
                // $firstCity->state_id = $firstStateId;
                // $firstCity->save();

                foreach ($citiesByName as $cityByName)
                {
                    $photosForCity = Photo::where('city_id', $cityByName->id)
                        ->select('id', 'country_id', 'state_id', 'city_id')
                        ->get();
                    echo sizeof($photosForCity) . " photos for city \n";

                    //  foreach ($photosForCity as $photo)
                    //  {
                    //      $photo->country_id = $firstCountryId;
                    //      $photo->state_id = $firstStateId;
                    //      $photo->city_id = $firstCityId;
                    //      $photo->save();
                    //  }

                    if ($cityByName->id > $firstCityId)
                    {
                        $cityPhotosCount = Photo::where('city_id', $cityByName->id)
                            ->select('id', 'country_id', 'state_id', 'city_id')
                            ->count();

                        if ($cityPhotosCount === 0)
                        {
                            // $city->delete();

                            echo "... duplicate city can be deleted \n\n";
                        }
                    }
                }
            }
        }
    }
}
