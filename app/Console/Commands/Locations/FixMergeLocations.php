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

        echo "\nFound " . count($duplicatedCountries) . " duplicates \n";

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
                         $this->processPhotosForCountry($country->id, $firstCountryId);
                    }

                    // Delete duplicate countries
                    if ($country->id > $firstCountryId)
                    {
                        $countryPhotos = Photo::where('country_id', $country->id)->count();
                        $countryStates = State::where('country_id', $country->id)->count();
                        $countryCities = City::where('country_id', $country->id)->count();

                        if ($countryPhotos === 0 && $countryStates === 0 && $countryCities === 0)
                        {
                            $country->delete();
                            echo "duplicate country deleted \n";
                        }
                    }

                    echo "... finished processing duplicated country \n";
                }
            }
        }
    }

    /**
     * For each countryId,
     *
     * Look at each States -> Cities
     */
    public function processStatesForCountry (int $countryId, array $countryIds, int $firstCountryId)
    {
        echo "\n...processing states for country \n";

        $statesForCountry = State::where('country_id', $countryId)->get();

        foreach ($statesForCountry as $state)
        {
            echo "\nState: $state->state id: $state->id \n";

            // Look for Duplicate States
            $duplicateStatesByName = State::where('state', $state->state)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo count($duplicateStatesByName)  . " states found with the same name \n";

            $firstStateId = $duplicateStatesByName[0]->id;
            echo "First stateId: $firstStateId \n";

            $firstState = State::find($firstStateId);
            if ($firstState->country_id !== $firstCountryId)
            {
                $oldCountryId = $firstState->country_id;
                $firstState->country_id = $firstCountryId;
                $firstState->save();

                echo "country_id ($firstCountryId) updated for state ($firstState->state : $firstState->id), oldCountryId ($oldCountryId) \n";
            }

            // All states incl original and duplicate
            foreach ($duplicateStatesByName as $duplicateStateIndex => $duplicateState)
            {
                echo "duplicate state $duplicateState->state ($duplicateState->id) #$duplicateStateIndex \n";

                // Get cities for each state
                $citiesForStateCount = City::where('state_id', $duplicateState->id)->count();
                echo $citiesForStateCount . " cities found \n";

                if ($citiesForStateCount > 0)
                {
                    $this->processCitiesForState($duplicateState->id, $countryIds, $firstCountryId, $firstStateId);
                }

                // Get photos for each state
                $photosForState = Photo::where('state_id', $state->id)->get();
                echo count($photosForState) . " photos found for state \n";

                if (count($photosForState) > 0)
                {
                     foreach ($photosForState as $photoForState)
                     {
                         if ($photoForState->country_id !== $firstCountryId)
                         {
                             $oldCountryId = $photoForState->country_id;
                             $photoForState->country_id = $firstCountryId;
                             $photoForState->save();

                             echo "country_id ($firstCountryId) updated for photo ($photoForState->id) in state ($firstStateId), oldCountryId ($oldCountryId) \n";
                         }

                         if ($photoForState->state_id !== $firstStateId)
                         {
                             $oldStateId = $photoForState->state_id;
                             $photoForState->state_id = $firstStateId;
                             $photoForState->save();

                             echo "state_id ($firstStateId) updated for photo ($photoForState->id) in state ($firstStateId), oldStateId ($oldStateId)  \n";
                         }
                     }
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

        echo "... finished processing states for country \n\n";
    }

    /**
     * For each State, process Cities
     */
    public function processCitiesForState (int $stateId, array $countryIds, int $firstCountryId, $firstStateId)
    {
        echo "\n...processing cities for state \n";

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
            echo count($citiesByName) . " cities found with the same name as $cityForState->city \n";

            if (count($citiesByName) > 0)
            {
                 $firstCityId = $citiesByName[0]->id;
                 echo "First cityId for $cityForState->city: $firstCityId \n";

                 $firstCity = City::find($firstCityId);

                 if ($firstCity->country_id !== $firstCountryId)
                 {
                     $oldCountryId = $firstCity->country_id;
                     $firstCity->country_id = $firstCountryId;
                     $firstCity->save();

                     echo "country_id ($firstCountryId) updated for city ($firstCity->id) in state, oldCountryId ($oldCountryId) \n";
                 }

                 if ($firstCity->state_id !== $firstStateId)
                 {
                     $oldStateId = $firstCity->state_id;
                     $firstCity->state_id = $firstStateId;
                     $firstCity->save();

                     echo "state_id ($firstStateId) updated for city ($firstCity->id) in state, oldStateId ($oldStateId) \n";
                 }

                foreach ($citiesByName as $cityNameIndex => $cityByName)
                {
                    echo "duplicate city $cityByName->city ($cityByName->id) #$cityNameIndex \n";

                    $photosForCity = Photo::where('city_id', $cityByName->id)
                        ->select('id', 'country_id', 'state_id', 'city_id')
                        ->get();
                    echo count($photosForCity) . " photos for city $cityByName->city ($cityByName->id) \n";

                    foreach ($photosForCity as $photo)
                    {
                        if ($photo->country_id !== $firstCountryId)
                        {
                            $oldCountryId = $photo->country_id;
                            $photo->country_id = $firstCountryId;
                            $photo->save();

                            echo "country_id ($firstCountryId) updated for photo ($photo->id) in city ($firstCityId), oldCountryId ($oldCountryId) \n";
                        }

                        if ($photo->state_id !== $firstStateId)
                        {
                            $oldStateId = $photo->state_id;
                            $photo->state_id = $firstStateId;
                            $photo->save();

                            echo "state_id ($firstStateId) updated for photo ($photo->id) in city ($firstCityId), oldStateId ($oldStateId) \n";
                        }

                        if ($photo->city_id !== $firstCityId)
                        {
                            $oldCityId = $photo->city_id;
                            $photo->city_id = $firstCityId;
                            $photo->save();

                            echo "city_id ($firstCityId) updated for photo ($photo->id) in city, old cityId ($oldCityId) \n";
                        }
                    }
                }
            }
        }

        echo "... finished processing cities for state \n\n";
    }

    /**
     * Using a countryId, after looping over States, and their cities, and their photos
     *
     * We can now check each City for the duplicated countryId
     */
    public function processCitiesForCountry (int $countryId, array $countryIds, int $firstCountryId)
    {
        echo "\n...processing cities for country \n";

        $citiesForCountry = City::where('country_id', $countryId)->get();

        // Look for duplicate cities
        foreach ($citiesForCountry as $cityForCountry)
        {
            // Find all cities by name
            $citiesByName = City::where('city', $cityForCountry->city)
                ->whereIn('country_id', $countryIds)
                ->orderBy('id')
                ->get();
            echo count($citiesByName) . " cities found with the same name \n\n";

            $firstCityId = $citiesByName[0]->id;
            echo "First cityId for $cityForCountry->city: $firstCityId \n";

            $firstCity = City::find($firstCityId);
            if ($firstCity->country_id !== $firstCountryId)
            {
                $oldCountryId = $firstCity->country_id;
                $firstCity->country_id = $firstCountryId;
                $firstCity->save();

                echo "country_id ($firstCountryId) updated for city ($firstCityId) in country ($firstCountryId), old countryId ($oldCountryId) \n";
            }

            foreach ($citiesByName as $cityByNameIndex => $cityByName)
            {
                echo "duplicate city #$cityByNameIndex \n";

                $photosForCity = Photo::where('city_id', $cityByName->id)
                    ->select('id', 'country_id', 'state_id', 'city_id')
                    ->get();
                echo count($photosForCity) . " photos for city \n\n";

                if (count($photosForCity) > 0)
                {
                      foreach ($photosForCity as $photo)
                      {
                          if ($photo->country_id !== $firstCountryId)
                          {
                              $oldCountryId = $photo->country_id;
                              $photo->country_id = $firstCountryId;
                              $photo->save();

                              echo "country_id ($firstCountryId) updated for photo ($photo->id) in city ($firstCityId) in country ($firstCountryId), old countryId ($oldCountryId) \n";
                          }

                          if ($photo->city_id !== $firstCityId)
                          {
                              $oldCityId = $photo->city_id;
                              $photo->city_id = $firstCityId;
                              $photo->save();

                              echo "city_id ($firstCityId) updated for photo ($photo->id) in city ($firstCityId) in country ($firstCountryId), old cityId ($oldCityId) \n";
                          }
                      }
                }
            }
        }

        echo "... finished processing cities for country \n\n";
    }

    public function processPhotosForCountry (int $countryId, int $firstCountryId)
    {
        echo "\n...processing photos for country \n";

        $photosForCountryCount = Photo::where('country_id', $countryId)->count();

        if ($photosForCountryCount > 0)
        {
            $photosForCountry = Photo::where('country_id', $countryId)->get();

            foreach ($photosForCountry as $photo)
            {
                if ($photo->country_id !== $firstCountryId)
                {
                    $photo->country_id = $firstCountryId;
                    $photo->save();

                    echo "photo #$photo->id for country has been updated for country ($firstCountryId), old countryId ($countryId) \n";
                }
            }
        }

        echo "... finished processing photos for country \n\n";
    }
}
