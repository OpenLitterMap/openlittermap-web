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

            // Get Original + Duplicates for each Country
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
                    echo "\n\n---Duplicated countryId: " . $country->id . " \n";

                    // Check if the duplicated country has states
                    $statesForCountryCount = State::where('country_id', $country->id)->count();
                    echo $statesForCountryCount . " states found for country \n";

                    if ($statesForCountryCount > 0)
                    {
                        // This will also process cities, their photos, and the photos for each state
                        $this->processStatesForCountry($country->id, $countryIds, $firstCountryId);
                    }

                    // Check Cities for Country
                    $citiesForCountryCount = City::where('country_id', $country->id)->count();
                    echo $citiesForCountryCount . " cities found for country \n";

                    if ($citiesForCountryCount > 0)
                    {
                        // This will also process the photos for each city
                        $this->processCitiesForCountry($country->id, $countryIds, $firstCountryId);
                    }

                    // Check Photos for Country
                    $photosForCountryCount = Photo::where('country_id', $country->id)->count();
                    echo $photosForCountryCount . " photos for country \n";

                    if ($photosForCountryCount > 0)
                    {
                         $this->processPhotosForCountry($country->id);
                    }

                    // Delete duplicate countries
                    if ($country->id > $firstCountryId)
                    {
                        $countryPhotos = Photo::where('country_id', $country->id)->count();
                        $countryStates = State::where('country_id', $country->id)->count();
                        $countryCities = City::where('country_id', $country->id)->count();

                        if ($countryPhotos === 0 && $countryStates === 0 && $countryCities === 0)
                        {
                            // $country->delete();
                            echo "duplicate country can be deleted \n";
                        }
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
     * @param int   $firstCountryId
     */
    public function processStatesForCountry (int $countryId, array $countryIds, int $firstCountryId)
    {
        $statesForCountry = State::where('country_id', $countryId)->get();

        foreach ($statesForCountry as $state)
        {
            echo "State: $state->state id: $state->id \n";

            // Look for Duplicate States
            $duplicateStatesByName = State::where('state', $state->state)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo sizeof($duplicateStatesByName)  . " states found with the same name \n";

            $firstStateId = $duplicateStatesByName[0]->id;
            // echo "First stateId: $firstStateId \n";

            // $firstState = State::find($firstStateId);
            // $firstState->country_id = $firstCountryId;
            // $firstState->save();

            // All states incl original and duplicate
            foreach ($duplicateStatesByName as $duplicateStateIndex => $duplicateState)
            {
                echo "duplicate state #$duplicateStateIndex \n";

                // Get cities for each state
                $citiesForStateCount = City::where('state_id', $duplicateState->id)->count();
                echo $citiesForStateCount . " cities found \n";

                if ($citiesForStateCount > 0)
                {
                    $this->processCitiesForState($duplicateState->id, $countryIds, $firstCountryId, $firstStateId);
                }

                // Get photos for each state
                $photosForState = Photo::where('state_id', $state->id)->get();
                echo sizeof($photosForState) . " photos found for state \n";

                if (sizeof($photosForState) > 0)
                {
//                     foreach ($photosForState as $photoForState)
//                     {
//                         $photoForState->country_id = $firstCountryId;
//                         $photoForState->state_id = $firstStateId;
//                         $photoForState->save();
//                         echo "photo $photoForState->id for state can be updated \n";
//                     }
                }
            }

            // Delete duplicate states
            if ($state->id > $firstStateId)
            {
                $statePhotos = Photo::where('state_id', $state->id)->count();
                $citiesForState = City::where('state_id', $state->id)->count();

                if ($statePhotos === 0 && $citiesForState === 0)
                {
                    // $state->delete();

                    echo "duplicate state can be deleted \n\n";
                }
            }
        }
    }

    /**
     * For each State, process Cities
     *
     * @param int   $stateId
     * @param array $countryIds
     * @param int   $firstCountryId
     */
    public function processCitiesForState (int $stateId, array $countryIds, int $firstCountryId, $firstStateId)
    {
        $citiesForState = City::where('state_id', $stateId)->get();

        // Look for duplicate cities
        foreach ($citiesForState as $cityForState)
        {
            echo "City: $cityForState->city id: $cityForState->id \n";

            // Find all cities by name
            $citiesByName = City::where('city', $cityForState->city)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo sizeof($citiesByName) . " cities found with the same name as $cityForState->city \n";

            if (sizeof($citiesByName) > 0)
            {
                $firstCityId = $citiesByName[0]->id;
                // echo "First cityId for $cityForState->city: $firstCityId \n";

                // $firstCity = City::find($firstCityId);
                // $firstCity->country_id = $firstCountryId;
                // $firstCity->state_id = $firstStateId;
                // $firstCity->save();

                foreach ($citiesByName as $cityNameIndex => $cityByName)
                {
                    echo "duplicate city #$cityNameIndex \n";

                    $photosForCity = Photo::where('city_id', $cityByName->id)
                        ->select('id', 'country_id', 'state_id', 'city_id')
                        ->get();
                    echo sizeof($photosForCity) . " photos for city $cityByName->id \n";

                    //  foreach ($photosForCity as $photo)
                    //  {
                    //      $photo->country_id = $firstCountryId;
                    //      $photo->state_id = $firstStateId;
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

    /**
     * Using a countryId, after looping over States, and their cities, and their photos
     *
     * We can now check each City for the duplicated countryId
     *
     * @param int $countryId
     * @param array $countryIds
     */
    public function processCitiesForCountry (int $countryId, array $countryIds, int $firstCountryId)
    {
        $citiesForCountry = City::where('country_id', $countryId)->get();

        // Look for duplicate cities
        foreach ($citiesForCountry as $cityForCountry)
        {
            // Find all cities by name
            $citiesByName = City::where('city', $cityForCountry->city)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo sizeof($citiesByName) . " cities found with the same name \n\n";

            $firstCityId = $citiesByName[0]->id;
            echo "First cityId for $cityForCountry->city: $firstCityId \n";

            // $firstCity = City::find($firstCityId);
            // $firstCity->country_id = $firstCountryI;
            // $firstCity->save();

            foreach ($citiesByName as $cityByNameIndex => $cityByName)
            {
                echo "duplicate city #$cityByNameIndex \n";

                $photosForCity = Photo::where('city_id', $cityByName->id)
                    ->select('id', 'country_id', 'state_id', 'city_id')
                    ->get();
                echo sizeof($photosForCity) . " photos for city \n\n";

                if (sizeof($photosForCity) > 0)
                {
                      foreach ($photosForCity as $photo)
                      {
//                          $photo->country_id = $firstCountryId;
//                          $photo->city_id = $firstCityId;
//                          $photo->save();
//                          echo "photo #$photo->id for country.city can be updated \n";
                      }
                }

                if ($cityByName->id > $firstCityId)
                {
                    $photosForCity = Photo::where('city_id', $cityByName->id)->count();
                    $statesForCity = State::where('id', $cityByName->state_id)->count();

                    if ($photosForCity === 0 && $statesForCity === 0)
                    {
                        // $city->delete();

                        echo "... duplicate city can be deleted \n\n";
                    }
                }
            }
        }
    }

    public function processPhotosForCountry (int $countryId)
    {
        $photosForCountryCount = Photo::where('country_id', $countryId)->count();

        if ($photosForCountryCount > 0)
        {
            $photosForCountry = Photo::where('country_id', $countryId)->get();

            foreach ($photosForCountry as $photo)
            {
//                $photoForCountry->country_id = $countryId;
//                $photoForCountry->save();
//                echo "photo #$photo->id for country can be updated \n";
            }
        }
    }
}
