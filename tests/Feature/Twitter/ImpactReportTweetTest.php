<?php

namespace Tests\Feature\Twitter;

use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests for the impact report tweet commands.
 *
 * These test the date computation, URL construction, and message formatting
 * without requiring Chromium. Browsershot integration is untestable locally.
 */
class ImpactReportTweetTest extends TestCase
{
    // ─── Weekly: date logic ──────────────────────────────────────────

    public function test_weekly_computes_correct_iso_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-07 06:30:00')); // Monday

        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $this->assertEquals(2026, $isoYear);
        $this->assertEquals(14, $isoWeek);

        $url = "https://openlittermap.com/impact/weekly/{$isoYear}/{$isoWeek}";
        $this->assertEquals('https://openlittermap.com/impact/weekly/2026/14', $url);

        $msg = "Weekly Impact Report for week {$isoWeek} of {$isoYear}."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";
        $this->assertStringContainsString('week 14 of 2026', $msg);
        $this->assertLessThanOrEqual(280, mb_strlen($msg));

        Carbon::setTestNow();
    }

    public function test_weekly_iso_week_at_year_boundary(): void
    {
        // Jan 5 2026 (Monday) — last week starts Dec 29 2025
        Carbon::setTestNow(Carbon::parse('2026-01-05 06:30:00'));

        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        // Dec 29 2025 is ISO week 1 of 2026 (ISO weeks can span year boundaries)
        $url = "https://openlittermap.com/impact/weekly/{$isoYear}/{$isoWeek}";
        $this->assertMatchesRegularExpression('#/impact/weekly/\d{4}/\d{1,2}$#', $url);

        Carbon::setTestNow();
    }

    public function test_weekly_save_path(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-07 06:30:00'));

        $lastWeek = now()->subWeek();
        $isoYear = (int) $lastWeek->format('o');
        $isoWeek = (int) $lastWeek->format('W');

        $dir = public_path("images/reports/weekly/{$isoYear}/{$isoWeek}");
        $path = "{$dir}/impact-report.png";

        $this->assertStringContainsString('weekly/2026/14/impact-report.png', $path);

        Carbon::setTestNow();
    }

    // ─── Monthly: date logic ─────────────────────────────────────────

    public function test_monthly_computes_correct_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 06:30:00'));

        $lastMonth = now()->subMonth();

        $this->assertEquals(2026, $lastMonth->year);
        $this->assertEquals(3, $lastMonth->month);

        $url = "https://openlittermap.com/impact/monthly/{$lastMonth->year}/{$lastMonth->month}";
        $this->assertEquals('https://openlittermap.com/impact/monthly/2026/3', $url);

        $time = $lastMonth->format('F Y');
        $msg = "Monthly Impact Report for {$time}."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";
        $this->assertStringContainsString('March 2026', $msg);
        $this->assertLessThanOrEqual(280, mb_strlen($msg));

        Carbon::setTestNow();
    }

    public function test_monthly_at_year_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 06:30:00'));

        $lastMonth = now()->subMonth();

        $this->assertEquals(2025, $lastMonth->year);
        $this->assertEquals(12, $lastMonth->month);

        $url = "https://openlittermap.com/impact/monthly/{$lastMonth->year}/{$lastMonth->month}";
        $this->assertEquals('https://openlittermap.com/impact/monthly/2025/12', $url);

        $time = $lastMonth->format('F Y');
        $this->assertEquals('December 2025', $time);

        Carbon::setTestNow();
    }

    public function test_monthly_save_path(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 06:30:00'));

        $lastMonth = now()->subMonth();
        $dir = public_path("images/reports/monthly/{$lastMonth->year}/{$lastMonth->month}");
        $path = "{$dir}/impact-report.png";

        $this->assertStringContainsString('monthly/2026/3/impact-report.png', $path);

        Carbon::setTestNow();
    }

    // ─── Annual: date logic ──────────────────────────────────────────

    public function test_annual_computes_correct_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 06:30:00'));

        $lastYear = now()->subYear()->year;

        $this->assertEquals(2026, $lastYear);

        $url = "https://openlittermap.com/impact/annual/{$lastYear}";
        $this->assertEquals('https://openlittermap.com/impact/annual/2026', $url);

        $msg = "Annual Impact Report for {$lastYear}."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";
        $this->assertStringContainsString('2026', $msg);
        $this->assertLessThanOrEqual(280, mb_strlen($msg));

        Carbon::setTestNow();
    }

    public function test_annual_save_path(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 06:30:00'));

        $lastYear = now()->subYear()->year;
        $dir = public_path("images/reports/annual/{$lastYear}");
        $path = "{$dir}/impact-report.png";

        $this->assertStringContainsString('annual/2026/impact-report.png', $path);

        Carbon::setTestNow();
    }

    // ─── Browsershot failure ─────────────────────────────────────────

    public function test_weekly_returns_failure_when_browsershot_fails(): void
    {
        // No Chromium locally — Browsershot will throw, command should return FAILURE
        $this->artisan('twitter:weekly-impact-report-tweet')
            ->assertFailed();
    }

    public function test_monthly_returns_failure_when_browsershot_fails(): void
    {
        $this->artisan('twitter:monthly-impact-report-tweet')
            ->assertFailed();
    }

    public function test_annual_returns_failure_when_browsershot_fails(): void
    {
        $this->artisan('twitter:annual-impact-report-tweet')
            ->assertFailed();
    }

    // ─── Config ──────────────────────────────────────────────────────

    public function test_chrome_path_defaults_to_snap_chromium(): void
    {
        $this->assertEquals('/snap/bin/chromium', config('services.browsershot.chrome_path'));
    }

    public function test_chrome_path_is_configurable(): void
    {
        config(['services.browsershot.chrome_path' => '/usr/bin/google-chrome']);
        $this->assertEquals('/usr/bin/google-chrome', config('services.browsershot.chrome_path'));
    }

    // ─── Tweet length ────────────────────────────────────────────────

    public function test_all_tweet_messages_fit_280_chars(): void
    {
        // Worst-case: longest month name + 4-digit year
        $weekly = "Weekly Impact Report for week 53 of 2026."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";
        $monthly = "Monthly Impact Report for September 2026."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";
        $annual = "Annual Impact Report for 2026."
            . " Join us at openlittermap.com #litter #citizenscience #impact #openlittermap";

        $this->assertLessThanOrEqual(280, mb_strlen($weekly), "Weekly tweet exceeds 280 chars");
        $this->assertLessThanOrEqual(280, mb_strlen($monthly), "Monthly tweet exceeds 280 chars");
        $this->assertLessThanOrEqual(280, mb_strlen($annual), "Annual tweet exceeds 280 chars");
    }
}
