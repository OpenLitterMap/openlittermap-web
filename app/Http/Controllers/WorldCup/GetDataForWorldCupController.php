<?php

namespace App\Http\Controllers\WorldCup;

use App\Models\Littercoin;
use App\Models\Location\Country;
use App\Helpers\Get\LocationHelper;
use App\Models\Leaderboard\Leaderboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class GetDataForWorldCupController extends Controller
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
     */
    public function __invoke (): array
    {
        $littercoin = Littercoin::count();

        /**
         * Get the Countries
         *
         * To do
         *  1. update all user_ids in country.created_by column
         *  2. Find out how to get top-10 more efficiently
         *  3. Paginate
         *  4. Automate 'manual_verify => 1'
         */
        $countries = Country::with([
            'creator' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby', 'created_at', 'updated_at')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            },
            'lastUploader' => function ($q) {
                $q->select('id', 'name', 'username', 'show_name_createdby', 'show_username_createdby', 'created_at', 'updated_at')
                    ->where('show_name_createdby', true)
                    ->orWhere('show_username_createdby', true);
            }
        ])
        ->where('manual_verify', true)
        ->orderBy('country', 'asc')
        ->get();

        $total_litter = 0;
        $total_photos = 0;

        foreach ($countries as $country)
        {
            // Get firstUploader (creator) and lastUploader
            // We should be loading this dynamically
            $country = LocationHelper::getCreatorInfo($country);

            // Get Leaderboard per country. Should load more and stop when there are 10-max as some users settings may be off.
//            $leaderboardIds = Redis::zrevrange("xp.country.$country->id", 0, 9, 'withscores');
//            $country['leaderboard'] = Leaderboard::getLeadersByUserIds($leaderboardIds);
            $country['leaderboard'] = [];

            // Total values
            $country['avg_photo_per_user'] = $country->total_contributors > 0
                ? round($country->total_photos_redis / $country->total_contributors, 2)
                : 0;
            $country['avg_litter_per_user'] = $country->total_contributors > 0
                ? round($country->total_litter_redis / $country->total_contributors, 2)
                : 0;

            // Right now this is the only way to calculate the global data scores.
            // The global scores should move to their own redis store.
            $total_photos += $country->total_photos_redis;
            $total_litter += $country->total_litter_redis;

            $country['diffForHumans'] = $country->created_at->diffForHumans();
        }

        $previousXp = 0;
        $nextXp = 0;

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

        /** Today's Leaderboard */
        $year = now()->year;
        $month = now()->month;
        $day = now()->day;

        $leaderboardIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", 0, 9, 'withscores');
        $globalLeaders = Leaderboard::getLeadersByUserIds($leaderboardIds);

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
}
