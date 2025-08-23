<?php

namespace App\Services\Locations;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class LocationService
{
    public function getLocations(string $type = 'country', array $params = [])
    {
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 50;
        $sortBy = $params['sort_by'] ?? 'total_litter_redis';
        $sortDir = $params['sort_dir'] ?? 'desc';
        $parentId = $params['parent_id'] ?? null;

        $model = match($type) {
            'country' => Country::class,
            'state' => State::class,
            'city' => City::class,
            default => Country::class,
        };

        $query = $model::with(['creator', 'lastUploader'])
            ->where('manual_verify', true);

        if ($parentId) {
            if ($type === 'state') $query->where('country_id', $parentId);
            if ($type === 'city') $query->where('state_id', $parentId);
        }

        $query->orderBy($sortBy, $sortDir);
        $locations = $query->paginate($perPage);

        $totals = [
            'photos' => $model::where('manual_verify', true)->sum('total_photos_redis'),
            'litter' => $model::where('manual_verify', true)->sum('total_litter_redis'),
        ];

        foreach ($locations as $location) {
            $location->percentage_litter = $totals['litter'] > 0
                ? round(($location->total_litter_redis / $totals['litter']) * 100, 2)
                : 0;
            $location->percentage_photos = $totals['photos'] > 0
                ? round(($location->total_photos_redis / $totals['photos']) * 100, 2)
                : 0;
            $location->avg_litter_per_user = $location->total_contributors_redis > 0
                ? round($location->total_litter_redis / $location->total_contributors_redis, 2)
                : 0;
            $location->avg_photos_per_user = $location->total_contributors_redis > 0
                ? round($location->total_photos_redis / $location->total_contributors_redis, 2)
                : 0;
        }

        return [
            'locations' => $locations->items(),
            'pagination' => [
                'total' => $locations->total(),
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
            ],
            'totals' => $totals,
        ];
    }

    public function getLocationDetails(string $type, int $id)
    {
        $model = match($type) {
            'country' => Country::class,
            'state' => State::class,
            'city' => City::class,
            default => Country::class,
        };

        $location = $model::with(['creator', 'lastUploader'])->findOrFail($id);

        $location->category_breakdown = $this->getCategoryBreakdown($type, $id);
        $location->time_series = $this->getTimeSeries($type, $id);
        $location->leaderboard = $this->getLeaderboard($type, $id);

        return $location;
    }

    public function getCategoryBreakdown(string $type, int $id)
    {
        $column = match($type) {
            'country' => 'country_id',
            'state' => 'state_id',
            'city' => 'city_id',
            default => 'country_id',
        };

        return Cache::remember("$type:$id:categories", 3600, function () use ($column, $id) {
            $breakdown = DB::table('photos')
                ->join('photo_tags', 'photos.id', '=', 'photo_tags.photo_id')
                ->leftJoin('categories', 'photo_tags.category_id', '=', 'categories.id')
                ->where("photos.$column", $id)
                ->where('photos.verified', '>=', 2)
                ->select(
                    'categories.name as category',
                    DB::raw('SUM(photo_tags.quantity) as total'),
                    DB::raw('COUNT(DISTINCT photos.id) as photo_count')
                )
                ->groupBy('categories.id', 'categories.name')
                ->orderBy('total', 'desc')
                ->get();

            $total = $breakdown->sum('total');

            return $breakdown->map(function ($item) use ($total) {
                $item->percentage = $total > 0 ? round(($item->total / $total) * 100, 2) : 0;
                return $item;
            });
        });
    }

    public function getTimeSeries(string $type, int $id, string $period = 'daily')
    {
        return Cache::remember("$type:$id:timeseries:$period", 1800, function () use ($type, $id, $period) {
            return DB::table('photo_metrics')
                ->where('location_type', $type)
                ->where('location_id', $id)
                ->where('timescale', match($period) {
                    'weekly' => 1,
                    'monthly' => 2,
                    'yearly' => 3,
                    default => 0,
                })
                ->where('day', '>=', now()->subYear()->toDateString())
                ->orderBy('day')
                ->get(['day', 'uploads as photos', 'tags as litter', 'brands'])
                ->toArray();
        });
    }

    public function getLeaderboard(string $type, int $id, string $period = 'all_time')
    {
        $column = match($type) {
            'country' => 'country_id',
            'state' => 'state_id',
            'city' => 'city_id',
            default => 'country_id',
        };

        $query = DB::table('photos')
            ->join('users', 'photos.user_id', '=', 'users.id')
            ->where("photos.$column", $id)
            ->where('photos.verified', '>=', 2)
            ->select(
                'users.id',
                'users.name',
                'users.username',
                'users.show_name',
                'users.show_username',
                DB::raw('COUNT(photos.id) as photo_count'),
                DB::raw('SUM(photos.total_litter) as total_litter')
            )
            ->groupBy('users.id', 'users.name', 'users.username', 'users.show_name', 'users.show_username');

        if ($period === 'today') {
            $query->whereDate('photos.created_at', today());
        } elseif ($period === 'this_week') {
            $query->whereBetween('photos.created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'this_month') {
            $query->whereMonth('photos.created_at', now()->month);
        } elseif ($period === 'this_year') {
            $query->whereYear('photos.created_at', now()->year);
        }

        return $query->orderByDesc('total_litter')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $display = 'Anonymous';
                if ($user->show_name && $user->name) {
                    $display = $user->name;
                } elseif ($user->show_username && $user->username) {
                    $display = $user->username;
                }

                return [
                    'user_id' => $user->id,
                    'display_name' => $display,
                    'photo_count' => $user->photo_count,
                    'total_litter' => $user->total_litter,
                ];
            });
    }

    public function getGlobalStats()
    {
        return Cache::remember('global:stats', 300, function () {
            $countries = Country::where('manual_verify', true)->get();

            $totalLitter = $countries->sum('total_litter_redis');
            $totalPhotos = $countries->sum('total_photos_redis');

            $level = $this->calculateLevel($totalLitter);

            return [
                'total_litter' => $totalLitter,
                'total_photos' => $totalPhotos,
                'total_contributors' => DB::table('users')->where('verified', '>=', 2)->count(),
                'total_countries' => $countries->count(),
                'level' => $level['level'],
                'current_xp' => $totalLitter,
                'previous_xp' => $level['previous_xp'],
                'next_xp' => $level['next_xp'],
                'progress' => $level['progress'],
            ];
        });
    }

    private function calculateLevel(int $totalLitter)
    {
        $levels = [
            0 => ['min' => 0, 'max' => 1000],
            1 => ['min' => 1000, 'max' => 10000],
            2 => ['min' => 10000, 'max' => 100000],
            3 => ['min' => 100000, 'max' => 250000],
            4 => ['min' => 250000, 'max' => 500000],
            5 => ['min' => 500000, 'max' => 1000000],
        ];

        $currentLevel = 0;
        foreach ($levels as $level => $range) {
            if ($totalLitter >= $range['min'] && $totalLitter < $range['max']) {
                $currentLevel = $level;
                break;
            }
        }

        $prev = $levels[$currentLevel]['min'];
        $next = $levels[$currentLevel]['max'];

        return [
            'level' => $currentLevel,
            'previous_xp' => $prev,
            'next_xp' => $next,
            'progress' => $next > $prev ? round((($totalLitter - $prev) / ($next - $prev)) * 100, 2) : 100,
        ];
    }
}
