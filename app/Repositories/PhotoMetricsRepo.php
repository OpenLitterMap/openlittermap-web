<?php

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only access to the photo_metrics table with a Redis read-through cache.
 *
 *  • All queries first try Redis (tag: timeseries) → MySQL → Redis set.
 *  • Each cached entry lives for 7 days (see TTL_DAYS).
 *  • TimeSeriesService::flush() should call
 *        Cache::tags('timeseries')
 *             ->forget(PhotoMetricsRepo::cacheKeyFromRow($row));
 *    for every bucket it upserts, so hot rows stay coherent.
 */
class PhotoMetricsRepo
{
    /** Cache lifetime for a single bucket row (in days). */
    public const TTL_DAYS = 7;

    /** Value stored in Redis to represent a “null” DB result. */
    private const NULL_SENTINEL = '__NULL__';

    /* --------------------------------------------------------------------- */
    /*  Public API                                                           */
    /* --------------------------------------------------------------------- */

    /**
     * Generic fetch using the exact composite primary key.
     *
     * @return object|null  stdClass row (same shape as DB) or null if absent
     */
    public function find(
        int    $timescale,
        string $locationType,
        int    $locationId,
        int    $year,
        int    $month,
        int    $isoWeek,
        string $day
    ): ?object {
        $pk = compact(
            'timescale',
            'locationType',
            'locationId',
            'year',
            'month',
            'isoWeek',
            'day'
        );

        // ── read-through cache ───────────────────────────────────────────
        $value = Cache::tags(['timeseries'])
            ->remember(
                self::cacheKey($pk),
                now()->addDays(self::TTL_DAYS),
                // Store a sentinel so even “miss” is cached
                fn () => $this->queryRow($pk) ?? self::NULL_SENTINEL
            );

        // Convert sentinel back to real null for caller
        return $value === self::NULL_SENTINEL ? null : $value;
    }

    /* --- Convenience helpers for callers that think in dates not PKs ----- */

    public function daily(string $locType, int $locId, string $day): ?object
    {
        [$year, $month] = array_map('intval', explode('-', $day, 3));
        $isoWeek        = (int) date('W', strtotime($day));

        return $this->find(1, $locType, $locId, $year, $month, $isoWeek, $day);
    }

    public function weekly(string $locType, int $locId, int $isoYear, int $isoWeek): ?object
    {
        $weekStart = Carbon::now()->setISODate($isoYear, $isoWeek)->startOfWeek();

        return $this->find(
            2,
            $locType,
            $locId,
            $isoYear,
            $weekStart->month,
            $isoWeek,
            $weekStart->toDateString()
        );
    }

    public function monthly(string $locType, int $locId, int $year, int $month): ?object
    {
        $day = sprintf('%04d-%02d-01', $year, $month);

        return $this->find(3, $locType, $locId, $year, $month, 0, $day);
    }

    public function yearly(string $locType, int $locId, int $year): ?object
    {
        return $this->find(4, $locType, $locId, $year, 0, 0, $year . '-01-01');
    }

    /* --------------------------------------------------------------------- */
    /*  Invalidation helper (used by TimeSeriesService::flush)               */
    /* --------------------------------------------------------------------- */

    public static function cacheKeyFromRow(array $row): string
    {
        return 'ts:' .
            $row['timescale']     . ':' .
            $row['location_type'] . ':' .
            $row['location_id']   . ':' .
            $row['year']          . ':' .
            $row['month']         . ':' .
            $row['iso_week']      . ':' .
            $row['day'];
    }

    /* --------------------------------------------------------------------- */
    /*  Internals                                                            */
    /* --------------------------------------------------------------------- */

    private static function cacheKey(array $pk): string
    {
        return 'ts:' .
            $pk['timescale']   . ':' .
            $pk['locationType']. ':' .
            $pk['locationId']  . ':' .
            $pk['year']        . ':' .
            $pk['month']       . ':' .
            $pk['isoWeek']     . ':' .
            $pk['day'];
    }

    /** Perform the actual SELECT (used only on a cache miss). */
    private function queryRow(array $pk): ?object
    {
        return DB::table('photo_metrics')
            ->where([
                'timescale'     => $pk['timescale'],
                'location_type' => $pk['locationType'],
                'location_id'   => $pk['locationId'],
                'year'          => $pk['year'],
                'month'         => $pk['month'],
                'iso_week'      => $pk['isoWeek'],
                'day'           => $pk['day'],
            ])
            ->first();
    }
}
