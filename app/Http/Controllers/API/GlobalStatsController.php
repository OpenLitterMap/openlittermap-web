<?php

namespace App\Http\Controllers\API;

use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Models\Users\User;
use Illuminate\Http\JsonResponse;
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
        $row = DB::table('metrics')
            ->where('timescale', 0)
            ->where('location_type', LocationType::Global)
            ->where('location_id', 0)
            ->where('user_id', 0)
            ->first(['uploads', 'tags']);

        $now = now('UTC');

        return response()->json([
            'total_tags' => (int) ($row->tags ?? 0),
            'total_images' => (int) ($row->uploads ?? 0),
            'total_users' => User::count(),
            'new_users_today' => User::whereDate('created_at', $now->toDateString())->count(),
            'new_users_last_7_days' => User::where('created_at', '>=', $now->copy()->subDays(7)->startOfDay())->count(),
            'new_users_last_30_days' => User::where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())->count(),
        ]);
    }
}
