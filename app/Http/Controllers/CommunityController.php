<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function stats(): array
    {
        return [
            'photosPerDay' => $this->getPhotosPerDay(),
            'usersPerWeek' => $this->getUsersPerWeek(),
            'littercoinPerMonth' => 100,
            'statsByMonth' => $this->getStatsByMonth()
        ];
    }

    private function getPhotosPerDay(): int
    {
        $total = Photo::query()
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->count();

        return round($total / 30);
    }

    private function getUsersPerWeek(): int
    {
        $total = User::query()
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->count();

        return round($total / 4);
    }

    private function getStatsByMonth(): array
    {
        // Not using models to avoid appended extra queries
        $photosByMonth = DB::table('photos')
            ->selectRaw("
                count(id) as total,
                date_format(created_at, '%b %Y') as period
            ")
            ->orderBy('created_at')
            ->groupBy('period')
            ->get();

        $usersByMonth = DB::table('users')
            ->selectRaw("
                count(id) as total,
                date_format(created_at, '%b %Y') as period
            ")
            ->orderBy('created_at')
            ->groupBy('period')
            ->get();

        $periods = $photosByMonth->pluck('period')->merge(
            $usersByMonth->pluck('period')
        )->unique();

        return compact('photosByMonth', 'usersByMonth', 'periods');
    }
}
