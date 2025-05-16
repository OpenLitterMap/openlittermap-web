<?php

namespace App\Services\Timeseries;

use App\Enums\Timescale;
use App\Models\Photo;
use App\Repositories\PhotoMetricsRepo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class TimeSeriesService
{
    /** cache increments until we flush once per chunk */
    private array $bucket = [];

    public function updateTimeSeries(Photo $photo): void
    {
        $this->buildData($photo);
        $this->persistData();
    }

    /* ------------------------------------------------------------------ */
    /* 1. Build one row for each (timescale × location) bucket            */
    /* ------------------------------------------------------------------ */

    private function buildData(Photo $photo): void
    {
        /* ─ location dimension ─ */
        $locs = [['global', 0]];
        if ($photo->country_id) $locs[] = ['country', $photo->country_id];
        if ($photo->state_id)   $locs[] = ['state',   $photo->state_id];
        if ($photo->city_id)    $locs[] = ['city',    $photo->city_id];

        /* ─ timestamp helpers ─ */
        $ts        = $photo->created_at;
        $isoWeek   = $ts->isoWeek();         // 1-53
        $isoYear   = $ts->isoWeekYear();     // may differ on 29-31 Dec & 1-3 Jan
        $weekStart = $ts->copy()->startOfWeek();   // always Monday (ISO-8601)

        /* ─ four scales ─ */
        $scales = [
            Timescale::Daily->value   => [
                'year'     => $ts->year,
                'month'    => $ts->month,
                'iso_week' => $isoWeek,
                'day'      => $ts->toDateString(),
            ],
            Timescale::Weekly->value  => [
                'year'     => $isoYear,          // ← ISO week-year
                'month'    => $weekStart->month, // month that Monday falls in
                'iso_week' => $isoWeek,
                'day'      => $weekStart->toDateString(),
            ],
            Timescale::Monthly->value => [
                'year'     => $ts->year,
                'month'    => $ts->month,
                'iso_week' => 0,
                'day'      => $ts->copy()->startOfMonth()->toDateString(),
            ],
            Timescale::Yearly->value  => [
                'year'     => $ts->year,
                'month'    => 0,
                'iso_week' => 0,
                'day'      => $ts->copy()->startOfYear()->toDateString(),
            ],
        ];

        /* ─ aggregate into the in-memory bucket ─ */
        foreach ($scales as $scale => $dims) {
            foreach ($locs as [$locType, $locId]) {
                $key = $this->hash($scale, $locType, $locId, $dims);

                $row =& $this->bucket[$key];

                if (!isset($row)) {
                    $row = [
                        'timescale'     => $scale,
                        'location_type' => $locType,
                        'location_id'   => $locId,
                        'year'          => $dims['year'],
                        'month'         => $dims['month'],
                        'iso_week'      => $dims['iso_week'],
                        'day'           => $dims['day'],
                        'uploads'       => 0,
                        'tags'          => 0,
                        'brands'        => 0,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                $row['uploads']++;
                $row['tags']   += $photo->total_tags   ?? 0;
                $row['brands'] += $photo->total_brands ?? 0;
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* 2. Flush the bucket to MySQL and invalidate Redis                  */
    /* ------------------------------------------------------------------ */

    private function persistData(): void
    {
        if (empty($this->bucket)) {
            return;
        }

        DB::table('photo_metrics')->upsert(
            array_values($this->bucket),
            ['timescale','location_type','location_id','year','month','iso_week','day'],
            [
                'uploads'    => DB::raw('uploads + VALUES(uploads)'),
                'tags'       => DB::raw('tags    + VALUES(tags)'),
                'brands'     => DB::raw('brands  + VALUES(brands)'),
                'updated_at' => DB::raw('VALUES(updated_at)'),
            ]
        );

        /* ─ redis invalidation ─ */
        foreach ($this->bucket as $row) {
            Cache::tags('timeseries')->forget(PhotoMetricsRepo::bucketCacheKey($row));

            if (
                $row['timescale'] === Timescale::Daily->value &&
                Carbon::parse($row['day'])->gte(now()->subYear())
            ) {
                Cache::tags('timeseries')->forget(
                    PhotoMetricsRepo::dailySeriesKey($row['location_type'], $row['location_id'])
                );
            }
        }

        $this->bucket = [];
    }

    /* ------------------------------------------------------------------ */
    /*  helpers                                                           */
    /* ------------------------------------------------------------------ */

    private function hash(int $scale, string $locType, int $locId, array $d): string
    {
        return "$scale|$locType|$locId|{$d['year']}|{$d['month']}|{$d['iso_week']}|{$d['day']}";
    }
}
