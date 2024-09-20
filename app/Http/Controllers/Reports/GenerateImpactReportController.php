<?php

namespace App\Http\Controllers\Reports;

use Carbon\Carbon;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\View\View;
use App\Models\Location\Country;
use App\Http\Controllers\Controller;

class GenerateImpactReportController extends Controller
{
    /**
     * Generate a weekly impact report
     */
    public function __invoke (): View
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        // Mon 1st Sept 2024 - Sun 7th Sept 2024
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

        return view('reports.impact', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'newUsers' => $newUsers,
            'totalUsers' => $totalUsers,
            'newPhotos' => $newPhotos,
            'totalPhotos' => $totalPhotos,
            'newTags' => $newTags,
            'totalTags' => $totalTags,
        ]);
    }
}
