<?php

namespace App\Helpers\Post;

use App\Events\NewCityAdded;
use App\Events\NewCountryAdded;
use App\Events\NewStateAdded;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

class UploadHelper
{
    /**
     * Get or Create Country from $addressArray
     */
    public function getCountryFromAddressArray (array $addressArray)
    {
        $countryCode = $this->lookupPlace($addressArray, ['country_code']);

        if (!$countryCode) {
            return Country::where('country', 'error_country')->first();
        }

        $country = Country::select('id', 'country', 'shortcode')
            ->firstOrCreate(
                ['shortcode' => $countryCode],
                ['country' => $addressArray["country"] ?? '', 'created_by' => auth()->id()]
            );

        if ($country->wasRecentlyCreated) {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewCountryAdded($country->country, $countryCode, now()));
        }

        return $country;
    }

    /**
     * Get or Create State from $addressArray
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

        $state = State::select('id', 'country_id', 'state', 'statenameb')
            ->firstOrCreate(
                ['state' => $stateName, 'country_id' => $country->id],
                ['created_by' => auth()->id()]
            );

        if ($state->wasRecentlyCreated)
        {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewStateAdded($stateName, $country->country, now()));
        }

        return $state;
    }

    /**
     * Get or Create City from $addressArray
     */
    public function getCityFromAddressArray (Country $country, State $state, $addressArray, $lat, $lon)
    {
        $cityName = $this->lookupPlace(
            $addressArray,
            ['city', 'town', 'city_district', 'village', 'hamlet', 'locality', 'county']
        );

        if (!$cityName) {
            // Return error city
            return City::where('city', 'error_city')->first();
        }

        $city = City::select('id', 'country_id', 'state_id', 'city')
            ->firstOrCreate(
                [
                    'country_id' => $country->id,
                    'state_id' => $state->id,
                    'city' => $cityName
                ],
                ['created_by' => auth()->id()]
            );

        if ($city->wasRecentlyCreated)
        {
            // Broadcast an event to anyone viewing the Global Map
            event(new NewCityAdded(
                $cityName,
                $state->state,
                $country->country,
                now(),
                $city->id,
                $lat,
                $lon
            ));
        }

        return $city;
    }

    /**
     * @param $addressArray
     * @param $keys
     */
    protected function lookupPlace ($addressArray, $keys): ?string
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
