<?php

namespace App\Services\Timeseries;

use App\Enums\Timescale;
use App\Models\Photo;
use Illuminate\Support\Facades\DB;

final class TimeSeriesService
{
    /** cache increments until we flush once per chunk */
    private array $bucket = [];

    public function updateTimeSeries(Photo $photo): void
    {
        /* 1. Location dimensions */
        $locs = [['global', 0]];
        if ($photo->country_id) $locs[] = ['country', $photo->country_id];
        if ($photo->state_id)   $locs[] = ['state',   $photo->state_id];
        if ($photo->city_id)    $locs[] = ['city',    $photo->city_id];

        /* 2. Time-scales */
        $ts = $photo->created_at;
        $scales = [
            Timescale::Daily->value   => [
                'year'     => $ts->year,
                'month'    => $ts->month,
                'iso_week' => $ts->isoWeek(),
                'day'      => $ts->toDateString(),
            ],
            Timescale::Weekly->value  => [
                'year'     => $ts->year,
                'month'    => $ts->month,
                'iso_week' => $ts->isoWeek(),
                'day'      => $ts->copy()->startOfWeek()->toDateString(),
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

        /* 3. Increment buckets */
        foreach ($scales as $scaleValue => $dims) {
            foreach ($locs as [$locType, $locId]) {
                $key = $this->hash($scaleValue, $locType, $locId, $dims);

                $row =& $this->bucket[$key];

                if (!isset($row)) {
                    $row = [
                        'timescale'     => $scaleValue,   // 1-4
                        'location_type' => $locType,
                        'location_id'   => $locId,
                        'year'          => $dims['year'],
                        'month'         => $dims['month'],
                        'iso_week'      => $dims['iso_week'],
                        'day'           => $dims['day'],
                        'uploads'       => 0,
                        'tags'          => 0,
                        'brands'        => 0,
                        'updated_at'    => now(),
                    ];
                }

                $row['uploads']++;
                $row['tags']   += $photo->tags_total   ?? 0;
                $row['brands'] += $photo->brands_total ?? 0;
            }
        }
    }

    /** Flush everything that accumulated so far (call once per chunk) */
    public function flush(): void
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

        $this->bucket = []; // reset for next chunk
    }

    /* ---------------------------------------------------- */

    private function hash(int $scale, string $locType, int $locId, array $dims): string {
        return $scale.'|'.$locType.'|'.$locId.'|'
            .($dims['year'] ?? '').'|'
            .($dims['month'] ?? '').'|'
            .($dims['iso_week'] ?? '').'|'
            .($dims['day'] ?? '');
    }
}
