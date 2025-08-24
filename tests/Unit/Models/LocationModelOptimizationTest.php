<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Location\City;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LocationModelOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
        TagKeyCache::preloadAll();
    }

    /**
     * Test that total_litter_redis uses stats.litter when available
     */
    public function test_total_litter_uses_stats_field(): void
    {
        $country = Country::factory()->create();

        // Set stats.litter
        Redis::hset("c:{$country->id}:stats", 'litter', '500');

        // Also set objects hash (shouldn't be used)
        Redis::hset("c:{$country->id}:t", [
            '1' => '100',
            '2' => '200',
        ]);

        // Should use stats.litter, not sum of objects
        $this->assertEquals(500, $country->total_litter_redis);
    }

    /**
     * Test fallback to summing objects when stats.litter not available
     */
    public function test_total_litter_fallback_to_sum(): void
    {
        $country = Country::factory()->create();

        // No stats.litter, only objects hash
        Redis::hset("c:{$country->id}:t", [
            '1' => '100',
            '2' => '200',
            '3' => '50',
        ]);

        // Should sum objects: 100 + 200 + 50 = 350
        $this->assertEquals(350, $country->total_litter_redis);
    }

    /**
     * Test total_photos_redis uses stats.photos
     */
    public function test_total_photos_uses_stats_field(): void
    {
        $country = Country::factory()->create();

        Redis::hset("c:{$country->id}:stats", 'photos', '42');

        $this->assertEquals(42, $country->total_photos_redis);
    }

    /**
     * Test last_upload_timestamp attribute
     */
    public function test_last_upload_timestamp_attribute(): void
    {
        $country = Country::factory()->create();

        $timestamp = now()->subDays(2)->getTimestamp();
        Redis::hset("c:{$country->id}:stats", 'last_ts', (string)$timestamp);

        $this->assertEquals($timestamp, $country->last_upload_timestamp);
    }

    /**
     * Test last_upload_date formatting
     */
    public function test_last_upload_date_formatting(): void
    {
        $country = Country::factory()->create();

        $timestamp = now()->subDays(2)->getTimestamp();
        Redis::hset("c:{$country->id}:stats", 'last_ts', (string)$timestamp);

        $date = $country->last_upload_date;
        $this->assertStringContainsString('T', $date); // ISO8601 format
        $this->assertStringContainsString('Z', $date);
    }

    /**
     * Test recent_activity uses HMGET optimization
     */
    public function test_recent_activity_optimization(): void
    {
        $country = Country::factory()->create();

        // Set some daily data
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');

        Redis::hset("c:{$country->id}:t:p", [
            $today => '10',
            $yesterday => '5',
        ]);

        $activity = $country->recent_activity;

        // Should have 7 days
        $this->assertCount(7, $activity);
        $this->assertEquals(10, $activity[$today]);
        $this->assertEquals(5, $activity[$yesterday]);
    }

    /**
     * Test State model uses correct prefix
     */
    public function test_state_model_uses_correct_prefix(): void
    {
        $state = State::factory()->create();

        Redis::hset("s:{$state->id}:stats", [
            'photos' => '25',
            'litter' => '150',
        ]);

        $this->assertEquals(25, $state->total_photos_redis);
        $this->assertEquals(150, $state->total_litter_redis);
    }

    /**
     * Test City model uses correct prefix
     */
    public function test_city_model_uses_correct_prefix(): void
    {
        $city = City::factory()->create();

        Redis::hset("ci:{$city->id}:stats", [
            'photos' => '10',
            'litter' => '75',
        ]);

        $this->assertEquals(10, $city->total_photos_redis);
        $this->assertEquals(75, $city->total_litter_redis);
    }

    /**
     * Test total_contributors uses stats.contributors when available
     */
    public function test_total_contributors_uses_stats_field(): void
    {
        $country = Country::factory()->create();

        // Set stats.contributors
        Redis::hset("c:{$country->id}:stats", 'contributors', '42');

        // Also add users to set (shouldn't be counted)
        Redis::sadd("c:{$country->id}:users", ['1', '2', '3']);

        // Should use stats.contributors
        $this->assertEquals(42, $country->total_contributors_redis);
    }

    /**
     * Test total_contributors fallback to SCARD
     */
    public function test_total_contributors_fallback_to_scard(): void
    {
        $country = Country::factory()->create();

        // No stats.contributors, only users set
        Redis::sadd("c:{$country->id}:users", ['1', '2', '3']);

        // Should count set members
        $this->assertEquals(3, $country->total_contributors_redis);
    }

    /**
     * Test PPM uses deterministic month lookup
     */
    public function test_ppm_deterministic_lookup(): void
    {
        $country = Country::factory()->create();

        $thisMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        Redis::hset("c:{$country->id}:{$thisMonth}:t", 'p', '10');
        Redis::hset("c:{$country->id}:{$lastMonth}:t", 'p', '20');

        $ppm = $country->ppm;

        $this->assertArrayHasKey($thisMonth, $ppm);
        $this->assertArrayHasKey($lastMonth, $ppm);
        $this->assertEquals(10, $ppm[$thisMonth]);
        $this->assertEquals(20, $ppm[$lastMonth]);
    }
}
