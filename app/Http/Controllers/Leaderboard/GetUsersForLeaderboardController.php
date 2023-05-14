<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

class GetUsersForLeaderboardController extends Controller
{
    private const PER_PAGE_100 = 100;
    private const PER_PAGE_10 = 10;

    /**
     * Get the first paginated section of the global leaderboard
     *
     * @return array
     */
    public function __invoke (): array
    {
        // Step 1 - Initialise values
        // Filter all data by "today", "yesterday", "last year" etc
        $timeFilter = null;

        // Default Leaderboard Type
        // Also: model names (country, state, city)
        $leaderboardType = "users";
        // Filter all data by Location Type (eg Countries) and Id
        $locationType = null;
        $locationId = null;

        // Get the current page
        $page = (int)request('page', 1); // 1, 2, 3...
        // Global: 100 per page
        // Location: 10 per page
        $start = ($page - 1) * self::PER_PAGE_100; // 0, 100, 200...
        $end = $start + self::PER_PAGE_100 - 1; // 99, 199, 299...

        // Data to return
        $total = 1;
        $userIds = [];

        // Old key to get all global leaders
        // This will change if we are filtering by time or location
        $queryFilter = "xp_redis";

        // Step 2 - Update initialised values by optional request params
        if (request()->has('timeFilter')) {
            $timeFilter = request('timeFilter');
        }

        if (request()->has('locationType') && request()->has('locationId')) {
            $locationId = request('locationId');
            $locationType = request('locationType');
        }

        if ($locationType !== null) {
            // when filtering by location, start and end should be 10 per page
            $start = ($page - 1) * self::PER_PAGE_10; // 0, 10, 20...
            $end = $start + self::PER_PAGE_10 - 1; // 9, 19, 29...
        }

        // Step 3 - Use variables to get the userIds from Redis
        // Returns
        // - total
        // - userIds
        // - queryFilter
        // - leaderboardType
        $leaderboardData = $this->getGlobalLeaderboard(
            $timeFilter,
            $start,
            $end,
            $total,
            $userIds,
            $queryFilter,
            $locationType,
            $locationId
        );

        $total = $leaderboardData['total'];

        // Array of userIds from Redis
        $userIds = $leaderboardData['userIds'];

        // todays_xp, yesterdays_xp...
        $queryFilter = $leaderboardData['queryFilter'];

        // users, country, state, or city
        $leaderboardType = $leaderboardData['leaderboardType'];

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
//            ->append($queryFilter)
//            ->sortByDesc($queryFilter)
            ->values()
            ->map(function (User $user, $index) use ($start, $queryFilter, $leaderboardType, $locationId) {

                $xp = 0;

                if ($queryFilter === "users")
                {
                    $user->append($queryFilter);
                }
                else
                {
                    // Get the XP value for the Leaderboard type
                    // Global / all users all time
                    // Filtered by location / time
                    $param = [
                        'leaderboardType' => $leaderboardType,
                        'locationId' => $locationId,
                        'queryFilter' => $queryFilter
                    ];

                    $xp = $user->getXpWithParams($param);

                    \Log::info($xp);

                    // $user[$queryFilter] = $int;
                }

                // Team Name
                $showTeamName = $user->active_team && $user->teams
                    ->where('pivot.team_id', $user->active_team)
                    ->first(function ($value, $key) {
                        return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                    });

                return [
                    'user' => $user,
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    // 'xp' => number_format($user->$queryFilter),
                    'xp' => $xp,
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
                    'rank' => $start + $index + 1
                ];
            })
            ->toArray();

        // Sort users by XP.

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1
        ];
    }

    /**
     * Get data for the Global Leaderboard
     *
     * - Global leaderboard (All users, all locations, all time)
     * - Per location (UserIds for a Country, State, or City)
     *
     * Returns leaderboardType
     *
     * @param $timeFilter
     * @param $start
     * @param $end
     * @param $total
     * @param $userIds
     * @param $queryFilter
     * @param $locationType
     * @param $locationId
     * @return array
     */
    private function getGlobalLeaderboard (
        $timeFilter,
        $start,
        $end,
        $total,
        $userIds,
        $queryFilter,
        $locationType,
        $locationId
    ): array
    {
        // Users for global leaderboard
        // We can also use model definition: country, state, city if we have locationType
        $leaderboardType = "users";

        // Get Global Leaderboard for all users, all locations
        if ($timeFilter === null || $timeFilter === 'all-time')
        {
            $total = Redis::zcount('xp.users', '-inf', '+inf');
            $userIds = Redis::zrevrange("xp.users", $start, $end);
        }
        else
        {
            // Filter by time / location
            if ($locationType !== null)
            {
                $leaderboardType = $locationType;
            }

            if ($timeFilter === 'today')
            {
                $year = now()->year;
                $month = now()->month;
                $day = now()->day;

                if ($leaderboardType === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year:$month:$day", $start, $end);
                }

                $queryFilter = "todays_xp";
            }
            else if ($timeFilter === 'yesterday')
            {
                $year = now()->subDays(1)->year;
                $month = now()->subDays(1)->month;
                $day = now()->subDays(1)->day;

                if ($leaderboardType === "users")
                {
                    $total = Redis::zcount("leaderboard:users:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month:$day", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year:$month:$day", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year:$month:$day", $start, $end);
                }

                $queryFilter = "yesterdays_xp";
            }
            else if ($timeFilter === 'this-month')
            {
                $year = now()->year;
                $month = now()->month;

                if ($leaderboardType === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year:$month", $start, $end);
                }

                $queryFilter = "this_months_xp";
            }
            else if ($timeFilter === 'last-month')
            {
                $year = now()->subMonths(1)->year;
                $month = now()->subMonths(1)->month;

                if ($leaderboardType === "users") {
                    $total = Redis::zcount("leaderboard:users:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year:$month", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year:$month", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year:$month", $start, $end);
                }

                $queryFilter = "last_months_xp";
            }
            else if ($timeFilter === 'this-year')
            {
                $year = now()->year;

                if ($leaderboardType === "users") {
                    $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year", $start, $end);
                }

                $queryFilter = "this_years_xp";
            }
            else if ($timeFilter === 'last-year')
            {
                $year = now()->year -1;

                if ($leaderboardType === "users") {
                    $total = Redis::zcount("leaderboard:users:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:users:$year", $start, $end);
                } else {
                    $total = Redis::zcount("leaderboard:$leaderboardType:$locationId:$year", '-inf', '+inf');
                    $userIds = Redis::zrevrange("leaderboard:$leaderboardType:$locationId:$year", $start, $end);
                }

                $queryFilter = "last_years_xp";
            }
        }

        return [
            'leaderboardType' => $leaderboardType,
            'total' => $total,
            'userIds' => $userIds,
            'queryFilter' => $queryFilter
        ];
    }
}
