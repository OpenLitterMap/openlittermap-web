<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Location\Country;
use App\Services\Locations\LocationService;
use App\Services\Achievements\Tags\TagKeyCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LocationServiceTagTest extends TestCase
{
    use RefreshDatabase;

    private LocationService $service;
    private array $tagIds;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushall();
        Cache::flush();

        $this->service = new LocationService();

        TagKeyCache::preloadAll();

        // Create test tag IDs
        $this->tagIds = [
            'cup' => TagKeyCache::getOrCreateId('object', 'cup'),
            'bottle' => TagKeyCache::getOrCreateId('object', 'bottle'),
            'wrapper' => TagKeyCache::getOrCreateId('object', 'wrapper'),
            'starbucks' => TagKeyCache::getOrCreateId('brand', 'starbucks'),
            'cocacola' => TagKeyCache::getOrCreateId('brand', 'cocacola'),
            'plastic' => TagKeyCache::getOrCreateId('material', 'plastic'),
            'paper' => TagKeyCache::getOrCreateId('material', 'paper'),
        ];
    }

    /**
     * Test getTopTags returns correct format
     */
    public function test_get_top_tags_returns_correct_format(): void
    {
        $country = Country::factory()->create();

        // Set up test data in Redis
        Redis::zadd("rank:c:{$country->id}:objects", [
            (string)$this->tagIds['cup'] => 100,
            (string)$this->tagIds['bottle'] => 50,
            (string)$this->tagIds['wrapper'] => 25,
        ]);

        Redis::hset("c:{$country->id}:stats", 'litter', '175');

        $result = $this->service->getTopTags('country', $country->id, 'objects', 2);

        // Check structure
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('other', $result);

        // Check items
        $this->assertCount(2, $result['items']);
        $this->assertEquals('cup', $result['items'][0]['name']);
        $this->assertEquals(100, $result['items'][0]['count']);
        $this->assertEquals(57.14, $result['items'][0]['percentage']); // 100/175 * 100

        // Check other bucket
        $this->assertEquals(25, $result['other']['count']); // wrapper not in top 2
        $this->assertEquals(14.29, $result['other']['percentage']);
    }

    /**
     * Test fallback to hash when ZSETs not available
     */
    public function test_get_top_tags_fallback_to_hash(): void
    {
        $country = Country::factory()->create();

        // Set up data only in hash, not ZSET
        Redis::hset("c:{$country->id}:t", [
            (string)$this->tagIds['cup'] => '75',
            (string)$this->tagIds['bottle'] => '25',
        ]);

        Redis::hset("c:{$country->id}:stats", 'litter', '100');

        $result = $this->service->getTopTags('country', $country->id, 'objects', 10);

        $this->assertCount(2, $result['items']);
        $this->assertEquals(100, $result['total']);
        $this->assertEquals(0, $result['other']['count']);
    }

    /**
     * Test empty location returns empty results
     */
    public function test_empty_location_returns_empty_results(): void
    {
        $country = Country::factory()->create();

        $result = $this->service->getTopTags('country', $country->id, 'objects', 10);

        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['other']['count']);
    }

    /**
     * Test cleanup stats calculation
     */
    public function test_cleanup_stats_calculation(): void
    {
        $country = Country::factory()->create();

        Redis::hset("c:{$country->id}:stats", [
            'litter' => '100',
            'picked_up' => '25'
        ]);

        $result = $this->service->getCleanupStats('country', $country->id);

        $this->assertEquals(25.0, $result['cleanup_rate']);
        $this->assertEquals(25, $result['total_picked_up']);
        $this->assertEquals(100, $result['total_litter']);
        $this->assertEquals(75, $result['remaining']);
    }

    /**
     * Test get top brands
     */
    public function test_get_top_brands(): void
    {
        $country = Country::factory()->create();

        Redis::zadd("rank:c:{$country->id}:brands", [
            (string)$this->tagIds['starbucks'] => 50,
            (string)$this->tagIds['cocacola'] => 30,
        ]);

        Redis::hset("c:{$country->id}:stats", 'litter', '200');

        $result = $this->service->getTopTags('country', $country->id, 'brands', 5);

        $this->assertCount(2, $result['items']);
        $this->assertEquals('starbucks', $result['items'][0]['name']);
        $this->assertEquals(50, $result['items'][0]['count']);
    }

    /**
     * Test caching works
     */
    public function test_results_are_cached(): void
    {
        $country = Country::factory()->create();

        Redis::zadd("rank:c:{$country->id}:objects", [
            (string)$this->tagIds['cup'] => 100
        ]);
        Redis::hset("c:{$country->id}:stats", 'litter', '100');

        // First call
        $result1 = $this->service->getTopTags('country', $country->id, 'objects', 10);

        // Modify Redis (this shouldn't affect cached result)
        Redis::zadd("rank:c:{$country->id}:objects", [
            (string)$this->tagIds['bottle'] => 200
        ]);

        // Second call should return cached result
        $result2 = $this->service->getTopTags('country', $country->id, 'objects', 10);

        $this->assertEquals($result1, $result2);

        // Clear cache and try again
        Cache::flush();
        $result3 = $this->service->getTopTags('country', $country->id, 'objects', 10);

        // Now bottle should be included
        $this->assertCount(2, $result3['items']);
    }

    /**
     * Test percentage calculations with zero total
     */
    public function test_percentage_calculations_with_zero_total(): void
    {
        $country = Country::factory()->create();

        Redis::zadd("rank:c:{$country->id}:objects", [
            (string)$this->tagIds['cup'] => 100
        ]);

        // No litter stat set

        $result = $this->service->getTopTags('country', $country->id, 'objects', 10);

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['items']);
    }

    /**
     * Test getTagSummary returns all sections
     */
    public function test_get_tag_summary_returns_all_sections(): void
    {
        $country = Country::factory()->create();

        // Set up minimal data
        Redis::hset("c:{$country->id}:stats", 'litter', '100');

        $result = $this->service->getTagSummary('country', $country->id);

        $this->assertArrayHasKey('top_objects', $result);
        $this->assertArrayHasKey('top_brands', $result);
        $this->assertArrayHasKey('top_materials', $result);
        $this->assertArrayHasKey('cleanup_stats', $result);
        $this->assertArrayHasKey('categories', $result);
    }

    /**
     * Test sorting maintains correct order
     */
    public function test_sorting_maintains_correct_order(): void
    {
        $country = Country::factory()->create();

        // Add items in random order
        Redis::zadd("rank:c:{$country->id}:objects", [
            (string)$this->tagIds['wrapper'] => 10,
            (string)$this->tagIds['cup'] => 100,
            (string)$this->tagIds['bottle'] => 50,
        ]);

        Redis::hset("c:{$country->id}:stats", 'litter', '160');

        $result = $this->service->getTopTags('country', $country->id, 'objects', 10);

        // Should be sorted by count descending
        $this->assertEquals('cup', $result['items'][0]['name']);
        $this->assertEquals('bottle', $result['items'][1]['name']);
        $this->assertEquals('wrapper', $result['items'][2]['name']);
    }
}
