<?php

namespace App\Helpers\Get;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

class LoadDataHelper
{
    use LocationHelper;

    /**
     * Get the World Cup page
     *
     * - Global Metadata
     *   - total photos
     *   - total litter
     *   - total littercoin
     *
     * - Countries array
     *
     * @return array $countries...
     */
    public static function getCountries ()
    {
        // first - global metadata
        $littercoin = \DB::table('users')->sum(\DB::raw('littercoin_owed + littercoin_allowance'));

        /**
         * Get the countries
         *  Todo
            1. update all user_ids in country.created_by column
            2. Find out how to get top-10 more efficiently
            3. Paginate
            4. Automate 'manual_verify => 1'
            5. Eager load leaders with the country model
         */
        $countries = Country::with(['creator' => function ($q) {
            $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby', 'created_at', 'updated_at')
              ->where('show_name_createdby', true)
              ->orWhere('show_username_createdby', true);
        }])
        ->where('manual_verify', true)
        ->orderBy('country', 'asc')
        ->get();

        $total_litter = 0;
        $total_photos = 0;

        foreach ($countries as $country)
        {
            // Get Creator info
            $country = LocationHelper::getCreatorInfo($country);

            // Get Leaderboard per country. Should load more and stop when there are 10-max as some users settings may be off.
            $leaderboardIds = Redis::zrevrange("xp.country.$country->id", 0, 9, 'withscores');

            $country['leaderboard'] = self::getLeadersFromLeaderboards($leaderboardIds);

            // Total values
            $country['avg_photo_per_user'] = $country->total_contributors > 0
                ? round($country->total_photos_redis / $country->total_contributors, 2)
                : 0;
            $country['avg_litter_per_user'] = $country->total_contributors > 0
                ? round($country->total_litter_redis / $country->total_contributors, 2)
                : 0;

            $total_photos += $country->total_photos_redis;
            $total_litter += $country->total_litter_redis;

            $country['diffForHumans'] = $country->created_at->diffForHumans();
            $country['updatedAtDiffForHumans'] = $country->updated_at->diffForHumans();
        }

        /**
         * Global levels
         *
         * todo - Make this dynamic
         *
         * See: GlobalLevels.php global_levels table
         */
        // level 0
        if ($total_litter <= 1000)
        {
            $previousXp = 0;
            $nextXp = 1000;
        }
        // level 1 - target, 10,000
        else if ($total_litter <= 10000)
        {
            $previousXp = 1000;
            $nextXp = 10000; // 10,000
        }
        // level 2 - target, 100,000
        else if ($total_litter <= 100000)
        {
            $previousXp = 10000; // 10,000
            $nextXp = 100000; // 100,000
        }
        // level 3 - target 250,000
        else if ($total_litter <= 250000)
        {
            $previousXp = 100000; // 100,000
            $nextXp = 250000; // 250,000
        }
        // level 4 500,000
        else if ($total_litter <= 500000)
        {
            $previousXp = 250000; // 250,000
            $nextXp = 500000; // 500,000
        }
        // level 5, 1M
        else if ($total_litter <= 1000000)
        {
            $previousXp = 250000; // 250,000
            $nextXp = 1000000; // 500,000
        }

        /** GLOBAL LITTER MAPPERS */
        $leaderboardIds = Redis::zrevrange("xp.users", 0, 9, 'withscores');
        $globalLeaders = self::getLeadersFromLeaderboards($leaderboardIds);

        return [
            'countries' => $countries,
            'total_litter' => $total_litter,
            'total_photos' => $total_photos,
            'globalLeaders' => $globalLeaders,
            'previousXp' => $previousXp,
            'nextXp' => $nextXp,
            'littercoin' => $littercoin,
            'owed' => 0
        ];
    }

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

        if (!$country) return ['success' => false, 'msg' => 'country not found'];

        $states = State::select('id', 'state', 'country_id', 'created_by', 'created_at', 'manual_verify', 'total_contributors')
            ->with(['creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            }])
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
            $leaderboardIds = Redis::zrevrange("xp.country.$country->id.state.$state->id", 0, 9, 'withscores');

            $state->leaderboard = self::getLeadersFromLeaderboards($leaderboardIds);

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

        // ['total_images', '!=', null]
        $state = State::where('id', $stateText)
            ->orWhere('state', $stateText)
            ->orWhere('statenameb', $stateText)
            ->first();

        if (!$state) return ['success' => false, 'msg' => 'state not found'];

        /**
         * Instead of loading the photos here on the city model,
         * save photos_per_day string on the city model
         */
        $cities = City::select('id', 'city', 'country_id', 'state_id', 'created_by', 'created_at', 'manual_verify', 'total_contributors')
            ->with(['creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            }])
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
            $leaderboardIds = Redis::zrevrange("xp.country.$country->id.state.$state->id.city.$city->id", 0, 9, 'withscores');

            $city['leaderboard'] = self::getLeadersFromLeaderboards($leaderboardIds);
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

    /**
     * Gets the users from the given ids
     * Attaches to each their global or location-based XP
     * Formats them for display on the leaderboards
     *
     * @param $leaderboardIds
     * @return array
     */
    protected static function getLeadersFromLeaderboards($leaderboardIds): array
    {
        $users = User::query()
            ->whereIn('id', array_keys($leaderboardIds))
            ->get();

        $leaders = collect($leaderboardIds)
            ->map(function ($xp, $userId) use ($users) {
                $user = $users->firstWhere('id', $userId);
                if (!$user) {
                    return null;
                }
                $user->xp_redis = $xp;
                return $user;
            })
            ->filter()
            ->sortByDesc('xp_redis');

        return LocationHelper::getLeaders($leaders);
    }
}
