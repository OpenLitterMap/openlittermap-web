<?php

namespace Tests\Feature\Twitter;

use App\Console\Commands\Twitter\DailyReportTweet;
use App\Enums\LocationType;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyReportTweetTest extends TestCase
{
    use RefreshDatabase;

    private DailyReportTweet $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new DailyReportTweet();
    }

    // ─── calculateStreak ─────────────────────────────────────────────

    public function test_streak_returns_zero_when_no_data(): void
    {
        $this->assertEquals(0, $this->command->calculateStreak(Carbon::yesterday()));
    }

    public function test_streak_returns_one_for_single_day(): void
    {
        $this->insertDailyGlobal(Carbon::yesterday(), ['uploads' => 10]);

        $this->assertEquals(1, $this->command->calculateStreak(Carbon::yesterday()));
    }

    public function test_streak_counts_five_consecutive_days(): void
    {
        $date = Carbon::yesterday();

        for ($i = 0; $i < 5; $i++) {
            $this->insertDailyGlobal($date->copy()->subDays($i), ['uploads' => 10]);
        }

        $this->assertEquals(5, $this->command->calculateStreak($date));
    }

    public function test_streak_breaks_at_gap(): void
    {
        $date = Carbon::yesterday();

        // 3 consecutive days, then a gap, then 2 more
        $this->insertDailyGlobal($date, ['uploads' => 10]);
        $this->insertDailyGlobal($date->copy()->subDay(), ['uploads' => 10]);
        $this->insertDailyGlobal($date->copy()->subDays(2), ['uploads' => 10]);
        // Gap: skip subDays(3)
        $this->insertDailyGlobal($date->copy()->subDays(4), ['uploads' => 10]);
        $this->insertDailyGlobal($date->copy()->subDays(5), ['uploads' => 10]);

        $this->assertEquals(3, $this->command->calculateStreak($date));
    }

    // ─── nextMilestone ───────────────────────────────────────────────

    public function test_milestone_under_100k_uses_5k_step(): void
    {
        $this->assertEquals(5_000, $this->command->nextMilestone(0));
        $this->assertEquals(5_000, $this->command->nextMilestone(4_999));
        $this->assertEquals(10_000, $this->command->nextMilestone(5_000));
        $this->assertEquals(95_000, $this->command->nextMilestone(90_001));
        $this->assertEquals(100_000, $this->command->nextMilestone(99_999));
    }

    public function test_milestone_under_1m_uses_10k_step(): void
    {
        $this->assertEquals(110_000, $this->command->nextMilestone(100_000));
        $this->assertEquals(110_000, $this->command->nextMilestone(100_001));
        $this->assertEquals(500_000, $this->command->nextMilestone(490_001));
        $this->assertEquals(1_000_000, $this->command->nextMilestone(999_999));
    }

    public function test_milestone_above_1m_uses_50k_step(): void
    {
        $this->assertEquals(1_050_000, $this->command->nextMilestone(1_000_000));
        $this->assertEquals(1_050_000, $this->command->nextMilestone(1_000_001));
        $this->assertEquals(2_000_000, $this->command->nextMilestone(1_950_001));
    }

    // ─── seasonLabel ─────────────────────────────────────────────────

    public function test_season_road_to_100k(): void
    {
        $label = $this->command->seasonLabel(50_000);

        $this->assertStringContainsString('Road to 100K', $label);
        $this->assertStringContainsString('50', $label);
    }

    public function test_season_road_to_250k(): void
    {
        $label = $this->command->seasonLabel(150_000);

        $this->assertStringContainsString('Road to 250K', $label);
        $this->assertStringContainsString('60', $label);
    }

    public function test_season_road_to_500k(): void
    {
        $label = $this->command->seasonLabel(400_000);

        $this->assertStringContainsString('Road to 500K', $label);
        $this->assertStringContainsString('80', $label);
    }

    public function test_season_road_to_750k(): void
    {
        $label = $this->command->seasonLabel(600_000);

        $this->assertStringContainsString('Road to 750K', $label);
        $this->assertStringContainsString('80', $label);
    }

    public function test_season_road_to_1m(): void
    {
        $label = $this->command->seasonLabel(800_000);

        $this->assertStringContainsString('Road to 1M', $label);
        $this->assertStringContainsString('80', $label);
    }

    public function test_season_beyond_1m(): void
    {
        $label = $this->command->seasonLabel(1_500_000);

        $this->assertStringContainsString('Beyond 1M', $label);
        $this->assertStringContainsString('1,500,000', $label);
        $this->assertStringContainsString('counting', $label);
    }

    // ─── missionLine ─────────────────────────────────────────────────

    public function test_mission_frame_1_streak_and_close_to_milestone(): void
    {
        // streak >= 2, remaining (5000 - 4800 = 200) <= 50 * 7 = 350
        $line = $this->command->missionLine(50, 4_800, 5);

        $this->assertStringContainsString('streak alive', $line);
        $this->assertStringContainsString('50', $line);
    }

    public function test_mission_frame_2_milestone_this_week(): void
    {
        // No streak, but 50 uploads/day, remaining = 200, days = 4
        $line = $this->command->missionLine(50, 4_800, 0);

        $this->assertStringContainsString('this week', $line);
    }

    public function test_mission_frame_3_default(): void
    {
        // 10 uploads/day, remaining = 4000, days = 400 — not this week
        $line = $this->command->missionLine(10, 1_000, 0);

        $this->assertStringContainsString('Only', $line);
        $this->assertStringContainsString('photos', $line);
    }

    public function test_mission_frame_3_when_zero_uploads(): void
    {
        $line = $this->command->missionLine(0, 1_000, 0);

        $this->assertStringContainsString('Only', $line);
    }

    // ─── leadLine ────────────────────────────────────────────────────

    public function test_lead_line_same_country_shows_streak(): void
    {
        $yesterday = Carbon::yesterday();

        // Same country leading for 3 days
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 100);
        $this->insertCountryMetrics($yesterday->copy()->subDay(), 1, 'Ireland', 'ie', 80);
        $this->insertCountryMetrics($yesterday->copy()->subDays(2), 1, 'Ireland', 'ie', 60);

        $line = $this->command->leadLine('Ireland', '🇮🇪', $yesterday);

        $this->assertStringContainsString('leads the way', $line);
        $this->assertStringContainsString('Day 3', $line);
    }

    public function test_lead_line_new_country_takes_the_lead(): void
    {
        $yesterday = Carbon::yesterday();

        // Ireland leads today
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 100);
        // US led yesterday
        $this->insertCountryMetrics($yesterday->copy()->subDay(), 5, 'United States of America', 'us', 80);

        $line = $this->command->leadLine('Ireland', '🇮🇪', $yesterday);

        $this->assertStringContainsString('takes the lead', $line);
    }

    public function test_lead_line_no_previous_data_plain_format(): void
    {
        $yesterday = Carbon::yesterday();

        // Only today's data
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 100);

        $line = $this->command->leadLine('Ireland', '🇮🇪', $yesterday);

        $this->assertEquals('🥇 🇮🇪 Ireland', $line);
    }

    // ─── Thread output ───────────────────────────────────────────────

    public function test_thread_always_produces_two_tweets(): void
    {
        $yesterday = Carbon::yesterday();

        $this->insertDailyGlobal($yesterday, ['uploads' => 10, 'tags' => 50, 'litter' => 30, 'xp' => 200]);
        $this->insertAllTimeGlobal(['uploads' => 500_000, 'tags' => 1_000_000, 'litter' => 500_000, 'xp' => 5_000_000]);

        User::factory()->create(['created_at' => $yesterday]);

        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 50);

        \Illuminate\Support\Facades\Artisan::call('twitter:daily-report');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Tweet 1:', $output);
        $this->assertStringContainsString('Tweet 2:', $output);
        $this->assertStringContainsString('📊 OpenLitterMap', $output);
        $this->assertStringContainsString('🏆 Country Podium', $output);
    }

    // ─── Conditional skipping ────────────────────────────────────────

    public function test_zero_littercoin_not_shown(): void
    {
        $yesterday = Carbon::yesterday();

        $this->insertDailyGlobal($yesterday, ['uploads' => 10, 'tags' => 50, 'litter' => 30, 'xp' => 200]);
        $this->insertAllTimeGlobal(['uploads' => 500_000, 'tags' => 1_000_000, 'litter' => 500_000, 'xp' => 5_000_000]);
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 50);

        \Illuminate\Support\Facades\Artisan::call('twitter:daily-report');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString('Littercoin', $output);
    }

    public function test_no_streak_line_when_streak_under_2(): void
    {
        $yesterday = Carbon::yesterday();

        $this->insertDailyGlobal($yesterday, ['uploads' => 10, 'tags' => 50, 'litter' => 30, 'xp' => 200]);
        $this->insertAllTimeGlobal(['uploads' => 500_000, 'tags' => 1_000_000, 'litter' => 500_000, 'xp' => 5_000_000]);
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 50);

        \Illuminate\Support\Facades\Artisan::call('twitter:daily-report');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringNotContainsString('consecutive uploads', $output);
    }

    public function test_streak_shown_when_two_or_more(): void
    {
        $yesterday = Carbon::yesterday();

        $this->insertDailyGlobal($yesterday, ['uploads' => 10, 'tags' => 50, 'litter' => 30, 'xp' => 200]);
        $this->insertDailyGlobal($yesterday->copy()->subDay(), ['uploads' => 5, 'tags' => 20, 'litter' => 10, 'xp' => 100]);
        $this->insertAllTimeGlobal(['uploads' => 500_000, 'tags' => 1_000_000, 'litter' => 500_000, 'xp' => 5_000_000]);
        $this->insertCountryMetrics($yesterday, 1, 'Ireland', 'ie', 50);

        \Illuminate\Support\Facades\Artisan::call('twitter:daily-report');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Day 2 of consecutive uploads', $output);
    }

    public function test_skips_when_no_uploads(): void
    {
        $this->artisan('twitter:daily-report')
            ->expectsOutputToContain('No uploads yesterday')
            ->assertSuccessful();
    }

    // ─── formatMilestone ─────────────────────────────────────────────

    public function test_format_milestone_thousands(): void
    {
        $this->assertEquals('5k', $this->command->formatMilestone(5_000));
        $this->assertEquals('525k', $this->command->formatMilestone(525_000));
    }

    public function test_format_milestone_millions(): void
    {
        $this->assertEquals('1M', $this->command->formatMilestone(1_000_000));
        $this->assertEquals('1.05M', $this->command->formatMilestone(1_050_000));
        $this->assertEquals('2M', $this->command->formatMilestone(2_000_000));
    }

    // ─── Tweet length enforcement ────────────────────────────────────

    public function test_tweets_never_exceed_280_chars(): void
    {
        $yesterday = Carbon::yesterday();

        // Use very long country names to push tweet 2 over the limit
        $this->insertDailyGlobal($yesterday, ['uploads' => 10, 'tags' => 50, 'litter' => 30, 'xp' => 200]);
        $this->insertDailyGlobal($yesterday->copy()->subDay(), ['uploads' => 5, 'tags' => 20, 'litter' => 10, 'xp' => 100]);
        $this->insertAllTimeGlobal(['uploads' => 500_000, 'tags' => 1_000_000, 'litter' => 500_000, 'xp' => 5_000_000]);

        // Insert 3 countries with long names
        $this->insertCountryMetrics($yesterday, 1, 'The Democratic Republic of the Congo', 'cd', 100);
        $this->insertCountryMetrics($yesterday, 2, 'Saint Vincent and the Grenadines', 'vc', 80);
        $this->insertCountryMetrics($yesterday, 3, 'The Former Yugoslav Republic of Macedonia', 'mk', 60);

        // Insert a state for FK constraint, then a city with a long name
        DB::table('states')->insert([
            'id' => 1,
            'state' => 'Test State',
            'country_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cities')->insert([
            'city' => 'Llanfairpwllgwyngyllgogerychwyrndrobwllllantysiliogogogoch',
            'country_id' => 1,
            'state_id' => 1,
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);

        User::factory()->create(['created_at' => $yesterday]);

        \Illuminate\Support\Facades\Artisan::call('twitter:daily-report');
        $output = \Illuminate\Support\Facades\Artisan::output();

        // Extract the actual tweets from output
        preg_match('/Tweet 1:\n(.*?)(?=\nTweet 2:)/s', $output, $m1);
        preg_match('/Tweet 2:\n(.*?)(?=\nThread)/s', $output, $m2);

        if (! empty($m1[1])) {
            $this->assertLessThanOrEqual(280, mb_strlen(trim($m1[1])), 'Tweet 1 exceeds 280 chars');
        }
        if (! empty($m2[1])) {
            $this->assertLessThanOrEqual(280, mb_strlen(trim($m2[1])), 'Tweet 2 exceeds 280 chars');
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function insertDailyGlobal(Carbon $date, array $data): void
    {
        DB::table('metrics')->insert(array_merge([
            'timescale' => 1,
            'location_type' => LocationType::Global->value,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $date->toDateString(),
            'year' => $date->year,
            'month' => $date->month,
            'week' => $date->weekOfYear,
            'uploads' => 0,
            'tags' => 0,
            'litter' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'xp' => 0,
        ], $data));
    }

    private function insertAllTimeGlobal(array $data): void
    {
        DB::table('metrics')->insert(array_merge([
            'timescale' => 0,
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

    private function insertCountryMetrics(Carbon $date, int $countryId, string $name, string $shortcode, int $tags): void
    {
        if (! DB::table('countries')->where('id', $countryId)->exists()) {
            DB::table('countries')->insert([
                'id' => $countryId,
                'country' => $name,
                'shortcode' => $shortcode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Country->value,
            'location_id' => $countryId,
            'user_id' => 0,
            'bucket_date' => $date->toDateString(),
            'year' => $date->year,
            'month' => $date->month,
            'week' => $date->weekOfYear,
            'uploads' => $tags,
            'tags' => $tags,
            'litter' => 0,
            'brands' => 0,
            'materials' => 0,
            'custom_tags' => 0,
            'xp' => 0,
        ]);
    }
}
