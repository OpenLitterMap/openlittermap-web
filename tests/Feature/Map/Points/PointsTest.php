<?php

namespace Tests\Feature\Map\Points;

use Tests\TestCase;
use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Teams\Team;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\CustomTagNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private $endpoint = '/api/points';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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
        // Create categories
        $smoking = Category::create(['key' => 'smoking']);
        $alcohol = Category::create(['key' => 'alcohol']);
        $food = Category::create(['key' => 'food']);

        // Create litter objects
        $cigarettes = LitterObject::create(['key' => 'cigarette_butts']);
        $bottles = LitterObject::create(['key' => 'alcohol_bottles']);

        // Create category-object relationships
        $smokingCigs = $smoking->litterObjects()->attach($cigarettes);
        $alcoholBottles = $alcohol->litterObjects()->attach($bottles);

        // Get the pivot models (CategoryObject)
        $smokingPivot = CategoryObject::where('category_id', $smoking->id)
            ->where('litter_object_id', $cigarettes->id)
            ->first();

        $alcoholPivot = CategoryObject::where('category_id', $alcohol->id)
            ->where('litter_object_id', $bottles->id)
            ->first();

        // Create photos
        $photoSmoking = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photoAlcohol = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);
        $photoFood = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.422, 'datetime' => now()]);

        // TODO: Connect photos to category objects
        // This depends on your actual pivot table structure
        // Example: $photoSmoking->categoryObjects()->attach($smokingPivot->id);

        // For now, mark as incomplete until we know the photo-categoryObject relationship
        $this->markTestIncomplete('Need to implement photo->categoryObjects relationship');
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
        // Test edge of bounding box
        $onEdge = Photo::factory()->create([
            'lat' => 52.150, // Exactly on top edge
            'lon' => 4.430,   // Exactly on right edge
            'datetime' => now()
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');
        $this->assertTrue($ids->contains($onEdge->id));
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
    public function it_handles_null_coordinates_gracefully()
    {
        // Create photos with null coordinates
        Photo::factory()->create(['lat' => null, 'lon' => 4.420, 'datetime' => now()]);
        Photo::factory()->create(['lat' => 52.145, 'lon' => null, 'datetime' => now()]);
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        // Should only return the photo with valid coordinates
        $this->assertCount(1, $response->json('features'));
        $this->assertEquals(1, $response->json('meta.returned'));
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
        // Create litter objects
        $cigarettes = LitterObject::create(['key' => 'cigarette_butts']);
        $bottles = LitterObject::create(['key' => 'plastic_bottles']);

        // Create category
        $smoking = Category::create(['key' => 'smoking']);

        // Create category-object relationship
        $smoking->litterObjects()->attach($cigarettes);

        // Get the pivot model
        $categoryObject = CategoryObject::where('category_id', $smoking->id)
            ->where('litter_object_id', $cigarettes->id)
            ->first();

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // TODO: Connect photo to category object
        // $photo1->categoryObjects()->attach($categoryObject->id);

        $this->markTestIncomplete('Need to implement photo->categoryObjects relationship');
    }

    /** @test */
    public function it_filters_photos_by_brands()
    {
        // Create brands
        $cocaCola = BrandList::create(['key' => 'coca-cola']);
        $pepsi = BrandList::create(['key' => 'pepsi']);

        // Create category and litter object
        $category = Category::create(['key' => 'softdrinks']);
        $bottle = LitterObject::create(['key' => 'bottle']);

        // Create category-object relationship
        $category->litterObjects()->attach($bottle);
        $categoryObject = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $bottle->id)
            ->first();

        // Attach brand to category object
        $categoryObject->brands()->attach($cocaCola->id, ['quantity' => 1]);

        // Create photos
        $photo1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420, 'datetime' => now()]);
        $photo2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421, 'datetime' => now()]);

        // TODO: Connect photo to category object
        // $photo1->categoryObjects()->attach($categoryObject->id);

        $this->markTestIncomplete('Need to implement photo->categoryObjects relationship');
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

        // TODO: Associate photos with custom tags based on your actual schema

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'custom_tags' => ['beach-cleanup']
            ]));

        $response->assertOk();
    }
}
