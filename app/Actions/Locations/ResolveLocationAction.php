<?php

namespace App\Actions\Locations;

use App\Exceptions\GeocodingException;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use GuzzleHttp\Exception\GuzzleException;

class ResolveLocationAction
{
    /**
     * Reverse geocode lat/lon and resolve to Country, State, City.
     *
     * Gracefully handles incomplete geocoding data — state and city may
     * be null if the API response doesn't include them. The photo is
     * still uploaded; location can be fixed later.
     *
     * @throws GeocodingException only if country_code is missing (minimum requirement)
     * @throws GuzzleException
     */
    public function run(float $lat, float $lon): LocationResult
    {
        $revGeoCode = app(ReverseGeocodeLocationAction::class)->run($lat, $lon);

        $address = $revGeoCode['address'];

        $country = $this->resolveCountry($address);
        $state = $this->resolveState($country, $address);
        $city = $state ? $this->resolveCity($country, $state, $address) : null;

        return new LocationResult(
            country: $country,
            state: $state,
            city: $city,
            addressArray: $address,
            displayName: $revGeoCode['display_name'],
        );
    }

    private function resolveCountry(array $address): Country
    {
        $code = $address['country_code'] ?? null;

        if (!$code) {
            throw new GeocodingException('No country_code in geocode response');
        }

        return Country::firstOrCreate(
            ['shortcode' => strtoupper($code)],
            ['country' => $address['country'] ?? '', 'created_by' => auth()->id()]
        );
    }

    private function resolveState(Country $country, array $address): ?State
    {
        $name = $this->lookup($address, ['state', 'county', 'region', 'state_district']);

        if (!$name) {
            return null;
        }

        return State::firstOrCreate(
            ['state' => $name, 'country_id' => $country->id],
            ['created_by' => auth()->id()]
        );
    }

    private function resolveCity(Country $country, State $state, array $address): ?City
    {
        $name = $this->lookup($address, [
            'city', 'town', 'city_district', 'village', 'hamlet', 'locality', 'county'
        ]);

        if (!$name) {
            return null;
        }

        return City::firstOrCreate(
            ['country_id' => $country->id, 'state_id' => $state->id, 'city' => $name],
            ['created_by' => auth()->id()]
        );
    }

    private function lookup(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($address[$key])) {
                return $address[$key];
            }
        }

        return null;
    }
}
