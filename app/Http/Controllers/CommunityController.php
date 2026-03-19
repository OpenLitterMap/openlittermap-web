<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Models\Users\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function stats(): array
    {
        return [
            'photosPerMonth' => $this->getPhotosPerMonth(),
            'litterTagsPerMonth' => $this->getLitterTagsPerMonth(),
            'usersPerMonth' => $this->getUsersPerMonth(),
            'statsByMonth' => Cache::remember(
                self::class . 'statsByMonth',
                // Clears cache at the start of next month
                now()->addMonth()->startOfMonth()->startOfDay(),
                function () {
                    return $this->getStatsByMonth();
                }
            )
        ];
    }

    private function getPhotosPerMonth(): int
    {
        $row = DB::table('metrics')
            ->where('timescale', 3)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first(['uploads']);

        return (int) ($row->uploads ?? 0);
    }

    private function getLitterTagsPerMonth(): int
    {
        $row = DB::table('metrics')
            ->where('timescale', 3)
            ->where('location_type', LocationType::Global->value)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first(['tags']);

        return (int) ($row->tags ?? 0);
    }

    private function getUsersPerMonth(): int
    {
        return User::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    private function getStatsByMonth(): array
    {
        // Not using models to avoid appended extra queries
        $photos = DB::table('photos')
            ->where('is_public', true)
            ->selectRaw("
                count(id) as total,
                date_format(created_at, '%b %Y') as period
            ")
            ->whereYear('created_at', '>=', 2020)
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $users = DB::table('users')
            ->selectRaw("
                count(id) as total,
                date_format(created_at, '%b %Y') as period
            ")
            ->whereYear('created_at', '>=', 2020)
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $periods = [];
        foreach (CarbonPeriod::create('2020-01-01', '1 month', now()->subMonth()) as $period) {
            $periods[] = $period->format('M Y');
        }

        $photosByMonth = [];
        $usersByMonth = [];
        foreach ($periods as $period) {
            $photosByMonth[] = $photos->get($period)->total ?? 0;
            $usersByMonth[] = $users->get($period)->total ?? 0;
        }

        return [
            'photosByMonth' => $photosByMonth,
            'usersByMonth' => $usersByMonth,
            'periods' => $periods
        ];
    }
}
