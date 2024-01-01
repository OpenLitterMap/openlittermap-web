<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Littercoin;
use App\Models\Location\Country;
use App\Models\User\User;
use Illuminate\Http\Request;

class GlobalStatsController extends Controller
{
    /**
     * Get the global data to show on the app
     *
     * Todo: We want to move all of this data to redis asap to avoid killing the database!
     * A lot of this taken from LoadDataHelper@getCountries. We need to remove the duplication.
     */
    public function index () : array
    {
        $littercoin = Littercoin::count();

        $totalUsers = User::count();

        $countries = Country::select('id')
            ->where('manual_verify', '1')
            ->orderBy('country', 'asc')
            ->get();

        $total_litter = 0;
        $total_photos = 0;

        foreach ($countries as $country)
        {
            $total_photos += $country->total_photos_redis;
            $total_litter += $country->total_litter_redis;
        }

        /**
         * Global levels
         *
         * todo - Make this dynamic
         *
         * See: GlobalLevels.php global_levels table
         */
        // level 0
        if ($total_litter <= 1000) {
            $previousXp = 0;
            $nextXp = 1000;
        } elseif ($total_litter <= 10000) {
            $previousXp = 1000;
            $nextXp = 10000;
            // 10,000
        } elseif ($total_litter <= 100000) {
            $previousXp = 10000;
            // 10,000
            $nextXp = 100000;
            // 100,000
        } elseif ($total_litter <= 250000) {
            $previousXp = 100000;
            // 100,000
            $nextXp = 250000;
            // 250,000
        } elseif ($total_litter <= 500000) {
            $previousXp = 250000;
            // 250,000
            $nextXp = 500000;
            // 500,000
        } elseif ($total_litter <= 1000000) {
            $previousXp = 500000;
            // 250,000
            $nextXp = 1000000;
            // 500,000
        }

        return [
            'total_litter' => $total_litter,
            'total_photos' => $total_photos,
            'previousXp' => $previousXp,
            'nextXp' => $nextXp,
            'littercoin' => $littercoin,
            'total_users' => $totalUsers
        ];
    }
}
