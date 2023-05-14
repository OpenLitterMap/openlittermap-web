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

        // Default Leaderboard Type
        // Also: model names (country, state, city)
        // $leaderboardType = "country";

        // Filter all data by Location Type (eg Countries) and Id
        $locationType = null;
        $locationId = null;

        // Get the current page
        $page = (int)request('page', 1);
        $start = ($page - 1) * self::PER_PAGE;
        $end = $start + self::PER_PAGE - 1;

        // Data to return
        $total = 1;
        $userIds = [];

        // Step 2 - Update initialised values by optional request params
        if (request()->has('timeFilter')) {
            $timeFilter = request('timeFilter');
        }

        if (request()->has('locationType') && request()->has('locationId')) {
            $locationId = request('locationId');
            $locationType = request('locationType');
        }

        if ($timeFilter === null || $locationId === null || $locationType === null)
        {
            return [
                'success' => false,
                'msg' => 'missing params'
            ];
        }

        // Step 3 - Use variables to get the userIds from Redis
        // Returns
        // - total
        // - userIds
        // - queryFilter
        // - leaderboardType
        $leaderboardData = $this->getDataForLocationLeaderboard(
            $timeFilter,
            $start,
            $end,
            $total,
            $userIds,
            $locationType,
            $locationId
        );

        $total = $leaderboardData['total'];

        // Array of userIds from Redis
        $userIds = $leaderboardData['userIds'];

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $userIds)
            ->get()
            ->sortBy('xp')
            ->values()
            ->map(function (User $user, $index) use ($start, $timeFilter, $locationType, $locationId) {

                // Get the XP value for the Leaderboard type
                // Global / all users all time
                // Filtered by location / time
                $param = [
                    'locationType' => $locationType,
                    'locationId' => $locationId,
                    'timeFilter' => $timeFilter
                ];

                $xp = $user->getXpWithParams($param);

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
                    'xp' => number_format($xp),
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
                    'rank' => $start + $index + 1
                ];
            })
            ->toArray();

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
     * @param $locationType "country", "state", "city"
     * @param $locationId
     * @return array
     */
    private function getDataForLocationLeaderboard (
        $timeFilter,
        $start,
        $end,
        $total,
        $userIds,
        $locationType,
        $locationId
    ): array
    {
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

        return [
            'total' => $total,
            'userIds' => $userIds,
        ];
    }
}