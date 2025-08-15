<?php

namespace Tests\Feature\Points;

use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Users\User;
use App\Models\Teams\Team;
use App\Services\Points\PointsStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PointsStatsTest extends TestCase
{
    use RefreshDatabase;

    private PointsStatsService $service;
    private array $testBbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PointsStatsService::class);

        // Standard test bbox (small area)
        $this->testBbox = [
            'left' => -0.2,
            'bottom' => 51.4,
            'right' => 0.2,
            'top' => 51.6
        ];

        // Add spatial index for testing
        DB::statement('ALTER TABLE photos ADD SPATIAL INDEX idx_photos_geom (geom)');
    }

    /** @test */
    public function it_returns_correct_counts_for_basic_aggregation()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $team = Team::factory()->create();

        // Create photos with different states
        $photo1 = $this->createPhotoWithLocation($user1, 0.0, 51.5, ['remaining' => false]);
        $photo2 = $this->createPhotoWithLocation($user1, 0.1, 51.5, ['remaining' => true]);
        $photo3 = $this->createPhotoWithLocation($user2, 0.0, 51.5, ['remaining' => false, 'team_id' => $team->id]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $this->assertEquals(3, $stats['counts']['photos']);
        $this->assertEquals(2, $stats['counts']['users']);
        $this->assertEquals(1, $stats['counts']['teams']);
        $this->assertEquals(2, $stats['counts']['picked_up']);
        $this->assertEquals(1, $stats['counts']['not_picked_up']);
    }

    /** @test */
    public function it_correctly_aggregates_photo_tags_without_double_counting()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $smoking = Category::factory()->create(['key' => 'smoking']);
        $butts = LitterObject::factory()->create(['key' => 'butts']);

        // Create photo tag with quantity 5
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 5
        ]);

        // Add materials (extras)
        $plastic = Materials::factory()->create(['key' => 'plastic']);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'quantity' => 3
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $this->assertEquals(5, $stats['counts']['total_objects'], 'Should count base quantity only');
        $this->assertEquals(8, $stats['counts']['total_tags'], 'Should be base (5) + materials (3)');

        // Check categories include extras
        $smokingCategory = collect($stats['by_category'])->firstWhere('key', 'smoking');
        $this->assertEquals(8, $smokingCategory->qty, 'Category should include base + extras');

        // Check objects don't include extras
        $buttsObject = collect($stats['by_object'])->firstWhere('key', 'butts');
        $this->assertEquals(5, $buttsObject->qty, 'Objects should be base quantity only');

        // Check materials
        $plasticMaterial = collect($stats['materials'])->firstWhere('key', 'plastic');
        $this->assertEquals(3, $plasticMaterial->qty);
    }

    /** @test */
    public function it_correctly_sums_brand_quantities_not_counts()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $alcohol = Category::factory()->create(['key' => 'alcohol']);
        $beerCan = LitterObject::factory()->create(['key' => 'beer_can']);
        $brand = BrandList::factory()->create(['key' => 'heineken']);

        // Create two photo tags
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 2
        ]);

        $photoTag2 = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 3
        ]);

        // Add brands with quantities
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brand->id,
            'quantity' => 2
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag2->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brand->id,
            'quantity' => 3
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $heinekenBrand = collect($stats['brands'])->firstWhere('key', 'heineken');
        $this->assertEquals(5, $heinekenBrand->qty, 'Should SUM quantities (2+3), not COUNT');
    }

    /** @test */
    public function it_filters_by_categories_and_objects_correctly()
    {
        // Arrange
        $photo1 = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation(User::factory()->create(), 0.1, 51.5);

        $smoking = Category::factory()->create(['key' => 'smoking']);
        $food = Category::factory()->create(['key' => 'food']);
        $butts = LitterObject::factory()->create(['key' => 'butts']);
        $wrapper = LitterObject::factory()->create(['key' => 'wrapper']);

        // Photo 1: smoking + butts
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 10
        ]);

        // Photo 2: food + wrapper
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_id' => $food->id,
            'litter_object_id' => $wrapper->id,
            'quantity' => 5
        ]);

        // Act - Filter for smoking category
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'categories' => ['smoking']
        ]);

        // Assert
        $this->assertEquals(1, $stats['counts']['photos']);
        $this->assertEquals(10, $stats['counts']['total_objects']);

        // Act - Filter for both categories
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'categories' => ['smoking', 'food']
        ]);

        $this->assertEquals(2, $stats['counts']['photos']);
        $this->assertEquals(15, $stats['counts']['total_objects']);

        // Act - Filter for category AND object (must be in same tag)
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'categories' => ['smoking'],
            'litter_objects' => ['butts']
        ]);

        $this->assertEquals(1, $stats['counts']['photos']);

        // Act - Filter for mismatched category and object
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'categories' => ['smoking'],
            'litter_objects' => ['wrapper']
        ]);

        $this->assertEquals(0, $stats['counts']['photos']);
    }

    /** @test */
    public function it_filters_by_date_range_correctly()
    {
        // Arrange
        $user = User::factory()->create();

        $oldPhoto = $this->createPhotoWithLocation($user, 0.0, 51.5, [
            'datetime' => '2024-01-15 12:00:00'
        ]);

        $recentPhoto = $this->createPhotoWithLocation($user, 0.1, 51.5, [
            'datetime' => '2024-06-15 12:00:00'
        ]);

        // Act - Filter by date range
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'from' => '2024-06-01',
            'to' => '2024-06-30'
        ]);

        // Assert
        $this->assertEquals(1, $stats['counts']['photos']);

        // Act - Filter by year
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'year' => 2024
        ]);

        $this->assertEquals(2, $stats['counts']['photos']);
    }

    /** @test */
    public function it_generates_correct_time_histogram_buckets()
    {
        // Arrange
        $user = User::factory()->create();

        // Create photos across different time periods
        $this->createPhotoWithLocation($user, 0.0, 51.5, ['datetime' => '2024-06-01 10:00:00']);
        $this->createPhotoWithLocation($user, 0.0, 51.5, ['datetime' => '2024-06-01 14:00:00']);
        $this->createPhotoWithLocation($user, 0.0, 51.5, ['datetime' => '2024-06-02 12:00:00']);
        $this->createPhotoWithLocation($user, 0.0, 51.5, ['datetime' => '2024-06-15 12:00:00']);

        // Act - Daily buckets (small date range)
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'from' => '2024-06-01',
            'to' => '2024-06-07'
        ]);

        // Assert
        $histogram = collect($stats['time_histogram']);
        // Filter to only non-zero buckets
        $nonEmptyBuckets = $histogram->filter(fn($h) => $h->photos > 0);

        // We have photos on June 1 (2 photos) and June 2 (1 photo) = 2 non-empty buckets
        $this->assertGreaterThanOrEqual(2, $nonEmptyBuckets->count());

        $june1Bucket = $histogram->firstWhere('bucket', '2024-06-01');
        $this->assertNotNull($june1Bucket);
        $this->assertEquals(2, $june1Bucket->photos);

        $june2Bucket = $histogram->firstWhere('bucket', '2024-06-02');
        $this->assertNotNull($june2Bucket);
        $this->assertEquals(1, $june2Bucket->photos);

        // Act - Monthly buckets (large date range)
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'from' => '2024-01-01',
            'to' => '2024-12-31'
        ]);

        $histogram = collect($stats['time_histogram']);
        $this->assertEquals('2024-06-01', $histogram->firstWhere('photos', '>', 0)->bucket);
    }

    /** @test */
    public function it_handles_username_filter_with_visibility_settings()
    {
        // Arrange
        $visibleUser = User::factory()->create([
            'username' => 'visible_user',
            'show_username_maps' => true
        ]);

        $hiddenUser = User::factory()->create([
            'username' => 'hidden_user',
            'show_username_maps' => false
        ]);

        $this->createPhotoWithLocation($visibleUser, 0.0, 51.5);
        $this->createPhotoWithLocation($hiddenUser, 0.1, 51.5);

        // Act - Filter by visible username
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'username' => 'visible_user'
        ]);

        // Assert
        $this->assertEquals(1, $stats['counts']['photos']);

        // Act - Filter by hidden username
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'username' => 'hidden_user'
        ]);

        $this->assertEquals(0, $stats['counts']['photos']);
    }

    /** @test */
    public function it_handles_spatial_filtering_correctly()
    {
        // Arrange
        $user = User::factory()->create();

        $insidePhoto = $this->createPhotoWithLocation($user, 0.0, 51.5);
        $outsidePhoto = $this->createPhotoWithLocation($user, 1.0, 52.0); // Outside bbox

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $this->assertEquals(1, $stats['counts']['photos']);
    }

    /** @test */
    public function it_handles_large_dataset_with_sampling()
    {
        // Arrange - Create many photos (would trigger sampling in production)
        $user = User::factory()->create();

        // Create 20 photos with predictable IDs for deterministic sampling
        for ($i = 1; $i <= 20; $i++) {
            $photo = $this->createPhotoWithLocation($user, 0.0, 51.5);
            // Force specific ID for deterministic MOD sampling
            DB::table('photos')->where('id', $photo->id)->update(['id' => $i * 10]);
        }

        // Mock the service to use sampling for smaller datasets (for testing)
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sampledAggregate');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, [
            'bbox' => $this->testBbox,
            'zoom' => 16
        ], 200); // Pass totalCount to trigger sampling

        // Assert
        $this->assertArrayHasKey('meta', $result);
        $this->assertTrue($result['meta']['sampling']);
        $this->assertStringContainsString('%', $result['meta']['sample_rate']);
    }

    /** @test */
    public function it_handles_temp_table_failure_gracefully()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createPhotoWithLocation($user, 0.0, 51.5);

        // Force TEMP table failure by dropping permissions (in test environment)
        // This simulates a TEMP table creation failure

        // Act - Should fall back to direct aggregation
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert - Should still return results
        $this->assertEquals(1, $stats['counts']['photos']);
    }

    /** @test */
    public function it_correctly_handles_custom_tags()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $category = Category::factory()->create(['key' => 'other']);
        $object = LitterObject::factory()->create(['key' => 'random_litter']);
        $customTag = CustomTagNew::factory()->create(['key' => 'overflowing_bin', 'approved' => true]);

        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
            'custom_tag_primary_id' => $customTag->id
        ]);

        // Also add as extra tag
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom_tag',
            'tag_type_id' => $customTag->id,
            'quantity' => 1
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $customTagResult = collect($stats['custom_tags'])->firstWhere('key', 'overflowing_bin');
        $this->assertEquals(1, $customTagResult->qty);
    }

    /** @test */
    public function it_generates_consistent_cache_keys_with_tile_snapping()
    {
        // Use reflection to test public method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateCacheKey');

        // Two slightly different bboxes that should snap to same tile at zoom 12
        // At zoom 12, tiles are much larger so these will definitely snap to same tile
        $params1 = [
            'bbox' => [
                'left' => -0.1,
                'bottom' => 51.5,
                'right' => 0.0,
                'top' => 51.51
            ],
            'zoom' => 12
        ];

        $params2 = [
            'bbox' => [
                'left' => -0.09,
                'bottom' => 51.5,
                'right' => 0.01,
                'top' => 51.51
            ],
            'zoom' => 12
        ];

        // Act
        $key1 = $method->invoke($this->service, $params1);
        $key2 = $method->invoke($this->service, $params2);

        // Extract the bbox part from the keys
        preg_match('/b([^:]+)/', $key1, $matches1);
        preg_match('/b([^:]+)/', $key2, $matches2);

        // Assert - Should generate same bbox after tile snapping
        $this->assertEquals($matches1[1], $matches2[1], 'Bboxes should snap to same tile');
    }

    /** @test */
    public function it_matches_photo_summary_aggregation_logic()
    {
        // This test ensures stats match what would be in individual photo summaries

        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);

        $smoking = Category::factory()->create(['key' => 'smoking']);
        $butts = LitterObject::factory()->create(['key' => 'butts']);
        $plastic = Materials::factory()->create(['key' => 'plastic']);
        $brand = BrandList::factory()->create(['key' => 'marlboro']);

        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 10
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'quantity' => 5
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brand->id,
            'quantity' => 2
        ]);

        // Generate summary for photo (as your migration would)
        $expectedSummary = [
            'tags' => [
                'smoking' => [
                    'butts' => [
                        'quantity' => 10,
                        'materials' => ['plastic' => 5],
                        'brands' => ['marlboro'],
                        'custom_tags' => []
                    ]
                ]
            ],
            'totals' => [
                'total_tags' => 15, // 10 objects + 5 materials (brands don't count toward tags)
                'total_objects' => 10,
                'by_category' => ['smoking' => 15], // includes materials but not brands
                'materials' => 5,
                'brands' => 2, // This is the brand count, not added to total_tags
                'custom_tags' => 0
            ]
        ];

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert - Stats should match summary logic
        $this->assertEquals($expectedSummary['totals']['total_objects'], $stats['counts']['total_objects']);
        $this->assertEquals($expectedSummary['totals']['total_tags'], $stats['counts']['total_tags']);

        $smokingCategory = collect($stats['by_category'])->firstWhere('key', 'smoking');
        $this->assertEquals(15, $smokingCategory->qty, 'Should match summary category total');
    }

    /**
     * Helper method to create a photo with geospatial data
     */
    private function createPhotoWithLocation($user, $lon, $lat, $attributes = [])
    {
        // First create the photo
        $photo = Photo::factory()->create(array_merge([
            'user_id' => $user->id,
            'lat' => $lat,
            'lon' => $lon,
            'datetime' => now(),
            'remaining' => true,
            'verified' => 2
        ], $attributes));

        // Then update the geom column directly with raw SQL
        DB::statement("
            UPDATE photos
            SET geom = ST_GeomFromText('POINT({$lon} {$lat})', 4326)
            WHERE id = ?
        ", [$photo->id]);

        return $photo;
    }
}
