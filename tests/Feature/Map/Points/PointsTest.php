<?php

namespace Tests\Feature\Map\Points;

use Tests\TestCase;
use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Teams\Team;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private $endpoint = '/api/points';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Seed the database with tags and brands
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        // Ensure any existing photos have correct geom values
        // This handles any photos created before triggers were set up
        DB::statement("
            UPDATE photos
            SET geom = ST_SRID(POINT(lon, lat), 4326)
            WHERE lon IS NOT NULL AND lat IS NOT NULL
        ");
    }

    /** @test */
    public function it_returns_geojson_feature_collection()
    {
        // Create test photo
        $photo = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'verified' => 2,
            'datetime' => '2024-06-15 10:00:00',
            'model' => 'iphone',
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => [
                    'left' => 4.400,
                    'bottom' => 52.140,
                    'right' => 4.440,
                    'top' => 52.150
                ]
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry' => ['type', 'coordinates'],
                        'properties'
                    ]
                ],
                'meta' => ['total', 'per_page', 'page']
            ])
            ->assertJson([
                'type' => 'FeatureCollection'
            ]);
    }

    /** @test */
    public function it_returns_coordinates_in_correct_geojson_order()
    {
        // Create photo with known coordinates
        $photo = Photo::factory()->create([
            'lat' => 52.3676,
            'lon' => 4.9041,
            'verified' => 2,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => [
                    'left' => 4.9,
                    'bottom' => 52.36,
                    'right' => 4.91,
                    'top' => 52.37
                ]
            ]));

        $response->assertOk();

        $feature = $response->json('features.0');

        // GeoJSON spec requires [longitude, latitude] order
        $this->assertEquals(4.9041, $feature['geometry']['coordinates'][0]);
        $this->assertEquals(52.3676, $feature['geometry']['coordinates'][1]);
    }

    /** @test */
    public function it_filters_photos_by_bounding_box()
    {
        // Create photos inside and outside bbox
        $inside1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $inside2 = Photo::factory()->create(['lat' => 52.146, 'lon' => 4.421, 'datetime' => now()]);
        $outside1 = Photo::factory()->create(['lat' => 52.160, 'lon' => 4.420, 'datetime' => now()]); // North
        $outside2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.450, 'datetime' => now()]); // East
        $outside3 = Photo::factory()->create(['lat' => 52.130, 'lon' => 4.420, 'datetime' => now()]); // South
        $outside4 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.390, 'datetime' => now()]); // West

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => [
                    'left' => 4.410,
                    'bottom' => 52.140,
                    'right' => 4.430,
                    'top' => 52.150
                ]
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');

        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($inside1->id));
        $this->assertTrue($ids->contains($inside2->id));
        $this->assertFalse($ids->contains($outside1->id));
        $this->assertFalse($ids->contains($outside2->id));
    }

    /** @test */
    public function it_filters_photos_by_categories()
    {
        // Get categories from seeded data
        $smoking = Category::where('key', 'smoking')->first();
        $alcohol = Category::where('key', 'alcohol')->first();

        // Get litter objects from seeded data
        $cigarettes = LitterObject::where('key', 'butts')->first();
        $bottles = LitterObject::where('key', 'beer_bottle')->first();

        // Create photos with tags
        $photoSmoking = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photoAlcohol = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);
        $photoFood = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.422, 'datetime' => now()]);

        // Create PhotoTags
        PhotoTag::create([
            'photo_id' => $photoSmoking->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $cigarettes->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        PhotoTag::create([
            'photo_id' => $photoAlcohol->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $bottles->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'categories' => ['smoking']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photoSmoking->id));
    }

    /** @test */
    public function it_filters_photos_by_date_range()
    {
        $old = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => '2023-01-15 10:00:00'
        ]);

        $inRange = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.421,
            'datetime' => '2024-06-15 10:00:00'
        ]);

        $recent = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.422,
            'datetime' => '2025-01-15 10:00:00'
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'from' => '2024-01-01',
                'to' => '2024-12-31'
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($inRange->id));
    }

    /** @test */
    public function it_filters_photos_by_username()
    {
        $user1 = User::factory()->create([
            'username' => 'johndoe',
            'show_username_maps' => true
        ]);

        $user2 = User::factory()->create([
            'username' => 'janedoe',
            'show_username_maps' => true
        ]);

        $user3 = User::factory()->create([
            'username' => 'hidden',
            'show_username_maps' => false
        ]);

        $photo1 = Photo::factory()->create([
            'user_id' => $user1->id,
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        $photo2 = Photo::factory()->create([
            'user_id' => $user2->id,
            'lat' => 52.145,
            'lon' => 4.421,
            'datetime' => now()
        ]);

        $photo3 = Photo::factory()->create([
            'user_id' => $user3->id,
            'lat' => 52.145,
            'lon' => 4.422,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'username' => 'johndoe'
            ]));

        $response->assertOk();

        $features = $response->json('features');

        $this->assertCount(1, $features);
        $this->assertEquals($photo1->id, $features[0]['properties']['id']);
    }

    /** @test */
    public function it_respects_user_privacy_settings()
    {
        $userPublic = User::factory()->create([
            'name' => 'John Public',
            'username' => 'johnpublic',
            'show_name_maps' => true,
            'show_username_maps' => true
        ]);

        $userPrivate = User::factory()->create([
            'name' => 'Jane Private',
            'username' => 'janeprivate',
            'show_name_maps' => false,
            'show_username_maps' => false
        ]);

        Photo::factory()->create([
            'user_id' => $userPublic->id,
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        Photo::factory()->create([
            'user_id' => $userPrivate->id,
            'lat' => 52.145,
            'lon' => 4.421,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $features = $response->json('features');

        // Check public user
        $publicFeature = collect($features)->firstWhere('properties.username', 'johnpublic');
        $this->assertEquals('John Public', $publicFeature['properties']['name']);
        $this->assertEquals('johnpublic', $publicFeature['properties']['username']);

        // Check private user
        $privateFeature = collect($features)->firstWhere('properties.username', null);
        $this->assertNull($privateFeature['properties']['name']);
        $this->assertNull($privateFeature['properties']['username']);
    }

    /** @test */
    public function it_shows_correct_filename_based_on_verification()
    {
        $user = User::factory()->create();

        $verifiedPhoto = Photo::factory()->create([
            'user_id' => $user->id,
            'lat' => 52.145,
            'lon' => 4.420,
            'verified' => 2,
            'filename' => 'verified-photo.jpg',
            'datetime' => now()
        ]);

        $unverifiedPhoto = Photo::factory()->create([
            'user_id' => $user->id,
            'lat' => 52.145,
            'lon' => 4.421,
            'verified' => 0,
            'filename' => 'unverified-photo.jpg',
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $features = collect($response->json('features'));

        $verified = $features->firstWhere('properties.id', $verifiedPhoto->id);
        $this->assertEquals('verified-photo.jpg', $verified['properties']['filename']);

        $unverified = $features->firstWhere('properties.id', $unverifiedPhoto->id);
        $this->assertEquals('/assets/images/waiting.png', $unverified['properties']['filename']);
    }

    /** @test */
    public function it_calculates_picked_up_status_correctly()
    {
        $pickedUp = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'remaining' => false,
            'datetime' => now()
        ]);

        $notPickedUp = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.421,
            'remaining' => true,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $features = collect($response->json('features'));

        $picked = $features->firstWhere('properties.id', $pickedUp->id);
        $this->assertTrue($picked['properties']['picked_up']);

        $notPicked = $features->firstWhere('properties.id', $notPickedUp->id);
        $this->assertFalse($notPicked['properties']['picked_up']);
    }

    /** @test */
    public function it_paginates_results()
    {
        // Create 10 photos
        for ($i = 0; $i < 10; $i++) {
            Photo::factory()->create([
                'lat' => 52.145,
                'lon' => 4.420 + ($i * 0.001),
                'datetime' => now()
            ]);
        }

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'per_page' => 5
            ]));

        $response->assertOk();

        $this->assertCount(5, $response->json('features'));
        $this->assertEquals(10, $response->json('meta.total'));
        $this->assertEquals(5, $response->json('meta.per_page'));
        $this->assertEquals(1, $response->json('meta.page'));
    }

    /** @test */
    public function it_enforces_maximum_per_page_limit()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'per_page' => 1000
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    /** @test */
    public function it_validates_required_parameters()
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['zoom', 'bbox.left', 'bbox.bottom', 'bbox.right', 'bbox.top']);
    }

    /** @test */
    public function it_validates_zoom_range()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 10, // Below minimum
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['zoom']);
    }

    /** @test */
    public function it_validates_bbox_ordering()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => [
                    'left' => 4.43,  // Left > Right (invalid)
                    'bottom' => 52.14,
                    'right' => 4.41,
                    'top' => 52.15
                ]
            ]));

        $response->assertStatus(422);
        // Note: The error message will be different since we're using abort()
        $this->assertStringContainsString('Invalid bounding box', $response->json('message'));
    }

    /** @test */
    public function it_rejects_overly_large_bbox_at_high_zoom()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => [
                    'left' => 0,     // 10° wide
                    'bottom' => 50,
                    'right' => 10,
                    'top' => 60      // 10° tall = 100 sq degrees
                ]
            ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('Bounding box too large', $response->json('message'));
    }

    /** @test */
    public function it_validates_categories_exist_in_database()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'categories' => ['invalid_category']
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['categories.0']);
    }

    /** @test */
    public function it_caches_responses_for_public_requests()
    {
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);

        $params = [
            'zoom' => 17,
            'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
        ];

        // First request
        $response1 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $response1->assertOk();

        // Modify database (should not affect cached response)
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // Second request (should be cached)
        $response2 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $response2->assertOk();

        // Should return same number of features (cached)
        $this->assertCount(1, $response2->json('features'));
    }

    /** @test */
    public function it_does_not_cache_username_filtered_requests()
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'show_username_maps' => true
        ]);

        Photo::factory()->create([
            'user_id' => $user->id,
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        $params = [
            'zoom' => 17,
            'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
            'username' => 'testuser'
        ];

        // First request
        $response1 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $this->assertCount(1, $response1->json('features'));

        // Add another photo
        Photo::factory()->create([
            'user_id' => $user->id,
            'lat' => 52.145,
            'lon' => 4.421,
            'datetime' => now()
        ]);

        // Second request (should not be cached)
        $response2 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $this->assertCount(2, $response2->json('features'));
    }

    /** @test */
    public function it_handles_edge_cases_for_coordinates()
    {
        // Test near edge of bounding box (slightly inside to account for boundary exclusion)
        $nearEdge = Photo::factory()->create([
            'lat' => 52.149999, // Just inside top edge
            'lon' => 4.429999,   // Just inside right edge
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertTrue($ids->contains($nearEdge->id));
    }

    /** @test */
    public function it_includes_team_information()
    {
        $team = Team::factory()->create(['name' => 'Cleanup Crew']);
        $photo = Photo::factory()->create([
            'team_id' => $team->id,
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $feature = $response->json('features.0');
        $this->assertEquals('Cleanup Crew', $feature['properties']['team']);
    }

    /** @test */
    public function it_includes_comprehensive_metadata()
    {
        Photo::factory()->count(5)->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'per_page' => 3,
                'from' => '2024-01-01',
                'to' => '2024-12-31'
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => [
                    'bbox',
                    'zoom',
                    'page',
                    'per_page',
                    'total',
                    'total_pages',
                    'returned',
                    'from',
                    'to',
                    'generated_at'
                ]
            ]);

        $meta = $response->json('meta');
        $this->assertEquals([4.41, 52.14, 4.43, 52.15], $meta['bbox']);
        $this->assertEquals(17, $meta['zoom']);
        $this->assertEquals('2024-01-01', $meta['from']);
        $this->assertEquals('2024-12-31', $meta['to']);
    }

    /** @test */
    public function it_rejects_null_coordinates_on_insert()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Either lat or lon NULL should fail since geom cannot be NULL
        Photo::factory()->create(['lat' => null, 'lon' => 4.420, 'datetime' => now()]);
    }

    /** @test */
    public function it_is_publicly_accessible_without_authentication()
    {
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('features'));
    }

    /** @test */
    public function it_filters_photos_by_litter_objects()
    {
        // Get categories and objects from seeded data
        $smoking = Category::where('key', 'smoking')->first();
        $cigarettes = LitterObject::where('key', 'butts')->first();
        $bottles = LitterObject::where('key', 'beer_bottle')->first();

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // Create PhotoTags
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $cigarettes->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_id' => Category::where('key', 'alcohol')->first()->id,
            'litter_object_id' => $bottles->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'litter_objects' => ['butts']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photo1->id));
    }

    /** @test */
    public function it_filters_photos_by_brands()
    {
        // Get data from seeded database
        $category = Category::where('key', 'softdrinks')->first();
        $bottle = LitterObject::where('key', 'water_bottle')->first();

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // Create PhotoTag for photo1 with brand extra tag
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_id' => $category->id,
            'litter_object_id' => $bottle->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Get or create a brand
        $cocaCola = BrandList::firstOrCreate(['key' => 'coca-cola']);

        // Add brand as extra tag
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'brand',
            'tag_type_id' => $cocaCola->id,
            'index' => 0,
            'quantity' => 1
        ]);

        // Create PhotoTag for photo2 without brand
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_id' => $category->id,
            'litter_object_id' => $bottle->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'brands' => ['coca-cola']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photo1->id));
    }

    /** @test */
    public function it_filters_photos_by_custom_tags()
    {
        // Create custom tags
        $tag1 = CustomTagNew::create(['key' => 'beach-cleanup', 'approved' => true]);
        $tag2 = CustomTagNew::create(['key' => 'park-cleanup', 'approved' => true]);

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // Create PhotoTag with primary custom tag
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'custom_tag_primary_id' => $tag1->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Create PhotoTag with different custom tag
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'custom_tag_primary_id' => $tag2->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'custom_tags' => ['beach-cleanup']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photo1->id));
    }

    /** @test */
    public function it_filters_photos_by_materials()
    {
        // Get data from seeded database
        $category = Category::where('key', 'softdrinks')->first();
        $bottle = LitterObject::where('key', 'water_bottle')->first();
        $plastic = Materials::where('key', 'plastic')->first();
        $glass = Materials::where('key', 'glass')->first();

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // Create PhotoTag for photo1
        $photoTag1 = PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_id' => $category->id,
            'litter_object_id' => $bottle->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Add plastic material as extra tag
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag1->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'index' => 0,
            'quantity' => 1
        ]);

        // Create PhotoTag for photo2 with different material
        $photoTag2 = PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_id' => $category->id,
            'litter_object_id' => $bottle->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Add glass material as extra tag
        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag2->id,
            'tag_type' => 'material',
            'tag_type_id' => $glass->id,
            'index' => 0,
            'quantity' => 1
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'materials' => ['plastic']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photo1->id));
    }

    /** @test */
    public function filters_require_same_photo_tag_to_match_all_selected_criteria()
    {
        // Arrange: create properly matched photo
        $smoking = Category::where('key', 'smoking')->first();
        $butts = LitterObject::where('key', 'butts')->first();

        $photo = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);

        // Create a PhotoTag that matches both category and object
        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Flush cache to ensure fresh results
        Cache::flush();

        // Act: filter smoking + butts together
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'categories' => ['smoking'],
                'litter_objects' => ['butts'],
            ]));

        // Assert: should find the photo
        $response->assertOk();
        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($photo->id));
    }

    /** @test */
    public function it_validates_date_range_order()
    {
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'from' => '2024-12-31',
                'to' => '2024-01-01'
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    /** @test */
    public function it_excludes_unapproved_custom_tags()
    {
        // Create an unapproved custom tag
        $unapprovedTag = CustomTagNew::create(['key' => 'unapproved-tag', 'approved' => false]);
        $approvedTag = CustomTagNew::create(['key' => 'approved-tag', 'approved' => true]);

        // Create photos with these tags
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        PhotoTag::create([
            'photo_id' => $photo1->id,
            'custom_tag_primary_id' => $unapprovedTag->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        PhotoTag::create([
            'photo_id' => $photo2->id,
            'custom_tag_primary_id' => $approvedTag->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Filter by unapproved tag
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'custom_tags' => ['unapproved-tag']
            ]));

        $response->assertOk();
        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertCount(0, $ids); // Should not find any

        // Filter by approved tag
        $response2 = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'custom_tags' => ['approved-tag']
            ]));

        $ids2 = collect($response2->json('features'))->pluck('properties.id');
        $this->assertCount(1, $ids2);
        $this->assertTrue($ids2->contains($photo2->id));
    }

    /** @test */
    public function it_returns_deterministic_ordering_for_pagination()
    {
        // Create photos with same datetime
        $datetime = now();
        $photos = [];
        for ($i = 0; $i < 5; $i++) {
            $photos[] = Photo::factory()->create([
                'lat' => 52.145,
                'lon' => 4.420 + ($i * 0.001),
                'datetime' => $datetime,
                'id' => 100 + $i // Set specific IDs for predictable ordering
            ]);
        }

        $params = [
            'zoom' => 17,
            'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
            'per_page' => 2
        ];

        // Get page 1
        $response1 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $ids1 = collect($response1->json('features'))->pluck('properties.id');

        // Get page 2
        $params['page'] = 2;
        $response2 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $ids2 = collect($response2->json('features'))->pluck('properties.id');

        // Verify no overlap and deterministic ordering
        $this->assertCount(2, $ids1);
        $this->assertCount(2, $ids2);
        $this->assertTrue($ids1->intersect($ids2)->isEmpty());

        // Verify order is by datetime DESC, then id ASC
        // Since all have same datetime, should be ordered by id ASC
        $this->assertEquals([100, 101], $ids1->sort()->values()->toArray());
        $this->assertEquals([102, 103], $ids2->sort()->values()->toArray());
    }

    /** @test */
    public function it_can_use_spatial_index_for_queries()
    {
        // Create photos spread across a large area
        for ($i = 0; $i < 100; $i++) {
            Photo::factory()->create([
                'lat' => 50 + ($i * 0.01),
                'lon' => 4 + ($i * 0.01),
                'datetime' => now()
            ]);
        }

        // Query a small bounding box
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 18,
                'bbox' => [
                    'left' => 4.40,
                    'bottom' => 50.40,
                    'right' => 4.50,
                    'top' => 50.50
                ]
            ]));

        $response->assertOk();

        // Should only return photos within the bbox
        $features = $response->json('features');
        foreach ($features as $feature) {
            $lon = $feature['geometry']['coordinates'][0];
            $lat = $feature['geometry']['coordinates'][1];

            $this->assertGreaterThanOrEqual(4.40, $lon);
            $this->assertLessThanOrEqual(4.50, $lon);
            $this->assertGreaterThanOrEqual(50.40, $lat);
            $this->assertLessThanOrEqual(50.50, $lat);
        }
    }

    /** @test */
    public function it_handles_datetime_column_properly()
    {
        // Test that datetime column works with Carbon dates
        $specificDate = '2024-07-15 14:30:00';
        $photo = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => $specificDate
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'from' => '2024-07-01',
                'to' => '2024-07-31'
            ]));

        $response->assertOk();

        $features = $response->json('features');
        $this->assertCount(1, $features);

        // Verify datetime is properly returned
        $returnedDatetime = $features[0]['properties']['datetime'];
        $this->assertStringStartsWith('2024-07-15', $returnedDatetime);
    }

    /** @test */
    public function it_filters_multiple_tag_types_with_or_logic()
    {
        // Setup: Photos with different tag combinations
        $smoking = Category::where('key', 'smoking')->first();
        $alcohol = Category::where('key', 'alcohol')->first();
        $butts = LitterObject::where('key', 'butts')->first();
        $plastic = Materials::where('key', 'plastic')->first();

        // Photo 1: smoking category only
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 1,
            'picked_up' => false
        ]);

        // Photo 2: plastic material only
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);
        $tag2 = PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => LitterObject::where('key', 'beer_bottle')->first()->id,
            'quantity' => 1,
            'picked_up' => false
        ]);
        PhotoTagExtraTags::create([
            'photo_tag_id' => $tag2->id,
            'tag_type' => 'material',
            'tag_type_id' => $plastic->id,
            'index' => 0,
            'quantity' => 1
        ]);

        // Query with both filters (should use OR logic between different filter types)
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'categories' => ['smoking'],
                'materials' => ['plastic']
            ]));

        $response->assertOk();
        $ids = collect($response->json('features'))->pluck('properties.id');

        // Should find both photos (OR logic between different filter types)
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($photo1->id));
        $this->assertTrue($ids->contains($photo2->id));
    }

    /** @test */
    public function geom_is_in_lon_lat_order_and_has_srid_4326()
    {
        $p = Photo::factory()->create([
            'lat' => 52.3676,
            'lon' => 4.9041,
            'datetime' => now(),
        ]);

        $row = DB::table('photos')
            ->selectRaw('id, lon, lat,
                ST_Longitude(geom) AS lon_val,
                ST_Latitude(geom)  AS lat_val,
                ST_SRID(geom) AS srid'
            )
            ->where('id', $p->id)
            ->first();

        $this->assertEquals(4.9041, (float)$row->lon_val);
        $this->assertEquals(52.3676, (float)$row->lat_val);
        $this->assertEquals(4326, (int)$row->srid);
    }

    /** @test */
    public function geom_updates_when_lat_lon_change()
    {
        $p = Photo::factory()->create([
            'lat' => 52.0, 'lon' => 4.0, 'datetime' => now(),
        ]);

        // Update coordinates
        DB::table('photos')->where('id', $p->id)->update(['lat' => 53.5, 'lon' => 5.5]);

        $row = DB::table('photos')
            ->selectRaw('ST_Longitude(geom) AS lon_val, ST_Latitude(geom) AS lat_val')
            ->where('id', $p->id)
            ->first();

        $this->assertEquals(5.5, (float)$row->lon_val);
        $this->assertEquals(53.5, (float)$row->lat_val);
    }

    /** @test */
    public function spatial_mbr_contains_returns_points_in_bbox()
    {
        // Two inside, one outside
        $inside1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $inside2 = Photo::factory()->create(['lat' => 52.146, 'lon' => 4.421, 'datetime' => now()]);
        $outside = Photo::factory()->create(['lat' => 52.160, 'lon' => 4.450, 'datetime' => now()]);

        $left = 4.410;
        $bottom = 52.140;
        $right = 4.430;
        $top = 52.150;

        // MySQL/MariaDB syntax for creating a bounding box polygon
        $polygon = sprintf('POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $left, $bottom,
            $right, $bottom,
            $right, $top,
            $left, $top,
            $left, $bottom
        );

        $rows = DB::table('photos')
            ->select('id')
            ->whereRaw('MBRContains(ST_GeomFromText(?, 4326, "axis-order=long-lat"), geom)', [$polygon])
            ->get()
            ->pluck('id');

        $this->assertTrue($rows->contains($inside1->id));
        $this->assertTrue($rows->contains($inside2->id));
        $this->assertFalse($rows->contains($outside->id));
    }

    /** @test */
    public function spatial_index_exists_on_geom()
    {
        $idx = DB::selectOne("
            SHOW INDEX FROM photos WHERE Key_name = 'photos_geom_sidx'
        ");
        $this->assertNotNull($idx);
        $this->assertEquals('SPATIAL', $idx->Index_type ?? $idx->Comment ?? 'SPATIAL');
    }

    /** @test */
    public function explain_uses_spatial_index_for_bbox()
    {
        // Create some test data
        Photo::factory()->count(10)->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => now()
        ]);

        $left = 4.410;
        $bottom = 52.140;
        $right = 4.430;
        $top = 52.150;

        // MySQL/MariaDB syntax for creating a bounding box polygon
        $polygon = sprintf('POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $left, $bottom,
            $right, $bottom,
            $right, $top,
            $left, $top,
            $left, $bottom
        );

        $plan = DB::select("
            EXPLAIN SELECT id
            FROM photos FORCE INDEX (photos_geom_sidx)
            WHERE MBRContains(ST_GeomFromText(?, 4326), geom)
        ", [$polygon]);

        // Look for the key = photos_geom_sidx
        $key = data_get($plan, '0.key');

        // With FORCE INDEX, it should use the spatial index
        $this->assertEquals('photos_geom_sidx', $key);
    }
}
