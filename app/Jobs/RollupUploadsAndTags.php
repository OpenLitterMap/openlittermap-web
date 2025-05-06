<?php

namespace App\Jobs;

use App\Models\Photo;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RollupUploadsAndTags implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /** @var Carbon Immutable date for the day we are rolling up */
    public function __construct(private Carbon $cursor) {}

    public function handle(): void
    {
        $from  = $this->cursor->copy()->startOfDay();
        $to    = $from->copy()->addDay();

        // ---------- GLOBAL ----------
        $g = Photo::whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) uploads, SUM(tags_total) tags')
            ->first();

        $this->upsertDaily('global', 0, $from, $g);

        // ---------- COUNTRY / STATE / CITY ----------
        foreach (['country_id','state_id','city_id'] as $col) {
            Photo::whereBetween('created_at', [$from,$to])
                ->whereNotNull($col)
                ->groupBy($col)
                ->selectRaw("$col id, COUNT(*) uploads, SUM(tags_total) tags")
                ->orderBy($col)
                ->chunk(500, function ($set) use ($col, $from) {
                    foreach ($set as $row) {
                        $this->upsertDaily(rtrim($col,'_id'), $row->id, $from, $row);
                    }
                });
        }

        // ---------- WEEK / MONTH / YEAR ----------
        app(\App\Services\PeriodAggregator::class)->extendFromDaily($from);
    }

    /* --------------------------------------------------------------- */

    private function upsertDaily(
        string $type,
        int    $id,
        Carbon $day,
               $row
    ): void {
        if (!$row->uploads) return;

        DB::table('photo_metrics_daily')->upsert(
            [[
                'location_type'=> $type,
                'location_id'  => $id,
                'day'          => $day->toDateString(),
                'uploads'      => $row->uploads,
                'tags_total'   => $row->tags ?? 0,
                'updated_at'   => now(),
            ]],
            ['location_type','location_id','day'],
            ['uploads','tags_total','updated_at']
        );
    }
}
