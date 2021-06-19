<?php

namespace App;

use App\Events\NewCityAdded;
use App\Events\NewStateAdded;
use App\Events\NewCountryAdded;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

trait CheckLocations
{
	protected $country;
    protected $countryCode;
    protected $countryId = 0;

    protected $state;
    protected $stateId = 0;

    protected $city;
    protected $cityId = 0;

    /**
     * Get the first, or create a new Country
     *
     * Using addressArray, inspect the 2-letter country_code value
     */
    protected function checkCountry ($addressArray, $userId)
    {
        $this->country = (array_key_exists('country', $addressArray))
            ? $addressArray['country']
            : 'error';

        $this->countryCode = (array_key_exists('country_code', $addressArray))
            ? $addressArray["country_code"]
            : 'error';

        if ($this->countryCode !== 'error')
        {
            $country = Country::select('id')
                ->where('shortcode', $this->countryCode)
                ->firstOrCreate();

            if ($country->wasRecentlyCreated)
            {
                event (new NewCountryAdded($this->country, $this->countryCode, now(), $userId));
            }

            $this->countryId = $country->id;
        }
    }

    /**
     * Check addressArray for State value
     *
     * The word "State" is used loosely and is the second layer in a Location.
     *
     * Country -> many states -> many cities.
     *
     * More generally, State means 2nd sub-country level.
     *
     * Eg. Country = USA, has many States (Cali, Texas etc.)
     * Eg. Country = UK,  has many "States", eg. England, Wales, Scotland, (even though they are countries).
     * Eg. Country = Ireland, has many "States", eg. County Cork, County Dublin, (even though they are counties).
     *
     * Not perfect, but it helps us divide a Country into 3 layers.
     *
     * Additional keys may need to be checked here.
     *
     * The address values in OpenStreetMap are also not very consistent.
     *
     * For example, in Australia, a State can be saved as "Queensland", but "QLD" is also used.
     * Therefore, we have "state" and "statenameb" to represent the name of the state as a string.
     */
    protected function checkState ($addressArray, $userId)
    {
        if (array_key_exists('state', $addressArray))
        {
            $this->state = $addressArray["state"];
        }
        if (!$this->state)
        {
            if (array_key_exists('county', $addressArray))
            {
                $this->state = $addressArray["county"];
            }
        }
        if (!$this->state)
        {
            if (array_key_exists('region', $addressArray))
            {
                $this->state = $addressArray["region"];
            }
        }
        if (!$this->state)
        {
            $this->state = 'error';
        }

        if ($this->state !== 'error')
        {
            $state = State::select('id', 'state', 'statenameb')
                ->where([
                    'state' => $this->state,
                    'country_id' => $this->countryId
                ])
                ->orWhere([
                    'statenameb' => $this->state,
                    'country_id' => $this->countryId
                ])
                ->firstOrCreate();

            if ($state->wasRecentlyCreated)
            {
                event(new NewStateAdded($this->state, $this->country, now(),$userId));
            }

            $this->stateId = $state->id;
        }
    }

    /**
     * Check addressArray for "City"
     *
     * A "City" is the 3rd layer in the Location model
     *
     * Country -> State -> Cities
     *
     * A State has many "cities"
     *
     * The word "City" can also mean town, village, etc.
     *
     * Additional keys may need to be checked here.
     */
    protected function checkCity ($addressArray, $userId)
    {
        // city, town, hamlet, city_district, village
        if (array_key_exists('city', $addressArray))
        {
            $this->city = $addressArray['city'];
        }
        if (!$this->city)
        {
            if (array_key_exists('town', $addressArray))
            {
                $this->city = $addressArray['town'];
            }
        }
        if (!$this->city)
        {
            if (array_key_exists('city_district', $addressArray))
            {
                $this->city = $addressArray['city_district'];
            }
        }
        if (!$this->city)
        {
            if (array_key_exists('village', $addressArray))
            {
                $this->city = $addressArray['village'];
            }
        }
        if (!$this->city)
        {
            if (array_key_exists('hamlet', $addressArray))
            {
                $this->city = $addressArray['hamlet'];
            }
        }
        if (!$this->city)
        {
            if (array_key_exists('locality', $addressArray))
            {
                $this->city = $addressArray['locality'];
            }
        }
        if (!$this->city)
        {
            if (array_key_exists('county', $addressArray))
            {
                $this->city = $addressArray['county'];
            }
        }
        if (!$this->city)
        {
            $this->city = 'error';
        }

        if ($this->city != 'error')
        {
            $city = City::select('id')
                ->where([
                    'country_id' => $this->countryId,
                    'state_id' => $this->stateId,
                    'city' => $this->city
                ])
                ->firstOrCreate();

            if ($city->wasRecentlyCreated)
            {
                event(new NewCityAdded($this->city, $this->state, $this->country, now(), $userId));
            }

            $this->cityId = $city->id;
        }
    }
}
