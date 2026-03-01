<?php

namespace Tests\Feature\Api;

use App\Enums\LocationType;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalStatsTest extends TestCase
{
    public function test_global_stats_returns_totals(): void
    {
        // 2 users created today, 1 created 10 days ago
        User::factory()->count(2)->create();
        User::factory()->create(['created_at' => now()->subDays(10)]);

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

        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_tags',
            'total_images',
            'total_users',
            'new_users_today',
            'new_users_last_7_days',
            'new_users_last_30_days',
        ]);
        $response->assertJsonPath('total_tags', 150);
        $response->assertJsonPath('total_images', 42);
        $response->assertJsonPath('total_users', 3);
        $response->assertJsonPath('new_users_today', 2);
        $response->assertJsonPath('new_users_last_7_days', 2);
        $response->assertJsonPath('new_users_last_30_days', 3);
    }

    public function test_global_stats_returns_zeros_when_no_data(): void
    {
        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
        $response->assertJsonPath('total_tags', 0);
        $response->assertJsonPath('total_images', 0);
        $response->assertJsonPath('total_users', 0);
        $response->assertJsonPath('new_users_today', 0);
        $response->assertJsonPath('new_users_last_7_days', 0);
        $response->assertJsonPath('new_users_last_30_days', 0);
    }

    public function test_global_stats_requires_no_auth(): void
    {
        $response = $this->getJson('/api/global/stats-data');

        $response->assertOk();
    }
}
