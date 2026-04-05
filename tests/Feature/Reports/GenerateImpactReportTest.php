<?php

namespace Tests\Feature\Reports;

use App\Enums\LocationType;
use App\Enums\Timescale;
use App\Models\Photo;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateImpactReportTest extends TestCase
{
    use RefreshDatabase;

    // ─── Weekly ──────────────────────────────────────────────────────

    public function test_weekly_report_renders_with_metrics(): void
    {
        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $this->insertMetrics(Timescale::Weekly, $lastWeek, ['uploads' => 25, 'tags' => 100]);
        $this->insertAllTimeMetrics(['uploads' => 5000, 'tags' => 20000]);

        $response = $this->get("/impact/weekly/{$isoYear}/{$isoWeek}");

        $response->assertOk();
        $response->assertViewIs('reports.impact');
        $response->assertViewHas('period', 'weekly');
        $response->assertViewHas('newPhotos', 25);
        $response->assertViewHas('newTags', 100);
    }

    // ─── Monthly ─────────────────────────────────────────────────────

    public function test_monthly_report_renders_with_metrics(): void
    {
        $lastMonth = now()->subMonth();

        $this->insertMetrics(Timescale::Monthly, $lastMonth, ['uploads' => 200, 'tags' => 800]);
        $this->insertAllTimeMetrics(['uploads' => 5000, 'tags' => 20000]);

        $response = $this->get("/impact/monthly/{$lastMonth->year}/{$lastMonth->month}");

        $response->assertOk();
        $response->assertViewIs('reports.impact');
        $response->assertViewHas('period', 'monthly');
        $response->assertViewHas('newPhotos', 200);
        $response->assertViewHas('newTags', 800);
    }

    // ─── Annual ──────────────────────────────────────────────────────

    public function test_annual_report_renders_with_metrics(): void
    {
        $lastYear = now()->subYear()->year;

        $this->insertMetrics(Timescale::Yearly, Carbon::createFromDate($lastYear, 1, 1), ['uploads' => 1000, 'tags' => 5000]);
        $this->insertAllTimeMetrics(['uploads' => 10000, 'tags' => 50000]);

        $response = $this->get("/impact/annual/{$lastYear}");

        $response->assertOk();
        $response->assertViewIs('reports.impact');
        $response->assertViewHas('period', 'annual');
        $response->assertViewHas('newPhotos', 1000);
        $response->assertViewHas('newTags', 5000);
    }

    public function test_annual_report_shows_year_in_date(): void
    {
        $lastYear = now()->subYear()->year;

        $this->insertMetrics(Timescale::Yearly, Carbon::createFromDate($lastYear, 1, 1), ['uploads' => 0, 'tags' => 0]);
        $this->insertAllTimeMetrics();

        $response = $this->get("/impact/annual/{$lastYear}");

        $response->assertOk();
        $response->assertViewHas('startDate', (string) $lastYear);
    }

    // ─── Edge cases ──────────────────────────────────────────────────

    public function test_future_date_returns_not_found(): void
    {
        $futureYear = now()->addYears(5)->year;

        $response = $this->get("/impact/weekly/{$futureYear}/1");

        $response->assertOk();
        $response->assertViewIs('pages.not-found');
    }

    public function test_invalid_period_defaults_to_weekly(): void
    {
        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $this->insertMetrics(Timescale::Weekly, $lastWeek, ['uploads' => 10, 'tags' => 50]);
        $this->insertAllTimeMetrics();

        $response = $this->get("/impact/invalid/{$isoYear}/{$isoWeek}");

        $response->assertOk();
        $response->assertViewHas('period', 'weekly');
    }

    // ─── Top brands uses v5 schema ───────────────────────────────────

    public function test_top_brands_uses_photo_tag_extra_tags(): void
    {
        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $this->insertMetrics(Timescale::Weekly, $lastWeek, ['uploads' => 10, 'tags' => 50]);
        $this->insertAllTimeMetrics(['uploads' => 100, 'tags' => 500]);

        // Create a photo in the target week
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'created_at' => $lastWeek->copy()->startOfWeek()->addDay(),
        ]);

        // Create a brand in brandslist
        $brandId = DB::table('brandslist')->insertGetId([
            'key' => 'test_brand',
            'is_custom' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a photo_tag
        $photoTagId = DB::table('photo_tags')->insertGetId([
            'photo_id' => $photo->id,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the brand extra tag
        DB::table('photo_tag_extra_tags')->insert([
            'photo_tag_id' => $photoTagId,
            'tag_type' => 'brand',
            'tag_type_id' => $brandId,
            'quantity' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/impact/weekly/{$isoYear}/{$isoWeek}");

        $response->assertOk();
        $response->assertViewHas('topBrands', function (array $brands) {
            return isset($brands['Test Brand']) && $brands['Test Brand'] === 3;
        });
    }

    // ─── Zero data renders gracefully ────────────────────────────────

    public function test_report_renders_with_no_data(): void
    {
        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $response = $this->get("/impact/weekly/{$isoYear}/{$isoWeek}");

        $response->assertOk();
        $response->assertViewIs('reports.impact');
        $response->assertViewHas('newPhotos', 0);
        $response->assertViewHas('newTags', 0);
        $response->assertViewHas('topUsers', []);
        $response->assertViewHas('topBrands', []);
        $response->assertViewHas('topTags', []);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function insertMetrics(Timescale $timescale, Carbon $date, array $data = []): void
    {
        // Weekly uses ISO year; monthly/yearly use calendar year (matches MetricsService)
        $year = $timescale === Timescale::Weekly ? (int) $date->format('o') : $date->year;

        DB::table('metrics')->insert(array_merge([
            'timescale' => $timescale->value,
            'location_type' => LocationType::Global->value,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $date->toDateString(),
            'year' => $year,
            'month' => $date->month,
            'week' => (int) $date->format('W'),
            'uploads' => 0,
            'tags' => 0,
            'litter' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'xp' => 0,
        ], $data));
    }

    private function insertAllTimeMetrics(array $data = []): void
    {
        DB::table('metrics')->insert(array_merge([
            'timescale' => Timescale::AllTime->value,
            'location_type' => LocationType::Global->value,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => '1970-01-01',
            'year' => 0,
            'month' => 0,
            'week' => 0,
            'uploads' => 0,
            'tags' => 0,
            'litter' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'xp' => 0,
        ], $data));
    }
}
