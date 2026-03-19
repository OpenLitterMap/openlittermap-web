<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Dashboard stats for the admin panel.
     *
     * Cached for 60 seconds to keep the query load low.
     */
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember('admin:dashboard:stats', 60, function () {
            $today = now()->startOfDay();

            // Queue totals — public photos awaiting admin approval
            $queueTotal = Photo::where('is_public', true)
                ->where('verified', VerificationStatus::VERIFIED->value)
                ->whereNotNull('summary')
                ->count();

            $queueToday = Photo::where('is_public', true)
                ->where('verified', VerificationStatus::VERIFIED->value)
                ->whereNotNull('summary')
                ->where('created_at', '>=', $today)
                ->count();

            // By verification status
            $byVerification = Photo::where('is_public', true)
                ->whereNotNull('summary')
                ->select('verified', DB::raw('COUNT(*) as count'))
                ->groupBy('verified')
                ->pluck('count', 'verified')
                ->mapWithKeys(function ($count, $status) {
                    $enum = VerificationStatus::tryFrom($status);

                    return [$enum ? $enum->label() : "status_{$status}" => $count];
                })
                ->all();

            // Top 20 countries by pending photos
            $byCountry = Photo::where('is_public', true)
                ->where('verified', VerificationStatus::VERIFIED->value)
                ->whereNotNull('summary')
                ->join('countries', 'photos.country_id', '=', 'countries.id')
                ->select('countries.country', DB::raw('COUNT(*) as count'))
                ->groupBy('countries.country')
                ->orderByDesc('count')
                ->limit(20)
                ->pluck('count', 'country')
                ->all();

            // Users
            $totalUsers = User::count();
            $usersToday = User::where('created_at', '>=', $today)->count();
            $flaggedUsernames = User::where('username_flagged', true)->count();

            return [
                'queue_total' => $queueTotal,
                'queue_today' => $queueToday,
                'by_verification' => $byVerification,
                'by_country' => $byCountry,
                'total_users' => $totalUsers,
                'users_today' => $usersToday,
                'flagged_usernames' => $flaggedUsernames,
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }
}
