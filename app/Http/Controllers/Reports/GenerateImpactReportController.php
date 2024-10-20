<?php

namespace App\Http\Controllers\Reports;

use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\View\View;
use Illuminate\Support\Number;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class GenerateImpactReportController extends Controller
{
    /**
     * Generate a weekly impact report
     */
    public function __invoke (): View
    {
        $start = now()->subWeek()->startOfWeek()->toDateTimeString();
        $end = now()->subWeek()->endOfWeek()->toDateTimeString();

        // Generate a unique cache key based on the date range
        $cacheKey = "impact_report:{$start}_{$end}";
        $expirationTime = Carbon::parse($end)->endOfDay();

        // Try to retrieve the report from the cache
        $report = Cache::remember($cacheKey, $expirationTime, function () use ($start, $end) {
            // Generate the report if it's not in the cache
            $startDate = Carbon::parse($start)->format('D jS M Y');
            $endDate = Carbon::parse($end)->format('D jS M Y');

            // Users
            $newUsers = User::whereBetween('created_at', [$start, $end])->count();
            $totalUsers = User::count();

            // Top Users
            $topUsers = $this->getTopUsers($start, $end);

            // Medals
            $medals = $this->getMedals();

            // Photos
            $newPhotos = Photo::whereBetween('created_at', [$start, $end])->count();
            $totalPhotos = Photo::count();

            // Tags
            // We should move this to Redis
            $totalTags = Photo::where('verified', '>=', 2)->sum('total_litter');

            [$topTags, $topBrands, $newTags] = $this->getTopLitter($start, $end);

            return [
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
            ];
        });

        return view('reports.impact', $report);
    }

    protected function getTopUsers (string $start, string $end): array
    {
        $userScores = [];

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        // Loop through each day of the last week
        for ($date = $startDate; $date->lte($endDate); $date->addDay())
        {
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

        $users = User::query()
            ->with(['teams:id,name'])
            ->whereIn('id', $top10UserIds)
            ->get()
            ->keyBy('id');

        // Get the users
        return collect($top10UserIds)->filter(function ($userId) use ($users) {
            // Filter out null users
            return $users->has($userId);
        })->map(function ($userId, $index) use ($userScores, $users) {
            $user = $users->get($userId);
            $weeklyXp = $userScores[$userId] ?? 0;

            return [
                'name' => $user->show_name ? $user->name : '',
                'username' => $user->show_username ? ('@' . $user->username) : '',
                'xp' => number_format($weeklyXp),
                'global_flag' => $user->global_flag,
                'social' => !empty($user->social_links) ? $user->social_links : null,
                'team' => $user->teams->first() ? $user->teams->first()->name : '', // Use the first team, if any
                'rank' => $index + 1,
                'ordinal' => Number::ordinal($index + 1),
            ];
        })->toArray();
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
        $newTags = 0;

        $litterJson = $this->getLitterJson();

        $photos = Photo::whereBetween('created_at', [$start, $end])->get();

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

                        $newTags += $quantity;
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
                    if (isset($topTags[$attributeName])) {
                        $topTags[$attributeName] += $quantity;
                    } else {
                        $topTags[$attributeName] = $quantity;
                    }

                    $newTags += $quantity;
                }
            }
        }

        // Sort by quantity in descending order
        arsort($topTags);
        arsort($topBrands);

        $finalTags = array_slice($topTags, 0, 10);
        $finalBrands = array_slice($topBrands, 0, 10);

        return [$finalTags, $finalBrands, $newTags];
    }

    protected function getLitterJson (): array
    {
        $path = resource_path('js/langs/en/litter.json');

        $contents = File::get($path);

        return json_decode($contents, true);
    }
}
