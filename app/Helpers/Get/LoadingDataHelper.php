<?php


namespace App\Helpers\Get;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

use App\Helpers\Get\CheckLocationHelper;

trait LoadingDataHelper
{
    use CheckLocationHelper;

    /**
     * Get the Maps & Data page, incl all Countries metadata
     */
    public static function getCountries ()
    {
        // first - global metadata
        $littercoin = \DB::table('users')->sum(\DB::raw('littercoin_owed + littercoin_allowance'));

        /**
         *  Todo
        1. save user_id in country created_by column
        2. Find out how to get top-10 more efficiently
        3. Paginate
        4. Automate 'manual_verify => 1'
        5. Eager load leaders with the country model
         */
        $countries = Country::with(['creator' => function($q) {
            $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby')
                ->where('show_name_createdby', true)
                ->orWhere('show_username_createdby', true);
        }])
        ->where('manual_verify', '1')
        ->orderBy('country', 'asc')
        ->get();

        $total_litter = 0;
        $total_photos = 0;

        foreach ($countries as $country)
        {
            // Get Creator info
            $country = CheckLocationHelper::getCreatorInfo($country);

            // Get Leaderboard per country. Should load more and stop when there are 10-max as some users settings may be off.
            $leaderboard_ids = Redis::zrevrange($country->country.':Leaderboard', 0, 9);

            $leaders = User::whereIn('id', $leaderboard_ids)->orderBy('xp', 'desc')->get();

            $arrayOfLeaders = CheckLocationHelper::getLeaders($leaders);

            $country['leaderboard'] = json_encode($arrayOfLeaders);

            // Total values
            $country['avg_photo_per_user'] = round($country->total_photos_redis / $country->total_contributors, 2);
            $country['avg_litter_per_user'] = round($country->total_litter_redis / $country->total_contributors, 2);

            $total_photos += $country->total_photos_redis;
            $total_litter += $country->total_litter_redis;

            $country['diffForHumans'] = $country->created_at->diffForHumans();
        }

        /**
         * Global levels
         *
         * todo - Make this dynamic
         *
         * Someone please refactor this!!!
         *
         * See: GlobalLevels.php global_levels table
         * We need to keep earlier levels for test databases
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
        $users = User::where('xp', '>', 9000)
            ->orderBy('xp', 'desc')
            ->where('show_name', 1)
            ->orWhere('show_username', 1)
            ->limit(10)
            ->get();

        $newIndex = 0;
        $globalLeaders = [];
        foreach ($users as $user)
        {
            $name = '';
            $username = '';
            if (($user->show_name) || ($user->show_username))
            {
                if ($user->show_name) $name = $user->name;

                if ($user->show_username) $username = '@' . $user->username;

                $globalLeaders[$newIndex] = [
                    'position' => $newIndex,
                    'name' => $name,
                    'username' => $username,
                    'xp' => number_format($user->xp),
                    'flag' => $user->global_flag
                    // 'level' => $user->level,
                    // 'linkinsta' => $user->link_instagram
                ];

                $newIndex++;
            }
        }

        $globalLeadersString = json_encode($globalLeaders);

        return [
            'countries' => $countries,
            'total_litter' => $total_litter,
            'total_photos' => $total_photos,
            'globalLeaders' => $globalLeadersString,
            'previousXp' => $previousXp,
            'nextXp' => $nextXp,
            'littercoin' => $littercoin,
            'owed' => 0
        ];
    }

    /**
     * Get the States for a Country
     *
     * Todo: add shortcode
     *
     * @param $country
     *
     * @return array
     */
    public static function getStates ($country) : array
    {
        $country = Country::where('id', $country)
            ->orWhere('country', $country)
            ->orWhere('shortcode', $country)
            ->first();

        if (!$country) return ['success' => false, 'msg' => 'country not found'];

        $states = State::select('id', 'state', 'country_id', 'created_by', 'created_at', 'manual_verify', 'total_contributors')
            ->with(['creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name', 'show_username')
                    ->where('show_name', true)
                    ->orWhere('show_username', true);
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
            $state = CheckLocationHelper::getCreatorInfo($state);

            // Get Leaderboard
            $leaderboard_ids = Redis::zrevrange($countryName.':'.$state->state.':Leaderboard',0,9);

            $leaders = User::whereIn('id', $leaderboard_ids)->orderBy('xp', 'desc')->get();

            $arrayOfLeaders = CheckLocationHelper::getLeaders($leaders);

            $state->leaderboard = json_encode($arrayOfLeaders);

            // Get images/litter metadata
            $state->avg_photo_per_user = round($state->total_photos_redis / $state->total_contributors, 2);
            $state->avg_litter_per_user = round($state->total_litter_redis / $state->total_contributors, 2);

            $total_litter += $state->total_litter_redis;
            $state->diffForHumans = $state->created_at->diffForHumans();

            if ($state->creator)
            {
                $state->creator->name = ($state->creator->show_name) ? $state->creator->name : "";
                $state->creator->username = ($state->creator->show_username) ? $state->creator->username : "";
            }
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
     */
    public static function getCities ($stateId)
    {
        $state = State::where([
            ['id', $stateId],
            ['total_images', '!=', null]
        ])->first();

        if (!$state) return ['success' => false, 'msg' => 'state not found'];

        /**
         * Instead of loading the photos here on the city model,
         * save photos_per_day string on the city model
         */
        $cities = City::select('id', 'city', 'country_id', 'state_id', 'created_by', 'created_at', 'manual_verify', 'total_contributors')
            ->with(['creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name', 'show_username')
                    ->where('show_name', true)
                    ->orWhere('show_username', true);
            }])
            ->where([
                ['state_id', $state->id],
                ['total_images', '>', 0],
                ['total_litter', '>', 0],
                ['total_contributors', '>', 0]
            ])
            ->orderBy('city', 'asc')
            ->get();

        $countryName = $state->country->country;
        $stateName = $state->state;

        foreach ($cities as $city)
        {
            // Get Creator info
            $city = CheckLocationHelper::getCreatorInfo($city);

            // Get Leaderboard
            $leaderboard_ids = Redis::zrevrange($countryName . ':' . $stateName . ':' . $city->city . ':Leaderboard', 0, 9);

            $leaders = User::whereIn('id', $leaderboard_ids)->orderBy('xp', 'desc')->get();

            $arrayOfLeaders = CheckLocationHelper::getLeaders($leaders);

            $city['leaderboard'] = json_encode($arrayOfLeaders);
            $city['avg_photo_per_user'] = round($city->total_photos_redis / $city->total_contributors, 2);
            $city['avg_litter_per_user'] = round($city->total_litter_redis / $city->total_contributors, 2);
            $city['diffForHumans'] = $city->created_at->diffForHumans();

            if ($city->creator)
            {
                $city->creator->name = ($city->creator->show_name) ? $city->creator->name : "";
                $city->creator->username = ($city->creator->show_username) ? $city->creator->username : "";
            }
        }

        return [
            'success' => true,
            'country' => $countryName,
            'state' => $stateName,
            'cities' => $cities
        ];
    }
}
