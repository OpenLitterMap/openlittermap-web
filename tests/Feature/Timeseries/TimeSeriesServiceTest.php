<?php

namespace Tests\Feature\Timeseries;

use App\Enums\Timescale;
use App\Models\Photo;
use App\Repositories\PhotoMetricsRepo;
use App\Services\Timeseries\TimeSeriesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimeSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimeSeriesService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('countries')->insert(['id' => 1, 'created_at' => now(),'updated_at' => now()]);
        DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id'=>10, 'created_at' => now(),'updated_at' => now()]);

        $this->svc = app(TimeSeriesService::class);
    }

    /** @test */
    public function it_writes_four_timescales_for_each_location(): void
    {
        $photo = Photo::factory()->create([
            'created_at' => Carbon::create(2025, 5, 10, 12),
            'country_id' => 1,
            'state_id'   => 10,
            'city_id'    => 100,
        ]);
        $photo->total_tags   = 3;
        $photo->total_brands = 1;

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertDatabaseCount('photo_metrics', 16);

        $this->assertDatabaseHas('photo_metrics', [
            'timescale'     => Timescale::Daily->value,
            'location_type' => 'city',
            'location_id'   => 100,
            'day'           => '2025-05-10',
            'uploads'       => 1,
            'tags'          => 3,
            'brands'        => 1,
        ]);
    }

    /** @test */
    public function second_photo_increments_existing_row_not_overwrites(): void
    {
        $ts = Carbon::create(2025, 5, 11, 7);
        $p1 = Photo::factory()->create(['created_at' => $ts]);
        $p1->total_tags = 2;

        $p2 = Photo::factory()->create(['created_at' => $ts]);
        $p2->total_tags = 5;

        $this->svc->updateTimeSeries($p1);
        $this->svc->updateTimeSeries($p2);
        $this->svc->flush();

        $this->assertDatabaseHas('photo_metrics', [
            'timescale'     => Timescale::Daily->value,
            'location_type' => 'global',
            'location_id'   => 0,
            'day'           => '2025-05-11',
            'uploads'       => 2,
            'tags'          => 7,
        ]);
    }

    /** @test */
    public function bucket_is_empty_after_flush(): void
    {
        $photo = Photo::factory()->create();
        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $ref = new \ReflectionClass($this->svc);
        $bucket = $ref->getProperty('bucket');
        $bucket->setAccessible(true);

        $this->assertEmpty($bucket->getValue($this->svc));
    }

    /** @test */
    public function the_partitioning_rules_match_the_primary_key(): void
    {
        $sql = DB::selectOne("SHOW CREATE TABLE photo_metrics")->{'Create Table'};

        $this->assertStringContainsString(
            'PARTITION BY LIST',
            $sql,
            'photo_metrics is not partitioned by timescale'
        );

        foreach (['p_daily','p_weekly','p_monthly','p_yearly'] as $part) {
            $this->assertStringContainsString($part, $sql);
        }
    }

    /** @test */
    public function weekly_bucket_uses_month_of_week_monday(): void
    {
        // 1 Jun 2025 is a Sunday; ISO-week 22 starts on Mon 26 May 2025 (month = 5)
        $photo = Photo::factory()->create([
            'created_at' => Carbon::create(2025, 6, 1, 12),
        ]);

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertDatabaseHas('photo_metrics', [
            'timescale'   => Timescale::Weekly->value,
            'location_id' => 0,
            'year'        => 2025,
            'month'       => 5,                   // May, not June
            'iso_week'    => $photo->created_at->isoWeek(),
            'day'         => '2025-05-26',        // Monday that begins the week
        ]);
    }

    /** @test */
    public function weekly_bucket_uses_iso_week_year_across_year_boundary(): void
    {
        // 31 Dec 2025 belongs to ISO-week 1 of 2026
        $photo = Photo::factory()->create([
            'created_at' => Carbon::create(2025, 12, 31, 10),
        ]);

        $isoYear = $photo->created_at->isoWeekYear();   // 2026
        $weekMon = $photo->created_at->copy()->startOfWeek();

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertDatabaseHas('photo_metrics', [
            'timescale' => Timescale::Weekly->value,
            'year'      => $isoYear,                    // 2026
            'iso_week'  => $photo->created_at->isoWeek(),
            'day'       => $weekMon->toDateString(),
        ]);
    }

    /** @test */
    public function created_at_is_set_on_insert(): void
    {
        $photo = Photo::factory()->create();

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $row = DB::table('photo_metrics')->first();
        $this->assertNotNull($row->created_at);
    }

    /** @test */
    public function tags_and_brands_default_to_zero(): void
    {
        $photo = Photo::factory()->create();   // no totals set

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertDatabaseHas('photo_metrics', [
            'timescale' => Timescale::Daily,
            'uploads'   => 1,
            'tags'      => 0,
            'brands'    => 0,
        ]);
    }

    /** @test */
    public function separate_flushes_still_increment_the_same_row(): void
    {
        $ts = Carbon::create(2025, 7, 4, 12);
        [$p1, $p2] = Photo::factory()->count(2)->create(['created_at' => $ts]);

        $this->svc->updateTimeSeries($p1);
        $this->svc->flush();          // first chunk

        $this->svc->updateTimeSeries($p2);
        $this->svc->flush();          // next chunk

        $this->assertDatabaseHas('photo_metrics', [
            'timescale' => Timescale::Daily,
            'day'       => '2025-07-04',
            'uploads'   => 2,
        ]);
    }

    /** @test */
    public function flush_invalidates_the_bucket_cache_key(): void
    {
        $ts    = Carbon::create(2025, 8, 1, 9);
        $photo = Photo::factory()->create(['created_at' => $ts]);

        // Manually compute the bucket key for the daily/global/0 row
        $dims = [
            'timescale'     => Timescale::Daily->value,
            'location_type' => 'global',
            'location_id'   => 0,
            'year'          => $ts->year,
            'month'         => $ts->month,
            'iso_week'      => $ts->isoWeek(),
            'day'           => $ts->toDateString(),
        ];
        $bucketKey = PhotoMetricsRepo::bucketCacheKey($dims);

        // Seed the cache
        Cache::tags('timeseries')->put($bucketKey, (object)['uploads' => 999], now()->addDay());
        $this->assertNotNull(Cache::tags('timeseries')->get($bucketKey));

        // Trigger an update + flush → should forget that exact key
        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertNull(Cache::tags('timeseries')->get($bucketKey), 'Bucket cache key must be invalidated');
    }

    /** @test */
    public function flush_invalidates_daily_series_cache_for_recent_data(): void
    {
        $ts    = Carbon::create(2025, 8, 2, 10);
        $photo = Photo::factory()->create(['created_at' => $ts]);

        // Compute the "last-year daily series" key for global/0
        $seriesKey = PhotoMetricsRepo::dailySeriesKey('global', 0);

        // Seed the series cache
        Cache::tags('timeseries')->put($seriesKey, collect([/* dummy */]), now()->addDay());
        $this->assertNotNull(Cache::tags('timeseries')->get($seriesKey));

        // Update + flush should drop the series
        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertNull(Cache::tags('timeseries')->get($seriesKey), 'Daily series cache key must be invalidated for recent daily rows');
    }

    /** @test */
    public function flush_does_not_invalidate_daily_series_cache_for_old_data(): void
    {
        // A photo older than one year (should NOT clear the last-year series)
        $ts    = Carbon::today()->subYears(2);
        $photo = Photo::factory()->create(['created_at' => $ts]);

        $seriesKey = PhotoMetricsRepo::dailySeriesKey('global', 0);
        Cache::tags('timeseries')->put($seriesKey, collect(['keep me']), now()->addDay());
        $this->assertNotNull(Cache::tags('timeseries')->get($seriesKey));

        $this->svc->updateTimeSeries($photo);
        $this->svc->flush();

        $this->assertNotNull(Cache::tags('timeseries')->get($seriesKey), 'Daily series cache key should survive flush for old daily rows');
    }
}
