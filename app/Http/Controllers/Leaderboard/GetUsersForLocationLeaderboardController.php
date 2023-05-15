<?php

namespace App\Http\Controllers\Leaderboard;

use App\Models\User\User;
use Illuminate\Support\Facades\Redis;

class GetUsersForLocationLeaderboardController
{
    private const PER_PAGE = 10;

    /**
     * Get the first paginated section of the global leaderboard
     *
     * params:
     *
     * All required.
     * - timeFilter = "today", "yesterday", "this-month", "last-year"
     * - locationType = "country", "state", "city"
     * - locationId = countryId, stateId, cityId
     *
     * @return array
     */
    public function __invoke (): array
    {
        // Step 1 - Initialise values
        $timeFilter = null;
        $locationType = null;
        $locationId = null;

        // Get the current page
        $page = (int)request('page', 1);
        $start = ($page - 1) * self::PER_PAGE;
        $end = $start + self::PER_PAGE - 1;

        // Step 2 - Update initialised values by optional request params
        if (request()->has('timeFilter')) {
            $timeFilter = request('timeFilter');
        }

        if (request()->has('locationType') && request()->has('locationId')) {
            $locationId = request('locationId');
            $locationType = request('locationType');
        }

        // Step 3 - Validation
        if ($timeFilter === null || $locationId === null || $locationType === null)
        {
            return [
                'success' => false,
                'msg' => 'missing params'
            ];
        }

        // Step 4 - Use variables to get the userIds from Redis
        // Returns
        // - total
        // - userIds
        // - queryFilter
        // - leaderboardType
        $leaderboardData = $this->getDataForLocationLeaderboard(
            $timeFilter,
            $start,
            $end,
            $locationType,
            $locationId
        );

        // We need the total count of all queries to check if we need a next page
        $total = $leaderboardData['total'];

        // Array of userIds from Redis
        $userIds = $leaderboardData['userIds'];

        $users = User::with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->map(function (User $user, $index) use ($start, $timeFilter, $locationType, $locationId) {

                // Get the XP value for the Leaderboard type
                // Global / all users all time
                // Filtered by location / time
                $params = [
                    'locationType' => $locationType,
                    'locationId' => $locationId,
                    'timeFilter' => $timeFilter
                ];

                $xp = $user->getXpWithParams($params);

                // Team Name
                $showTeamName = $user->active_team && $user->teams
                    ->where('pivot.team_id', $user->active_team)
                    ->first(function ($value, $key) {
                        return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                    });

                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => $xp, // number_format($xp),
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
                    // 'rank' => $start + $index + 1
                ];
            });

        $sortedUsers = collect($users)->sortByDesc('xp');
        $users = $sortedUsers->values()->all();

        return [
            'success' => true,
            'users' => $users,
            'hasNextPage' => $total > $end + 1
        ];
    }

    /**
     * Get 10 userIds and total userIds count for the Global Leaderboard
     *
     * - Global leaderboard (All users, all locations, all time)
     * - Per location (UserIds for a Country, State, or City)
     *
     * @param $timeFilter
     * @param $start
     * @param $end
     * @param $locationType "country", "state", "city"
     * @param $locationId
     * @return array
     */
    private function getDataForLocationLeaderboard (
        $timeFilter,
        $start,
        $end,
        $locationType,
        $locationId
    ): array
    {
        $userIds = [];
        $total = 0;

        if ($timeFilter === 'today')
        {
            $year = now()->year;
            $month = now()->month;
            $day = now()->day;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year:$month:$day", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year:$month:$day", $start, $end);
        }
        else if ($timeFilter === 'yesterday')
        {
            $year = now()->subDays(1)->year;
            $month = now()->subDays(1)->month;
            $day = now()->subDays(1)->day;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year:$month:$day", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year:$month:$day", $start, $end);
        }
        else if ($timeFilter === 'this-month')
        {
            $year = now()->year;
            $month = now()->month;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year:$month", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year:$month", $start, $end);
        }
        else if ($timeFilter === 'last-month')
        {
            $year = now()->subMonths(1)->year;
            $month = now()->subMonths(1)->month;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year:$month", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year:$month", $start, $end);
        }
        else if ($timeFilter === 'this-year')
        {
            $year = now()->year;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year", $start, $end);
        }
        else if ($timeFilter === 'last-year')
        {
            $year = now()->year -1;

            $total = Redis::zcount("leaderboard:$locationType:$locationId:$year", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:$year", $start, $end);
        }
        else if ($timeFilter === 'all-time')
        {
            $total = Redis::zcount("leaderboard:$locationType:$locationId:total", '-inf', '+inf');
            $userIds = Redis::zrevrange("leaderboard:$locationType:$locationId:total", $start, $end);
        }

        return [
            'total' => $total,
            'userIds' => $userIds,
        ];
    }
}