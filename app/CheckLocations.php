<?php

namespace App;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;

use App\Events\NewCityAdded;
use App\Events\NewStateAdded;
use App\Events\NewCountryAdded;

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
                // Broadcast an event to anyone viewing the Global Map
                event (new NewCountryAdded($this->country, $this->countryCode, now(), $userId));
            }

            $this->countryId = $country->id;
        }
    }

    /**
     * Check addressArray for State value
     *
     * The "State" is the second layer in a Location.
     *
     * Country -> many states
     * State -> many cities
     *
     * Additional keys may need to be checked here.
     *
     * The address values in OpenStreetMap are also not very consistent.
     *
     * For example, in Australia, a State can be saved as "Queensland", or "QLD"
     * Therefore, we have "state" and "statenameb" to represent different values
     */
    protected function checkState ($addressArray, $userId)
    {
        \Log::info(['checkState.countryId', $this->countryId]);
        \Log::info(['checkState.stateId', $this->stateId]);
        \Log::info(['checkState.state', $this->state]);

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

            try
            {
                $state = State::select('id', 'country_id', 'state', 'statenameb')
                    ->where([
                        'state' => $this->state,
                        'country_id' => $this->countryId
                    ])
                    ->first();

                if (!$state)
                {
                    $state = State::create([
                        'country_id' => $this->countryId,
                        'state' => $this->state
                    ]);
                }

                if ($state->wasRecentlyCreated)
                {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewStateAdded($this->state, $this->country, now(),$userId));
                }

                $this->stateId = $state->id;
            }
            catch (\Exception $e)
            {
                \Log::info(['CheckLocations.checkState', $e->getMessage()]);
            }
        }
    }

    /**
     * Check addressArray for "City"

     * Country -> State -> Cities
     *
     * A State has many "cities"
     *
     * Additional keys may need to be checked here.
     */
    protected function checkCity ($addressArray, $userId)
    {
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
            try
            {
                $city = City::select('id', 'country_id', 'state_id', 'city')
                    ->where([
                        'country_id' => $this->countryId,
                        'state_id' => $this->stateId,
                        'city' => $this->city
                    ])
                    ->first();

                if (!$city)
                {
                    $city = City::create([
                        'country_id' => $this->countryId,
                        'state_id' => $this->stateId,
                        'city' => $this->city
                    ]);
                }

                $this->cityId = $city->id;

                if ($city->wasRecentlyCreated)
                {
                    // Broadcast an event to anyone viewing the Global Map
                    event(new NewCityAdded($this->city, $this->state, $this->country, now(), $userId));
                }
            }
            catch (\Exception $e)
            {
                \Log::info(['CheckLocations@createCity', $e->getMessage()]);
            }
        }
    }
}
