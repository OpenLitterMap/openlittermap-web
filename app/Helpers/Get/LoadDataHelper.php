<?php

namespace App\Helpers\Get;

use App\Models\Leaderboard\Leaderboard;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Support\Facades\Redis;

class LoadDataHelper
{
    /**
     * Get the States for a Country
     *
     * /world/{country}
     *
     *
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
            'total_contributors',
            'updated_at',
            'user_id_last_uploaded'
        )
        ->with([
            'creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            },
            'lastUploader' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby', 'created_at', 'updated_at')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            }
        ])
        ->where([
            'country_id' => $country->id,
            'manual_verify' => 1,
            ['total_litter', '>', 0],
            ['total_contributors', '>', 0]
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

            // Get Leaderboard
            // $leaderboardIds = Redis::zrevrange("xp.country.$country->id.state.$state->id", 0, 9, 'withscores');
            // $state->leaderboard = Leaderboard::getLeadersByUserIds($leaderboardIds);
            $state['leaderboard'] = [];

            // Get images/litter metadata
            $state->avg_photo_per_user = $state->total_contributors > 0
                ? round($state->total_photos_redis / $state->total_contributors, 2)
                : 0;
            $state->avg_litter_per_user = $state->total_contributors > 0
                ? round($state->total_litter_redis / $state->total_contributors, 2)
                : 0;

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
     *
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

            if (!$country) {
                return ['success' => false, 'msg' => 'country not found'];
            }
        }

        $stateText = urldecode($state);

        // ['total_images', '!=', null]
        $state = State::where('id', $stateText)
            ->orWhere('state', $stateText)
            ->orWhere('statenameb', $stateText)
            ->first();

        if (!$state) {
            return ['success' => false, 'msg' => 'state not found'];
        }

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
            'manual_verify',
            'total_contributors',
            'user_id_last_uploaded'
        )
        ->with([
            'creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            },
            'lastUploader' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby', 'created_at', 'updated_at')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            }
        ])
        ->where([
            ['state_id', $state->id],
            ['total_images', '>', 0],
            ['total_litter', '>', 0],
            ['total_contributors', '>', 0]
        ])
        ->orderBy('city', 'asc')
        ->get();

        $countryName = $country->country;
        $stateName = $state->state;

        foreach ($cities as $city)
        {
            // Get Creator info
            $city = LocationHelper::getCreatorInfo($city);

            // Get Leaderboard
//            $leaderboardIds = Redis::zrevrange("xp.country.$country->id.state.$state->id.city.$city->id", 0, 9, 'withscores');
//            $city['leaderboard'] = Leaderboard::getLeadersByUserIds($leaderboardIds);
            $city['leaderboard'] = [];

            $city['avg_photo_per_user'] = $city->total_contributors > 0
                ? round($city->total_photos_redis / $city->total_contributors, 2)
                : 0;
            $city['avg_litter_per_user'] = $city->total_contributors > 0
                ? round($city->total_litter_redis / $city->total_contributors, 2)
                : 0;
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
