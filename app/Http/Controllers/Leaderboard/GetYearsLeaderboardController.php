<?php

namespace App\Http\Controllers\Leaderboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class GetYearsLeaderboardController extends Controller
{
    public function __invoke ()
    {
        $year = request('year');

        $data = Redis::get($year);

        \Log::info($year);
        \Log::info($data);


        return response()->json($data);

    }
}
