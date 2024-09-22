<?php

namespace App\Http\Controllers\Reports;

use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use App\Models\Location\Country;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class GenerateImpactReportController extends Controller
{
    /**
     * Generate a weekly impact report
     */
    public function __invoke () //: View
    {
        $start = now()->subWeek()->startOfWeek();
        $end = now()->subWeek()->endOfWeek();

        // Mon 9th Sept 2024 - Sun 15th Sept 2024
        $startDate = Carbon::parse($start)->format('D jS M Y');
        $endDate = Carbon::parse($end)->format('D jS M Y');

        $newUsers = User::whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->count();
        $totalUsers = User::count();

        $newPhotos = Photo::whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->count();
        $totalPhotos = Photo::count();

        $newTags = Photo::whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->sum('total_litter');

        $totalTags = 0;

        $countries = Country::where('manual_verify', true)
            ->orderBy('country', 'asc')
            ->get();

        foreach ($countries as $country)
        {
            $totalTags += $country->total_litter_redis;
        }

        $topUsers = $this->getTopUsers($start, $end);
        $medals = $this->getMedals();

        [$topTags, $topBrands] = $this->getTopLitter($start, $end);
        $topMaterials = $this->getTopMaterials($start, $end);

        return view('reports.impact', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'newUsers' => $newUsers,
            'totalUsers' => $totalUsers,
            'newPhotos' => $newPhotos,
            'totalPhotos' => $totalPhotos,
            'newTags' => $newTags,
            'totalTags' => $totalTags,
            'topUsers' => $topUsers,
            'medals' => $medals,
            'topTags' => $topTags,
            'topBrands' => $topBrands,
            'topMaterials' => $topMaterials
        ]);
    }

    protected function getTopUsers ($start, $end): array
    {
        $userScores = [];

        // Loop through each day of the last week
        for ($date = $start; $date->lte($end); $date->addDay()) {
            $year = $date->year;
            $month = $date->month;
            $day = $date->day;

            $key = "leaderboard:users:$year:$month:$day";

            // Get all users from the Redis set and their XP for that day
            $dailyUserIds = Redis::zrevrange($key, 0, -1, ['withscores' => true]);

            foreach ($dailyUserIds as $userId => $xp) {
                if (isset($userScores[$userId])) {
                    $userScores[$userId] += $xp; // Sum XP for the week
                } else {
                    $userScores[$userId] = $xp;
                }
            }
        }

        // Sort users by their weekly XP in descending order
        arsort($userScores);

        // Get the top 10 user IDs
        $top10UserIds = array_slice(array_keys($userScores), 0, 10);

        // Get the users
        return User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $top10UserIds)
            ->get()
            ->map(function (User $user, $index) use ($userScores) {
                $weeklyXp = $userScores[$user->id] ?? 0; // Use the calculated XP from Redis

                $showTeamName = $user->active_team && $user->teams
                        ->where('pivot.team_id', $user->active_team)
                        ->first(function ($value, $key) {
                            return $value->pivot->show_name_leaderboards || $value->pivot->show_username_leaderboards;
                        });

                return [
                    'name' => $user->show_name ? $user->name : '',
                    'username' => $user->show_username ? ('@' . $user->username) : '',
                    'xp' => number_format($weeklyXp),
                    'global_flag' => $user->global_flag,
                    'social' => !empty($user->social_links) ? $user->social_links : null,
                    'team' => $showTeamName ? $user->team->name : '',
                    'rank' => $index + 1
                ];
            })
            ->toArray();
    }

    protected function getMedals (): array
    {
        return [
            0 => [
                'src' => 'https://openlittermap.com/assets/icons/gold-medal-2.png',
                'alt' => 'Gold Medal'
            ],
            1 => [
                'src' => 'https://openlittermap.com/assets/icons/silver-medal-2.png',
                'alt' => 'Silver Medal'
            ],
            2 => [
                'src' => 'https://openlittermap.com/assets/icons/bronze-medal-2.png',
                'alt' => 'Bronze Medal'
            ]
        ];
    }

    protected function getTopLitter ($start, $end): array
    {
        $topTags = [];
        $topBrands = [];

        $litterJson = $this->getLitterJson();

        $photos = Photo::whereDate('created_at', '<=', $start)
            ->whereDate('created_at', '>=', $end)
            ->get();

        foreach ($photos as $photo)
        {
            // load the tags manually
            $photoTags = $photo->tags();

            foreach ($photoTags as $category => $attributes)
            {
                if ($category === 'brands')
                {
                    foreach ($attributes as $attribute => $quantity)
                    {
                        $brandName = $litterJson['brands'][$attribute] ?? $attribute;

                        if (isset($topBrands[$brandName])) {
                            $topBrands[$brandName] += $quantity;
                        } else {
                            $topBrands[$brandName] = $quantity;
                        }
                    }

                    continue;
                }

                // Loop through each attribute in the category
                foreach ($attributes as $attribute => $quantity)
                {
                    // Map the category and attribute to human-readable names
                    $categoryName = $litterJson['categories'][$category] ?? $category;
                    $attributeName = $litterJson[$category][$attribute] ?? $attribute;

                    // Increment the count for the specific attribute
                    if (isset($topTags[$categoryName][$attributeName])) {
                        $topTags[$categoryName][$attributeName] += $quantity;
                    } else {
                        $topTags[$categoryName][$attributeName] = $quantity;
                    }
                }
            }
        }

        return [$topTags, $topBrands];
    }

    protected function getLitterJson (): array
    {
        $path = resource_path('js/langs/en/litter.json');

        $contents = File::get($path);

        return json_decode($contents, true);
    }

    protected function getTopMaterials ($start, $end): array {
        return [];
    }
}
