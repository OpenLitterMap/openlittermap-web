<?php

namespace Tests\Feature\Timeseries;

use App\Repositories\PhotoMetricsRepo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhotoMetricsRepoTest extends TestCase
{
    use RefreshDatabase;

    private PhotoMetricsRepo $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(PhotoMetricsRepo::class);

        // cut noise from other queries so we can count them
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    /* ---------------------------------------------------------------------
     * 1.  Basic caching behaviour
     * ------------------------------------------------------------------- */

    /** @test */
    public function it_hits_the_database_once_then_serves_from_cache(): void
    {
        // Seed a single daily row.
        DB::table('photo_metrics')->insert([
            'timescale'     => 1,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => 2025,
            'month'         => 5,
            'iso_week'      => 19,
            'day'           => '2025-05-10',
            'uploads'       => 1,
            'tags'          => 0,
            'brands'        => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Remove the INSERT from the log; we only want to count SELECTs.
        DB::flushQueryLog();

        // First call: cache miss → DB hit.
        $row1 = $this->repo->daily('global', 0, '2025-05-10');

        // Second call: same key, should be a cache hit → no new SQL.
        $row2 = $this->repo->daily('global', 0, '2025-05-10');

        $this->assertEquals($row1, $row2, 'Cache should return identical object');

        // exactly *one* query ran
        $this->assertCount(1, DB::getQueryLog(), 'Second fetch should not query DB');
    }

    /** @test */
    public function it_caches_null_results_too(): void
    {
        Cache::flush();
        DB::flushQueryLog();

        // Ask for a row that doesn’t exist.
        $miss1 = $this->repo->daily('global', 0, '2099-01-01');
        $this->assertNull($miss1);

        // Second call must NOT hit DB again.
        DB::flushQueryLog();
        $miss2 = $this->repo->daily('global', 0, '2099-01-01');
        $this->assertNull($miss2);
        $this->assertCount(0, DB::getQueryLog(), 'Null result should be cached');
    }

    /* ---------------------------------------------------------------------
     * 2.  Invalidation helper
     * ------------------------------------------------------------------- */

    /** @test */
    public function cache_key_helper_allows_manual_eviction(): void
    {
        $row = [
            'timescale'     => 1,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => 2025,
            'month'         => 5,
            'iso_week'      => 19,
            'day'           => '2025-05-10',
            'uploads'       => 1,
            'tags'          => 0,
            'brands'        => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
        DB::table('photo_metrics')->insert($row);

        // Warm the cache…
        $this->repo->daily('global', 0, '2025-05-10');

        // …then evict exactly that key.
        Cache::tags('timeseries')->forget(PhotoMetricsRepo::cacheKeyFromRow($row));

        // Next call should touch DB again → new query appears.
        DB::flushQueryLog();
        $this->repo->daily('global', 0, '2025-05-10');
        $this->assertCount(1, DB::getQueryLog(), 'Evicted key should trigger new DB query');
    }

    /* ---------------------------------------------------------------------
     * 3.  Helper methods daily / weekly / monthly / yearly
     * ------------------------------------------------------------------- */

    /** @test */
    public function weekly_helper_maps_to_correct_primary_key(): void
    {
        // Monday that begins ISO week 22 of 2025 is 2025-05-26.
        $weekMon = Carbon::create(2025, 5, 26)->startOfWeek();
        $row = [
            'timescale'     => 2,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => $weekMon->isoWeekYear(),        // 2025
            'month'         => $weekMon->month,                // May
            'iso_week'      => $weekMon->isoWeek(),            // 22
            'day'           => $weekMon->toDateString(),       // 2025-05-26
            'uploads'       => 3,
            'tags'          => 4,
            'brands'        => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
        DB::table('photo_metrics')->insert($row);

        $found = $this->repo->weekly(
            'global',
            0,
            $weekMon->isoWeekYear(),
            $weekMon->isoWeek()
        );

        $this->assertNotNull($found);
        $this->assertSame(3, $found->uploads);
    }

    /** @test */
    public function monthly_and_yearly_helpers_return_expected_rows(): void
    {
        DB::table('photo_metrics')->insert([
            // monthly bucket: July 2024
            'timescale'     => 3,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => 2024,
            'month'         => 7,
            'iso_week'      => 0,
            'day'           => '2024-07-01',
            'uploads'       => 2,
            'tags'          => 5,
            'brands'        => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('photo_metrics')->insert([
            // yearly bucket: 2023
            'timescale'     => 4,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => 2023,
            'month'         => 0,
            'iso_week'      => 0,
            'day'           => '2023-01-01',
            'uploads'       => 10,
            'tags'          => 20,
            'brands'        => 5,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $m = $this->repo->monthly('global', 0, 2024, 7);
        $this->assertSame(5, $m->tags);

        $y = $this->repo->yearly('global', 0, 2023);
        $this->assertSame(10, $y->uploads);
    }
}
