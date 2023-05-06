<?php

namespace App\Helpers\Post;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

class UploadHelper
{
    /**
     * Get or Create Country from $addressArray
     *
     * @param array $addressArray
     */
    public function getCountryFromAddressArray (array $addressArray)
    {
        $countryCode = $this->lookupPlace($addressArray, ['country_code']);

        if (!$countryCode) {
            return Country::where('country', 'error_country')->first();
        }

        return Country::select('id', 'country', 'shortcode')
            ->firstOrCreate(
                ['shortcode' => $countryCode],
                ['country' => $addressArray["country"] ?? '', 'created_by' => auth()->id()]
            );
    }

    /**
     * Get or Create State from $addressArray
     *
     * @param Country $country
     * @param array $addressArray
     */
    public function getStateFromAddressArray (Country $country, array $addressArray)
    {
        $stateName = $this->lookupPlace(
            $addressArray,
            ['state', 'county', 'region', 'state_district']
        );

        if (!$stateName) {
            // Return error state
            return State::where('state', 'error_state')->first();
        }

        return State::select('id', 'country_id', 'state', 'statenameb')
            ->firstOrCreate(
                ['state' => $stateName, 'country_id' => $country->id],
                ['created_by' => auth()->id()]
            );
    }

    /**
     * Get or Create City from $addressArray
     */
    public function getCityFromAddressArray (Country $country, State $state, $addressArray)
    {
        // This list and part of the app could use some work.
        $cityName = $this->lookupPlace(
            $addressArray,
            ['city', 'town', 'city_district', 'village', 'hamlet', 'locality', 'county']
        );

        if (!$cityName) {
            // Return error city
            return City::where('city', 'error_city')->first();
        }

        return City::select('id', 'country_id', 'state_id', 'city')
            ->firstOrCreate(
                [
                    'country_id' => $country->id,
                    'state_id' => $state->id,
                    'city' => $cityName
                ],
                ['created_by' => auth()->id()]
            );
    }

    /**
     * @param $addressArray
     * @param $keys
     * @return string|null
     */
    protected function lookupPlace($addressArray, $keys): ?string
    {
        foreach ($keys as $key) {
            $place = $addressArray[$key] ?? null;

            if ($place) {
                return $place;
            }
        }

        return null;
    }
}
