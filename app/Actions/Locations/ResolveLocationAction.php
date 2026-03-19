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
     * OSM admin-level keys in priority order for state-level resolution.
     * Covers Nominatim/LocationIQ address fields across all countries.
     * @see https://wiki.openstreetmap.org/wiki/Key:admin_level
     */
    public const STATE_KEYS = [
        'state',           // admin_level ~4 (US states, DE Bundesländer, etc.)
        'region',          // admin_level ~4-6 (FR régions, IT regioni)
        'province',        // admin_level ~4-6 (ES provincias, PH provinces)
        'state_district',  // admin_level ~5-6 (IN districts, sub-state)
        'county',          // admin_level ~6 (US counties, UK counties)
        'district',        // admin_level ~6-8 (RO judete, TR ilçe, IN districts)
        'municipality',    // admin_level ~7-8 (SE kommun, NO kommune)
        'borough',         // admin_level ~8-9 (NYC boroughs, London boroughs)
    ];

    /**
     * OSM keys in priority order for city-level resolution.
     */
    public const CITY_KEYS = [
        'city',            // admin_level ~8 (primary city name)
        'town',            // smaller than city
        'city_district',   // sub-city district
        'village',         // rural settlement
        'hamlet',          // tiny settlement
        'locality',        // named place
        'suburb',          // when no higher-level name exists
        'quarter',         // city quarter/neighbourhood
        'county',          // fallback for areas without city-level divisions
    ];

    /**
     * Reverse geocode lat/lon and resolve to Country, State, City.
     *
     * Only country is required. State and city are best-effort — the photo
     * is still uploaded if they can't be resolved. Location can be fixed later.
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
        $name = $this->lookup($address, self::STATE_KEYS);

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
        $name = $this->lookup($address, self::CITY_KEYS);

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
