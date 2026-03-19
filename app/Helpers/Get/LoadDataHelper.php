<?php

namespace App\Helpers\Get;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;

class LoadDataHelper
{
    /**
     * Get the States for a Country
     *
     * /world/{country}
     *
     * @param string $url
     *
     * @return array
     */
    public static function getStates (string $url) : array
    {
        $urlText = urldecode($url);

        $country = Country::where('id', $urlText)
            ->orWhere('country', $urlText)
            ->orWhere('shortcode', $urlText)
            ->first();

        if (!$country) {
            return [
                'success' => false,
                'msg' => 'country not found'
            ];
        }

        $states = State::select(
            'id',
            'state',
            'country_id',
            'created_by',
            'created_at',
            'manual_verify',
            'updated_at'
        )
        ->with([
            'creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            },
        ])
        ->where([
            'country_id' => $country->id,
            'manual_verify' => 1,
        ])
        ->orderBy('state', 'asc')
        ->get();

        $total_litter = 0;
        $total_photos = 0;

        $countryName = $country->country;

        foreach ($states as $state)
        {
            // Get Creator info
            $state = LocationHelper::getCreatorInfo($state);

            $state['leaderboard'] = [];

            $total_litter += $state->total_litter_redis;
            $state->diffForHumans = $state->created_at->diffForHumans();
        }

        return [
            'success' => true,
            'countryName' => $countryName,
            'states' => $states,
            'total_litter' => $total_litter,
            'total_photos' => $total_photos
        ];
    }

    /**
     * Get the cities for the /country/state
     *
     * @param null $country (string)
     * @param string $state
     *
     * @return array
     */
    public static function getCities ($country, string $state) : array
    {
        if ($country)
        {
            $countryText = urldecode($country);

            $country = Country::where('id', $countryText)
                ->orWhere('country', $countryText)
                ->orWhere('shortcode', $countryText)
                ->first();

            if (!$country) return ['success' => false, 'msg' => 'country not found'];
        }

        $stateText = urldecode($state);

        $state = State::where('id', $stateText)
            ->orWhere('state', $stateText)
            ->first();

        if (!$state) return ['success' => false, 'msg' => 'state not found'];

        /**
         * Instead of loading the photos here on the city model,
         * save photos_per_day string on the city model
         */
        $cities = City::select(
            'id',
            'city',
            'country_id',
            'state_id',
            'created_by',
            'created_at',
            'updated_at',
            'manual_verify'
        )
        ->with([
            'creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            },
        ])
        ->where([
            ['state_id', $state->id],
        ])
        ->orderBy('city', 'asc')
        ->get();

        $countryName = $country->country;
        $stateName = $state->state;

        foreach ($cities as $city)
        {
            // Get Creator info
            $city = LocationHelper::getCreatorInfo($city);

            $city['leaderboard'] = [];
            $city['diffForHumans'] = $city->created_at->diffForHumans();
        }

        return [
            'success' => true,
            'country' => $countryName,
            'state' => $stateName,
            'cities' => $cities
        ];
    }
}
