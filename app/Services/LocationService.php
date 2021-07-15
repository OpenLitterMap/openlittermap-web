<?php


namespace App\Services;


use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

class LocationService
{
    /**
     * Get or Create Country from $addressArray
     *
     * @param array $addressArray
     * @return Country
     */
    public function getCountryFromAddressArray (array $addressArray)
    {
        $countryCode = $addressArray["country_code"] ?? '';

        if (!$countryCode) {
            return Country::where('country', 'error_country')->first();
        }

        $country = Country::select('id', 'country', 'shortcode')
            ->firstOrCreate([
                'shortcode' => $countryCode,
            ]);

        if ($country->wasRecentlyCreated) {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewCountryAdded($country->country, $countryCode, now()));
        }

        return $country;
    }

    /**
     * Get or Create State from $addressArray
     *
     * @param Country $country
     * @param array $addressArray
     * @return State
     */
    public function getStateFromAddressArray (Country $country, array $addressArray)
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
            // Return error state
            return State::where('state', 'error_state')->first();
        }

        $state = State::select('id', 'country_id', 'state', 'statenameb')
            ->firstOrCreate([
                'state' => $stateName,
                'country_id' => $country->id
            ]);

        if ($state->wasRecentlyCreated)
        {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewStateAdded($stateName, $country->country, now()));
        }

        return $state;
    }

    /**
     * Get or Create City from $addressArray
     *
     * @return City
     */
    public function getCityFromAddressArray (Country $country, State $state, $addressArray)
    {
        $cityName = null;

        if (array_key_exists('city', $addressArray)) {
            $cityName = $addressArray['city'];
        }
        if (!$cityName) {
            if (array_key_exists('town', $addressArray)) {
                $cityName = $addressArray['town'];
            }
        }
        if (!$cityName) {
            if (array_key_exists('city_district', $addressArray)) {
                $cityName = $addressArray['city_district'];
            }
        }
        if (!$cityName) {
            if (array_key_exists('village', $addressArray)) {
                $cityName = $addressArray['village'];
            }
        }
        if (!$cityName) {
            if (array_key_exists('hamlet', $addressArray)) {
                $cityName = $addressArray['hamlet'];
            }
        }
        if (!$cityName) {
            if (array_key_exists('locality', $addressArray)) {
                $cityName = $addressArray['locality'];
            }
        }
        if (!$cityName) {
            if (array_key_exists('county', $addressArray)) {
                $cityName = $addressArray['county'];
            }
        }
        if (!$cityName) {
            // Return error city
            return City::where('city', 'error_city')->first();
        }

        $city = City::select('id', 'country_id', 'state_id', 'city')
            ->firstOrCreate([
                'country_id' => $country->id,
                'state_id' => $state->id,
                'city' => $cityName
            ]);

        if ($city->wasRecentlyCreated) {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewCityAdded($cityName, $state->state, $country->country, now()));
        }

        return $city;
    }
}
