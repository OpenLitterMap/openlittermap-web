<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PeriodAggregator
{
    /**
     * Roll DAILY rows into WEEKLY + MONTHLY + YEARLY.
     */
    public function extendFromDaily(Carbon $day): void
    {
        [$y, $m, $w] = [$day->year, $day->month, $day->isoWeek()];

        // WEEKLY
        $sum = DB::table('photo_metrics_daily')
            ->where('day','>=',$day->copy()->startOfWeek())
            ->where('day','<=',$day->copy()->endOfWeek())
            ->selectRaw('location_type, location_id,
                              SUM(uploads) u, SUM(tags_total) t')
            ->groupBy('location_type','location_id')
            ->get();

        foreach ($sum as $r) {
            DB::table('photo_metrics_weekly')->upsert([[
                'location_type'=> $r->location_type,
                'location_id'  => $r->location_id,
                'year'         => $y,
                'iso_week'     => $w,
                'uploads'      => $r->u,
                'tags_total'   => $r->t,
                'updated_at'   => now(),
            ]], ['location_type','location_id','year','iso_week'],
                ['uploads','tags_total','updated_at']);
        }

        // MONTHLY + YEARLY follow the same pattern -------------------
        // MONTHLY
        $sum = DB::table('photo_metrics_daily')
            ->whereYear('day', $y)->whereMonth('day', $m)
            ->selectRaw('location_type, location_id,
                              SUM(uploads) u, SUM(tags_total) t')
            ->groupBy('location_type','location_id')
            ->get();

        foreach ($sum as $r) {
            DB::table('photo_metrics_monthly')->upsert([[
                'location_type'=> $r->location_type,
                'location_id'  => $r->location_id,
                'year'         => $y,
                'month'        => $m,
                'uploads'      => $r->u,
                'tags_total'   => $r->t,
                'updated_at'   => now(),
            ]], ['location_type','location_id','year','month'],
                ['uploads','tags_total','updated_at']);
        }

        // YEARLY
        $sum = DB::table('photo_metrics_daily')
            ->whereYear('day', $y)
            ->selectRaw('location_type, location_id,
                              SUM(uploads) u, SUM(tags_total) t')
            ->groupBy('location_type','location_id')
            ->get();

        foreach ($sum as $r) {
            DB::table('photo_metrics_yearly')->upsert([[
                'location_type'=> $r->location_type,
                'location_id'  => $r->location_id,
                'year'         => $y,
                'uploads'      => $r->u,
                'tags_total'   => $r->t,
                'updated_at'   => now(),
            ]], ['location_type','location_id','year'],
                ['uploads','tags_total','updated_at']);
        }
    }
}
