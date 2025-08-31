<?php

namespace Tests\Unit\Services;

use App\Enums\LocationType;
use App\Services\Locations\LocationService;
use App\Models\Location\Country;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationServiceTagTest extends TestCase
{
    protected LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService();
        Redis::flushall();
        Cache::flush();

        // Seed test tags in database
        $this->seedTestTags();
    }

    /**
     * Seed minimal test tags so TagKeyCache can resolve them
     */
    protected function seedTestTags(): void
    {
        // Objects
        DB::table('litter_objects')->insertOrIgnore([
            ['id' => 1, 'key' => 'bottle'],
            ['id' => 2, 'key' => 'can'],
            ['id' => 3, 'key' => 'wrapper'],
            ['id' => 4, 'key' => 'cup'],
            ['id' => 5, 'key' => 'bag'],
        ]);

        // Brands
        DB::table('brandslist')->insertOrIgnore([
            ['id' => 1, 'key' => 'coca-cola'],
            ['id' => 2, 'key' => 'pepsi'],
            ['id' => 3, 'key' => 'starbucks'],
        ]);

        // Materials
        DB::table('materials')->insertOrIgnore([
            ['id' => 1, 'key' => 'plastic'],
            ['id' => 2, 'key' => 'glass'],
            ['id' => 3, 'key' => 'metal'],
        ]);
    }

    public function test_get_top_tags_returns_correct_format()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:obj", '1', '10');
        Redis::hset("{c:{$country->id}}:obj", '2', '5');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '15');

        $result = $this->service->getTopTags(LocationType::Country, $country->id);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('dimension_total', $result);
        $this->assertArrayHasKey('other', $result);
        $this->assertEquals(15, $result['total']);
        $this->assertEquals(15, $result['dimension_total']);
    }

    public function test_get_top_tags_fallback_to_hash()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:obj", '1', '20');
        Redis::hset("{c:{$country->id}}:obj", '2', '15');
        Redis::hset("{c:{$country->id}}:obj", '3', '10');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '45');

        $result = $this->service->getTopTags(LocationType::Country, $country->id, 'objects', 2);

        $this->assertCount(2, $result['items']);
        $this->assertEquals(45, $result['total']);
        $this->assertEquals(45, $result['dimension_total']);
        $this->assertEquals(10, $result['other']['count']);
    }

    public function test_empty_location_returns_empty_results()
    {
        $country = Country::factory()->create();

        $result = $this->service->getTopTags(LocationType::Country, $country->id);

        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['dimension_total']);
        $this->assertEquals(0, $result['other']['count']);
    }

    public function test_cleanup_stats_calculation()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:stats", 'litter', '100');
        Redis::hset("{c:{$country->id}}:stats", 'picked_up', '25');

        $result = $this->service->getCleanupStats(LocationType::Country, $country->id);

        $this->assertEquals(25.0, $result['cleanup_rate']);
        $this->assertEquals(25, $result['total_picked_up']);
        $this->assertEquals(100, $result['total_litter']);
    }

    public function test_get_top_brands()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:brands", '1', '30');
        Redis::hset("{c:{$country->id}}:brands", '2', '20');
        Redis::hset("{c:{$country->id}}:brands", '3', '10');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '100');

        $result = $this->service->getTopTags(LocationType::Country, $country->id, 'brands', 3);

        $this->assertCount(3, $result['items']);
        $this->assertEquals(100, $result['total']);
        $this->assertEquals(60, $result['dimension_total']);

        // Check that brand names are resolved
        $this->assertEquals('coca-cola', $result['items'][0]['name']);
        $this->assertEquals('pepsi', $result['items'][1]['name']);
        $this->assertEquals('starbucks', $result['items'][2]['name']);
    }

    public function test_results_are_cached()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:obj", '1', '10');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '10');

        $result1 = $this->service->getTopTags(LocationType::Country, $country->id);

        Redis::hset("{c:{$country->id}}:obj", '1', '20');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '20');

        $result2 = $this->service->getTopTags(LocationType::Country, $country->id);

        $this->assertEquals($result1, $result2);
        $this->assertEquals(10, $result2['total']);

        Cache::flush();
        $result3 = $this->service->getTopTags(LocationType::Country, $country->id);
        $this->assertEquals(20, $result3['total']);
    }

    public function test_percentage_calculations_with_zero_total()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:stats", 'litter', '0');

        $result = $this->service->getTopTags(LocationType::Country, $country->id);

        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['dimension_total']);
        $this->assertEquals(0, $result['other']['percentage']);
    }

    public function test_get_tag_summary_returns_all_sections()
    {
        $country = Country::factory()->create();

        Redis::hset("{c:{$country->id}}:stats", 'litter', '100');

        $result = $this->service->getTagSummary(LocationType::Country, $country->id);

        $this->assertArrayHasKey('top_objects', $result);
        $this->assertArrayHasKey('top_brands', $result);
        $this->assertArrayHasKey('top_materials', $result);
        $this->assertArrayHasKey('total_litter', $result);
        $this->assertEquals(100, $result['total_litter']);
    }

    public function test_sorting_maintains_correct_order()
    {
        $country = Country::factory()->create();

        // Set values individually to avoid Redis issues
        Redis::hset("{c:{$country->id}}:obj", '5', '3');
        Redis::hset("{c:{$country->id}}:obj", '4', '7');
        Redis::hset("{c:{$country->id}}:obj", '3', '15');
        Redis::hset("{c:{$country->id}}:obj", '2', '10');
        Redis::hset("{c:{$country->id}}:obj", '1', '5');
        Redis::hset("{c:{$country->id}}:stats", 'litter', '40');

        $result = $this->service->getTopTags(LocationType::Country, $country->id, 'objects', 3);

        // Should be sorted by count descending
        $this->assertEquals(15, $result['items'][0]['count']);
        $this->assertEquals(10, $result['items'][1]['count']);
        $this->assertEquals(7, $result['items'][2]['count']);
        $this->assertEquals(8, $result['other']['count']);
    }
}
