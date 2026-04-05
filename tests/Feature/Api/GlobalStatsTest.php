<?php

namespace Tests\Feature\Api;

use App\Enums\LocationType;
use App\Models\Users\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalStatsTest extends TestCase
{
    public function test_global_stats_returns_totals(): void
    {
        // 2 users created today, 1 created 10 days ago
        User::factory()->count(2)->create();
        User::factory()->create(['created_at' => now()->subDays(10)]);

        // All-time totals row
        DB::table('metrics')->insert([
            'timescale' => 0,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => '1970-01-01',
            'year' => 0,
            'month' => 0,
            'week' => 0,
            'uploads' => 42,
            'tags' => 150,
            'litter' => 100,
            'xp' => 500,
        ]);

        $now = now('UTC');

        // Daily bucket: today
        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $now->toDateString(),
            'year' => $now->year,
            'month' => $now->month,
            'week' => 0,
            'uploads' => 5,
            'tags' => 20,
            'litter' => 10,
            'xp' => 50,
        ]);

        // Daily bucket: 3 days ago (within 7-day window)
        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $now->copy()->subDays(3)->toDateString(),
            'year' => $now->copy()->subDays(3)->year,
            'month' => $now->copy()->subDays(3)->month,
            'week' => 0,
            'uploads' => 10,
            'tags' => 40,
            'litter' => 20,
            'xp' => 100,
        ]);

        // Daily bucket: 20 days ago (within 30-day window, outside 7-day)
        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $now->copy()->subDays(20)->toDateString(),
            'year' => $now->copy()->subDays(20)->year,
            'month' => $now->copy()->subDays(20)->month,
            'week' => 0,
            'uploads' => 8,
            'tags' => 30,
            'litter' => 15,
            'xp' => 80,
        ]);

        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_tags',
            'total_images',
            'total_users',
            // New keys
            'new_users_last_24_hours',
            'new_users_last_7_days',
            'new_users_last_30_days',
            'new_tags_last_24_hours',
            'new_tags_last_7_days',
            'new_tags_last_30_days',
            'new_photos_last_24_hours',
            'new_photos_last_7_days',
            'new_photos_last_30_days',
            // Legacy keys (pre-v5.7 mobile compat)
            'new_users_today',
            'new_tags_today',
            'new_photos_today',
        ]);
        $response->assertJsonPath('total_tags', 150);
        $response->assertJsonPath('total_images', 42);
        $response->assertJsonPath('total_users', 3);
        $response->assertJsonPath('new_users_last_24_hours', 2);
        $response->assertJsonPath('new_users_last_7_days', 2);
        $response->assertJsonPath('new_users_last_30_days', 3);
        $response->assertJsonPath('new_tags_last_24_hours', 20);
        $response->assertJsonPath('new_tags_last_7_days', 60);       // 20 + 40
        $response->assertJsonPath('new_tags_last_30_days', 90);      // 20 + 40 + 30
        $response->assertJsonPath('new_photos_last_24_hours', 5);
        $response->assertJsonPath('new_photos_last_7_days', 15);     // 5 + 10
        $response->assertJsonPath('new_photos_last_30_days', 23);    // 5 + 10 + 8

        // Legacy keys match new keys
        $response->assertJsonPath('new_users_today', 2);
        $response->assertJsonPath('new_tags_today', 20);
        $response->assertJsonPath('new_photos_today', 5);
    }

    public function test_global_stats_returns_zeros_when_no_data(): void
    {
        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonPath('total_tags', 0);
        $response->assertJsonPath('total_images', 0);
        $response->assertJsonPath('total_users', 0);
        $response->assertJsonPath('new_users_last_24_hours', 0);
        $response->assertJsonPath('new_users_last_7_days', 0);
        $response->assertJsonPath('new_users_last_30_days', 0);
        $response->assertJsonPath('new_tags_last_24_hours', 0);
        $response->assertJsonPath('new_tags_last_7_days', 0);
        $response->assertJsonPath('new_tags_last_30_days', 0);
        $response->assertJsonPath('new_photos_last_24_hours', 0);
        $response->assertJsonPath('new_photos_last_7_days', 0);
        $response->assertJsonPath('new_photos_last_30_days', 0);
    }

    public function test_global_stats_excludes_old_daily_data(): void
    {
        $now = now('UTC');

        // Daily bucket: 45 days ago (outside 30-day window)
        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $now->copy()->subDays(45)->toDateString(),
            'year' => $now->copy()->subDays(45)->year,
            'month' => $now->copy()->subDays(45)->month,
            'week' => 0,
            'uploads' => 99,
            'tags' => 200,
            'litter' => 50,
            'xp' => 300,
        ]);

        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonPath('new_tags_last_24_hours', 0);
        $response->assertJsonPath('new_tags_last_7_days', 0);
        $response->assertJsonPath('new_tags_last_30_days', 0);
        $response->assertJsonPath('new_photos_last_24_hours', 0);
        $response->assertJsonPath('new_photos_last_7_days', 0);
        $response->assertJsonPath('new_photos_last_30_days', 0);
    }

    public function test_global_stats_requires_no_auth(): void
    {
        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
    }

    public function test_24h_includes_yesterday_bucket_just_after_midnight(): void
    {
        // At 00:05 UTC, "last 24 hours" should include yesterday's bucket
        Carbon::setTestNow(Carbon::parse('2026-04-04 00:05:00', 'UTC'));

        $yesterday = now('UTC')->copy()->subDay()->toDateString(); // 2026-04-03

        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $yesterday,
            'year' => 2026,
            'month' => 4,
            'week' => 0,
            'uploads' => 15,
            'tags' => 60,
            'litter' => 30,
            'xp' => 100,
        ]);

        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        // Yesterday's bucket is included in last_24_hours (bucket_date >= yesterday)
        $response->assertJsonPath('new_photos_last_24_hours', 15);
        $response->assertJsonPath('new_tags_last_24_hours', 60);

        Carbon::setTestNow();
    }

    public function test_24h_users_uses_exact_timestamp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-04 12:00:00', 'UTC'));

        // User created 23 hours ago (within 24h)
        User::factory()->create(['created_at' => now()->subHours(23)]);
        // User created 25 hours ago (outside 24h)
        User::factory()->create(['created_at' => now()->subHours(25)]);

        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonPath('new_users_last_24_hours', 1);

        Carbon::setTestNow();
    }

    public function test_legacy_today_keys_match_last_24_hours(): void
    {
        $now = now('UTC');

        DB::table('metrics')->insert([
            'timescale' => 1,
            'location_type' => LocationType::Global,
            'location_id' => 0,
            'user_id' => 0,
            'bucket_date' => $now->toDateString(),
            'year' => $now->year,
            'month' => $now->month,
            'week' => 0,
            'uploads' => 7,
            'tags' => 25,
            'litter' => 10,
            'xp' => 50,
        ]);

        User::factory()->create();

        $response = $this->getJson('/api/global/stats-data');
        $data = $response->json();

        $this->assertEquals($data['new_users_last_24_hours'], $data['new_users_today']);
        $this->assertEquals($data['new_tags_last_24_hours'], $data['new_tags_today']);
        $this->assertEquals($data['new_photos_last_24_hours'], $data['new_photos_today']);
    }
}
