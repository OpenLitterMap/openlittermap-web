<?php

namespace Tests\Support;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

class TestLocationService
{
    public function createOrGetLocationFromAddress(array $address): array
    {
        $country = Country::firstOrCreate(
            ['shortcode' => $address['country_code']],
            ['country' => $address['country']]
        );

        $state = State::firstOrCreate(
            ['state' => $address['state'], 'country_id' => $country->id],
            ['state' => $address['state']]
        );

        $city = City::firstOrCreate(
            [
                'city' => $address['city'],
                'state_id' => $state->id,
                'country_id' => $country->id,
            ],
            ['city' => $address['city']]
        );

        return [
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
        ];
    }
}
