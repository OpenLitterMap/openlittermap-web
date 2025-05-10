<?php

namespace Tests\Feature\Timeseries;

use App\Enums\Timescale;
use App\Models\Photo;
use App\Services\Timeseries\TimeSeriesService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $sql = DB::selectOne("SHOW CREATE TABLE photo_metrics")
            ->{'Create Table'};

        $this->assertStringContainsString(
            'PARTITION BY LIST',
            $sql,
            'photo_metrics is not partitioned by timescale'
        );

        foreach (['p_daily','p_weekly','p_monthly','p_yearly'] as $part) {
            $this->assertStringContainsString($part, $sql);
        }
    }
}
