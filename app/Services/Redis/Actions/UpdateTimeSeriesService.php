<?php

namespace App\Services\Redis\Actions;

use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class UpdateTimeSeriesService
{
    /**
     * Time-series remains largely the same, using date‐buckets:
     *   <scope>:ts:daily:photos:<YYYY-MM-DD>
     *   <scope>:ts:weekly:photos:<YYYY-WW>
     *   <scope>:ts:monthly:photos:<YYYY-MM>
     *   <scope>:ts:yearly:photos:<YYYY>
     */
    public function run(Photo $photo): void
    {
        $ts    = $photo->created_at;
        $date  = $ts->format('Y-m-d');
        $week  = $ts->format('o-W');
        $month = $ts->format('Y-m');
        $year  = $ts->format('Y');

        $scopes = [
            'global'  => 'global',
            'country' => "country:{$photo->country->id}",
            'state'   => "state:{$photo->state->id}",
            'city'    => "city:{$photo->city->id}",
            'user'    => "user:{$photo->user->id}",
        ];

        Redis::pipeline(function ($pipe) use ($scopes, $date, $week, $month, $year) {
            foreach ($scopes as $scopeKey) {
                $pipe->incr("{$scopeKey}:ts:daily:photos:{$date}");
                $pipe->incr("{$scopeKey}:ts:weekly:photos:{$week}");
                $pipe->incr("{$scopeKey}:ts:monthly:photos:{$month}");
                $pipe->incr("{$scopeKey}:ts:yearly:photos:{$year}");
            }
        });
    }
}
