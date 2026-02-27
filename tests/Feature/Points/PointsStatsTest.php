<?php

namespace Tests\Feature\Points;

use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Litter\Tags\Materials;
use App\Models\Users\User;
use App\Models\Teams\Team;
use App\Services\Points\PointsStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // Ensure test tables exist
        $this->createTestTables();
    }

    /** @test */
    /** @test */
    public function it_returns_correct_counts_for_basic_aggregation()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $team = Team::factory()->create();
        $user2->teams()->attach($team);

        // Create photos with tags
        $photo1 = $this->createPhotoWithTags($user1, 0.0, 51.5, 'smoking', 'butts', 5, [
            'remaining' => false
        ]);

        $photo2 = $this->createPhotoWithTags($user2, 0.1, 51.5, 'food', 'wrapper', 3, [
            'remaining' => false,
            'team_id' => $team->id
        ]);

        $photo3 = $this->createPhotoWithTags($user2, 0.05, 51.5, 'alcohol', 'beer_bottle', 2, [
            'remaining' => true
        ]);

        // Act
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 16,
                'bbox' => $this->testBbox
            ]));

        $response->assertOk();
        $stats = $response->json()['data'];

        // Assert
        $this->assertEquals(3, $stats['counts']['photos']);
        $this->assertEquals(2, $stats['counts']['users']);
        $this->assertEquals(1, $stats['counts']['teams']);
        $this->assertEquals(10, $stats['counts']['total_objects']); // 5 + 3 + 2
        $this->assertEquals(10, $stats['counts']['total_tags']); // 5 + 3 + 2
        $this->assertEquals(2, $stats['counts']['picked_up']);
        $this->assertEquals(1, $stats['counts']['not_picked_up']);
    }

    /** @test */
    public function it_correctly_aggregates_photo_tags_without_double_counting()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $smoking = $this->createCategory('smoking');
        $butts = $this->createLitterObject('butts');

        // Create photo tag with quantity 5
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($smoking->id, $butts->id),
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 5
        ]);

        // Add materials (extras)
        $plastic = $this->createMaterial('plastic');
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'quantity' => 3
        ]);

        // Update photo totals
        $photo->update([
            'total_tags' => 5,
            'total_tags' => 8  // 5 objects + 3 materials
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
        $this->assertNotNull($smokingCategory);
        $this->assertEquals(8, $smokingCategory->qty, 'Category should include base + extras');

        // Check objects don't include extras
        $buttsObject = collect($stats['by_object'])->firstWhere('key', 'butts');
        $this->assertNotNull($buttsObject);
        $this->assertEquals(5, $buttsObject->qty, 'Objects should be base quantity only');

        // Check materials
        $plasticMaterial = collect($stats['materials'])->firstWhere('key', 'plastic');
        $this->assertNotNull($plasticMaterial);
        $this->assertEquals(3, $plasticMaterial->qty);
    }

    /** @test */
    public function it_correctly_sums_brand_quantities()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $alcohol = $this->createCategory('alcohol');
        $beerCan = $this->createLitterObject('beer_can');

        // Create brand first
        $brandId = DB::table('brandslist')->insertGetId([
            'key' => 'heineken',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create two photo tags
        $cloId = $this->getCloId($alcohol->id, $beerCan->id);
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $cloId,
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 2
        ]);

        $photoTag2 = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $cloId,
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 3
        ]);

        // Add brands with quantities
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brandId,
            'quantity' => 2
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag2->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brandId,
            'quantity' => 3
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $heinekenBrand = collect($stats['brands'])->firstWhere('key', 'heineken');
        $this->assertNotNull($heinekenBrand);
        $this->assertEquals(5, $heinekenBrand->qty, 'Should SUM quantities (2+3), not COUNT');
    }

    /** @test */
    public function it_filters_by_categories_correctly()
    {
        // Arrange
        $photo1 = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation(User::factory()->create(), 0.1, 51.5);

        $smoking = $this->createCategory('smoking');
        $food = $this->createCategory('food');
        $butts = $this->createLitterObject('butts');
        $wrapper = $this->createLitterObject('wrapper');

        // Photo 1: smoking + butts
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_litter_object_id' => $this->getCloId($smoking->id, $butts->id),
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 10
        ]);
        $photo1->update(['total_tags' => 10]);

        // Photo 2: food + wrapper
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_litter_object_id' => $this->getCloId($food->id, $wrapper->id),
            'category_id' => $food->id,
            'litter_object_id' => $wrapper->id,
            'quantity' => 5
        ]);
        $photo2->update(['total_tags' => 5]);

        // Act - Filter for smoking category
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'categories' => ['smoking']
        ]);

        // Assert
        $this->assertEquals(1, $stats['counts']['photos']);
        $this->assertEquals(10, $stats['counts']['total_objects']);
    }

    /** @test */
    public function it_filters_by_date_range()
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
    }

    /** @test */
    public function it_generates_time_histogram()
    {
        // Arrange
        $user = User::factory()->create();

        // Create photos across different dates
        $this->createPhotoWithLocation($user, 0.0, 51.5, [
            'datetime' => '2024-06-01 10:00:00',
            'total_tags' => 5
        ]);

        $this->createPhotoWithLocation($user, 0.0, 51.5, [
            'datetime' => '2024-06-01 14:00:00',
            'total_tags' => 3
        ]);

        $this->createPhotoWithLocation($user, 0.0, 51.5, [
            'datetime' => '2024-06-02 12:00:00',
            'total_tags' => 2
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'from' => '2024-06-01',
            'to' => '2024-06-07'
        ]);

        // Assert
        $histogram = collect($stats['time_histogram']);

        $june1Bucket = $histogram->firstWhere('bucket', '2024-06-01');
        $this->assertNotNull($june1Bucket);
        $this->assertEquals(2, $june1Bucket->photos);
        $this->assertEquals(8, $june1Bucket->objects); // 5 + 3

        $june2Bucket = $histogram->firstWhere('bucket', '2024-06-02');
        $this->assertNotNull($june2Bucket);
        $this->assertEquals(1, $june2Bucket->photos);
        $this->assertEquals(2, $june2Bucket->objects);
    }

    /** @test */
    public function it_filters_by_username_with_visibility()
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
    public function it_handles_spatial_filtering()
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
    public function it_handles_custom_tags()
    {
        // Arrange
        $photo = $this->createPhotoWithLocation(User::factory()->create(), 0.0, 51.5);
        $category = $this->createCategory('other');
        $object = $this->createLitterObject('random_litter');
        $customTag = $this->createCustomTag('overflowing_bin');

        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($category->id, $object->id),
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);

        // Add custom tag as extra tag
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom_tag',
            'tag_type_id' => $customTag,
            'quantity' => 1
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        // Assert
        $customTagResult = collect($stats['custom_tags'])->firstWhere('key', 'overflowing_bin');
        $this->assertNotNull($customTagResult);
        $this->assertEquals(1, $customTagResult->qty);
    }

    /** @test */
    public function it_returns_empty_stats_when_no_photos_found()
    {
        // Act
        $stats = $this->service->getStats([
            'bbox' => [
                'left' => 100,
                'bottom' => 100,
                'right' => 101,
                'top' => 101
            ],
            'zoom' => 16
        ]);

        // Assert
        $this->assertEquals(0, $stats['counts']['photos']);
        $this->assertEmpty($stats['by_category']);
        $this->assertEmpty($stats['by_object']);
        $this->assertEmpty($stats['brands']);
        $this->assertEmpty($stats['materials']);
        $this->assertEmpty($stats['time_histogram']);
    }

    /** @test */
    public function it_indicates_truncation_at_max_results()
    {
        $user = User::factory()->create();
        $country = \App\Models\Location\Country::factory()->create();
        $state = \App\Models\Location\State::factory()->create(['country_id' => $country->id]);

        for ($i = 0; $i < 1001; $i++) {
            $this->createPhotoWithLocation($user, 0.0, 51.5, [
                'country_id' => $country->id,
                'state_id' => $state->id,
            ]);
        }

        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16
        ]);

        $this->assertArrayHasKey('meta', $stats);
        $this->assertArrayHasKey('truncated', $stats['meta']);
        $this->assertTrue($stats['meta']['truncated']);
        $this->assertEquals(1000, $stats['meta']['max_results']);
        $this->assertEquals(1000, $stats['counts']['photos']);
    }

    /** @test */
    public function it_filters_by_material_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $photo1 = $this->createPhotoWithLocation($user, 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation($user, 0.1, 51.5);

        $smoking = $this->createCategory('smoking');
        $food = $this->createCategory('food');
        $butts = $this->createLitterObject('butts');
        $wrapper = $this->createLitterObject('wrapper');
        $plastic = $this->createMaterial('plastic');

        // Photo 1: smoking/butts with plastic material
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_litter_object_id' => $this->getCloId($smoking->id, $butts->id),
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 5,
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'quantity' => 5,
        ]);

        // Photo 2: food/wrapper with NO materials
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_litter_object_id' => $this->getCloId($food->id, $wrapper->id),
            'category_id' => $food->id,
            'litter_object_id' => $wrapper->id,
            'quantity' => 3,
        ]);

        // Act - Filter for plastic material
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'materials' => ['plastic'],
        ]);

        // Assert - Only photo1 should match
        $this->assertEquals(1, $stats['counts']['photos']);
    }

    /** @test */
    public function it_filters_by_brand_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $photo1 = $this->createPhotoWithLocation($user, 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation($user, 0.1, 51.5);

        $alcohol = $this->createCategory('alcohol');
        $beerCan = $this->createLitterObject('beer_can');

        $brandId = DB::table('brandslist')->insertGetId([
            'key' => 'coca-cola',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Photo 1: alcohol/beer_can with coca-cola brand
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_litter_object_id' => $this->getCloId($alcohol->id, $beerCan->id),
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 2,
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brandId,
            'quantity' => 2,
        ]);

        // Photo 2: alcohol/beer_can with NO brand
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_litter_object_id' => $this->getCloId($alcohol->id, $beerCan->id),
            'category_id' => $alcohol->id,
            'litter_object_id' => $beerCan->id,
            'quantity' => 3,
        ]);

        // Act - Filter for coca-cola brand
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'brands' => ['coca-cola'],
        ]);

        // Assert - Only photo1 should match
        $this->assertEquals(1, $stats['counts']['photos']);
    }

    /** @test */
    public function it_filters_by_custom_tag_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $photo1 = $this->createPhotoWithLocation($user, 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation($user, 0.1, 51.5);

        $other = $this->createCategory('other');
        $object = $this->createLitterObject('random_litter');
        $customTagId = $this->createCustomTag('broken_glass');

        // Photo 1: with custom tag
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_litter_object_id' => $this->getCloId($other->id, $object->id),
            'category_id' => $other->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'custom_tag',
            'tag_type_id' => $customTagId,
            'quantity' => 1,
        ]);

        // Photo 2: no custom tag
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_litter_object_id' => $this->getCloId($other->id, $object->id),
            'category_id' => $other->id,
            'litter_object_id' => $object->id,
            'quantity' => 2,
        ]);

        // Act - Filter for broken_glass custom tag
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
            'custom_tags' => ['broken_glass'],
        ]);

        // Assert - Only photo1 should match
        $this->assertEquals(1, $stats['counts']['photos']);
    }

    /** @test */
    public function it_includes_top_contributors_in_stats()
    {
        // Arrange
        $user = User::factory()->create([
            'username' => 'test_contributor',
            'show_username_maps' => true,
        ]);

        $this->createPhotoWithLocation($user, 0.0, 51.5, [
            'total_tags' => 10,
        ]);

        // Act
        $stats = $this->service->getStats([
            'bbox' => $this->testBbox,
            'zoom' => 16,
        ]);

        // Assert
        $this->assertArrayHasKey('top_contributors', $stats);
        $this->assertNotEmpty($stats['top_contributors']);
        $this->assertEquals('test_contributor', $stats['top_contributors'][0]->username);
    }

    private function createPhotoWithTags($user, $lon, $lat, $categoryKey, $objectKey, $quantity, $attributes = [])
    {
        $photo = $this->createPhotoWithLocation($user, $lon, $lat, $attributes);

        // Get or create category and object
        $category = Category::firstOrCreate(['key' => $categoryKey]);
        $object = LitterObject::firstOrCreate(['key' => $objectKey]);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($category->id, $object->id),
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => $quantity
        ]);

        return $photo;
    }

    private function createPhotoWithLocation($user, $lon, $lat, $attributes = [])
    {
        return Photo::factory()->create(array_merge([
            'user_id' => $user->id,
            'lat' => $lat,
            'lon' => $lon,
            'datetime' => now(),
            'remaining' => true,
            'verified' => 2,
            'total_tags' => 0,
            'total_tags' => 0,
        ], $attributes));
    }

    /**
     * Create test tables if they don't exist
     */
    private function createTestTables()
    {
        // Create categories table if needed
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        // Create litter_objects table if needed
        if (!Schema::hasTable('litter_objects')) {
            Schema::create('litter_objects', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        // Create materials table if needed
        if (!Schema::hasTable('materials')) {
            Schema::create('materials', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        // Create brandslist table if needed
        if (!Schema::hasTable('brandslist')) {
            Schema::create('brandslist', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        // Create custom_tags_new table if needed - using raw SQL to avoid reserved word issues
        if (!Schema::hasTable('custom_tags_new')) {
            DB::statement('CREATE TABLE custom_tags_new (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(255) NOT NULL UNIQUE,
                crowdsourced BOOLEAN DEFAULT FALSE,
                approved BOOLEAN DEFAULT FALSE,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )');
        }

        // Create photo_tags table if needed
        if (!Schema::hasTable('photo_tags')) {
            Schema::create('photo_tags', function ($table) {
                $table->id();
                $table->unsignedBigInteger('photo_id');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('litter_object_id')->nullable();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }

        // Create photo_tag_extra_tags table if needed
        if (!Schema::hasTable('photo_tag_extra_tags')) {
            Schema::create('photo_tag_extra_tags', function ($table) {
                $table->id();
                $table->unsignedBigInteger('photo_tag_id');
                $table->string('tag_type');
                $table->unsignedBigInteger('tag_type_id');
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }
    }

    /**
     * Helper methods to create test data
     */
    private function createCategory($key)
    {
        return Category::factory()->create(['key' => $key]);
    }

    private function createLitterObject($key)
    {
        return LitterObject::factory()->create(['key' => $key]);
    }

    private function createMaterial($key)
    {
        return Materials::factory()->create(['key' => $key]);
    }

    private function createCustomTag($key)
    {
        return DB::table('custom_tags_new')->insertGetId([
            'key' => $key,
            'approved' => true,
            'crowdsourced' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
