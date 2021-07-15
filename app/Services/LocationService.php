<?php


namespace App\Services;


use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Exception;
use Illuminate\Support\Facades\Log;

class LocationService
{
    /**
     * From an array of data, return $countryId
     *
     * @param array $addressArray
     *
     * @return int $countryId
     */
    public function getCountryFromAddressArray(array $addressArray)
    {
        $countryCode = (array_key_exists('country_code', $addressArray))
            ? $addressArray["country_code"]
            : 'error';

        if ($countryCode !== 'error') {
            $country = Country::select('id', 'country')
                ->where('shortcode', $countryCode)
                ->firstOrCreate();

            if ($country->wasRecentlyCreated) {
                // Broadcast an event to anyone viewing the Global Map
                event(new NewCountryAdded($country->country, $countryCode, now()));
            }

            return $country;
        }

        // If you don't have this locally, create it
        return Country::where('country', 'error_country')->first();
    }

    /**
     * Return State.id from $addressArray
     *
     * @param array $addressArray
     * @param $country
     */
    public function getStateFromAddressArray(Country $country, array $addressArray)
    {
        $stateName = null;

        // Extract state name to get state.id
        if (array_key_exists('state', $addressArray)) {
            $stateName = $addressArray["state"];
        }
        if (!$stateName) {
            if (array_key_exists('county', $addressArray)) {
                $stateName = $addressArray["county"];
            }
        }
        if (!$stateName) {
            if (array_key_exists('region', $addressArray)) {
                $stateName = $addressArray["region"];
            }
        }
        if (!$stateName) {
            $stateName = 'error';
        }

        if ($stateName !== 'error') {
            try {
                $state = State::select('id', 'country_id', 'state', 'statenameb')
                    ->where([
                        'state' => $stateName,
                        'country_id' => $country->id
                    ])
                    ->firstOrCreate();

                if ($state->wasRecentlyCreated) {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewStateAdded($stateName, $country->country, now()));
                }

                return $state;
            } catch (Exception $e) {
                Log::info(['LocationService.checkState', $e->getMessage()]);
            }
        }

        // Return error state
        return State::where('state', 'error_state')->first();
    }

    /**
     * Return a city from Country, State, addressArrray
     */
    public function getCityFromAddressArray (Country $country, State $state, $addressArray)
    {
        $cityName = null;

        if (array_key_exists('city', $addressArray))
        {
            $cityName = $addressArray['city'];
        }
        if (!$cityName)
        {
            if (array_key_exists('town', $addressArray))
            {
                $cityName = $addressArray['town'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('city_district', $addressArray))
            {
                $cityName = $addressArray['city_district'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('village', $addressArray))
            {
                $cityName = $addressArray['village'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('hamlet', $addressArray))
            {
                $cityName = $addressArray['hamlet'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('locality', $addressArray))
            {
                $cityName = $addressArray['locality'];
            }
        }
        if (!$cityName)
        {
            if (array_key_exists('county', $addressArray))
            {
                $cityName = $addressArray['county'];
            }
        }
        if (!$cityName)
        {
            $cityName = 'error';
        }

        if ($cityName !== 'error')
        {
            try
            {
                $city = City::select('id', 'country_id', 'state_id', 'city')
                    ->where([
                        'country_id' => $country->id,
                        'state_id' => $state->id,
                        'city' => $cityName
                    ])
                    ->firstOrCreate();

                if ($city->wasRecentlyCreated)
                {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewCityAdded($cityName, $this->state, $this->country, now()));
                }
            }
            catch (Exception $e)
            {
                Log::info(['LocationService@createCity', $e->getMessage()]);
            }
        }

        // Return error city
        return City::where('city', 'error_city')->first();
    }
}
