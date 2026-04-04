<?php

namespace App\Http\Controllers\API;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GlobalStatsController extends Controller
{
    /**
     * GET /api/global/stats-data
     *
     * Returns world totals: total_tags, total_images, total_users.
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('global:stats-data', 300, function () {
            $row = DB::table('metrics')
                ->where('timescale', 0)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->first(['uploads', 'tags']);

            $now = now('UTC');
            $oneDayAgo = $now->copy()->subHours(24)->toDateString();
            $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay()->toDateString();
            $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay()->toDateString();

            $growth = DB::table('metrics')
                ->where('timescale', 1)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->where('bucket_date', '>=', $thirtyDaysAgo)
                ->selectRaw(
                    'SUM(CASE WHEN bucket_date >= ? THEN tags ELSE 0 END) as new_tags_24h,
                     SUM(CASE WHEN bucket_date >= ? THEN tags ELSE 0 END) as new_tags_7d,
                     SUM(tags) as new_tags_30d,
                     SUM(CASE WHEN bucket_date >= ? THEN uploads ELSE 0 END) as new_photos_24h,
                     SUM(CASE WHEN bucket_date >= ? THEN uploads ELSE 0 END) as new_photos_7d,
                     SUM(uploads) as new_photos_30d',
                    [$oneDayAgo, $sevenDaysAgo, $oneDayAgo, $sevenDaysAgo]
                )
                ->first();

            $usersLast24h = User::where('created_at', '>=', $now->copy()->subHours(24))->count();
            $usersLast7d  = User::where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())->count();
            $usersLast30d = User::where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())->count();
            $tagsLast24h  = (int) ($growth->new_tags_24h ?? 0);
            $tagsLast7d   = (int) ($growth->new_tags_7d ?? 0);
            $tagsLast30d  = (int) ($growth->new_tags_30d ?? 0);
            $photosLast24h = (int) ($growth->new_photos_24h ?? 0);
            $photosLast7d  = (int) ($growth->new_photos_7d ?? 0);
            $photosLast30d = (int) ($growth->new_photos_30d ?? 0);

            return [
                'total_tags' => (int) ($row->tags ?? 0),
                'total_images' => (int) ($row->uploads ?? 0),
                'total_users' => User::count(),

                // New keys (v5.7+)
                'new_users_last_24_hours' => $usersLast24h,
                'new_users_last_7_days' => $usersLast7d,
                'new_users_last_30_days' => $usersLast30d,
                'new_tags_last_24_hours' => $tagsLast24h,
                'new_tags_last_7_days' => $tagsLast7d,
                'new_tags_last_30_days' => $tagsLast30d,
                'new_photos_last_24_hours' => $photosLast24h,
                'new_photos_last_7_days' => $photosLast7d,
                'new_photos_last_30_days' => $photosLast30d,

                // Legacy keys (pre-v5.7 mobile compat)
                'new_users_today' => $usersLast24h,
                'new_tags_today' => $tagsLast24h,
                'new_photos_today' => $photosLast24h,
            ];
        });

        return response()->json($data);
    }
}
