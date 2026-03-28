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
            $today = $now->toDateString();
            $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay()->toDateString();
            $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay()->toDateString();

            $growth = DB::table('metrics')
                ->where('timescale', 1)
                ->where('location_type', LocationType::Global->value)
                ->where('location_id', 0)
                ->where('user_id', 0)
                ->where('bucket_date', '>=', $thirtyDaysAgo)
                ->selectRaw(
                    'SUM(CASE WHEN bucket_date = ? THEN tags ELSE 0 END) as new_tags_today,
                     SUM(CASE WHEN bucket_date >= ? THEN tags ELSE 0 END) as new_tags_7d,
                     SUM(tags) as new_tags_30d,
                     SUM(CASE WHEN bucket_date = ? THEN uploads ELSE 0 END) as new_photos_today,
                     SUM(CASE WHEN bucket_date >= ? THEN uploads ELSE 0 END) as new_photos_7d,
                     SUM(uploads) as new_photos_30d',
                    [$today, $sevenDaysAgo, $today, $sevenDaysAgo]
                )
                ->first();

            return [
                'total_tags' => (int) ($row->tags ?? 0),
                'total_images' => (int) ($row->uploads ?? 0),
                'total_users' => User::count(),
                'new_users_today' => User::whereDate('created_at', $today)->count(),
                'new_users_last_7_days' => User::where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())->count(),
                'new_users_last_30_days' => User::where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())->count(),
                'new_tags_today' => (int) ($growth->new_tags_today ?? 0),
                'new_tags_last_7_days' => (int) ($growth->new_tags_7d ?? 0),
                'new_tags_last_30_days' => (int) ($growth->new_tags_30d ?? 0),
                'new_photos_today' => (int) ($growth->new_photos_today ?? 0),
                'new_photos_last_7_days' => (int) ($growth->new_photos_7d ?? 0),
                'new_photos_last_30_days' => (int) ($growth->new_photos_30d ?? 0),
            ];
        });

        return response()->json($data);
    }
}
