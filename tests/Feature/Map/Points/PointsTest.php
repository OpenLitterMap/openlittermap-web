<?php

namespace Tests\Feature\Map\Points;

use Tests\TestCase;
use App\Models\Photo;
use App\Models\Users\User;
use App\Models\Teams\Team;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private $endpoint = '/api/v3/points';

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
                'meta' => ['total', 'per_page', 'current_page']
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
            'verified' => 2
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
        $inside1 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420]);
        $inside2 = Photo::factory()->create(['lat' => 52.146, 'lon' => 4.421]);
        $outside1 = Photo::factory()->create(['lat' => 52.160, 'lon' => 4.420]); // North
        $outside2 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.450]); // East
        $outside3 = Photo::factory()->create(['lat' => 52.130, 'lon' => 4.420]); // South
        $outside4 = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.390]); // West

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
        $smoking = Category::create(['key' => 'smoking', 'name' => 'Smoking']);
        $alcohol = Category::create(['key' => 'alcohol', 'name' => 'Alcohol']);
        $food = Category::create(['key' => 'food', 'name' => 'Food']);

        // Create photos with different categories
        $photoSmoking = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420]);
        PhotoTag::create([
            'photo_id' => $photoSmoking->id,
            'category_id' => $smoking->id,
            'quantity' => 1
        ]);

        $photoAlcohol = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421]);
        PhotoTag::create([
            'photo_id' => $photoAlcohol->id,
            'category_id' => $alcohol->id,
            'quantity' => 1
        ]);

        $photoFood = Photo::factory()->create(['lat' => 52.145, 'lon' => 4.422]);
        PhotoTag::create([
            'photo_id' => $photoFood->id,
            'category_id' => $food->id,
            'quantity' => 1
        ]);

        // Request only smoking and alcohol
        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'categories' => ['smoking', 'alcohol']
            ]));

        $response->assertOk();

        $ids = collect($response->json('features'))->pluck('properties.id');

        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($photoSmoking->id));
        $this->assertTrue($ids->contains($photoAlcohol->id));
        $this->assertFalse($ids->contains($photoFood->id));
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
            'lon' => 4.420
        ]);

        $photo2 = Photo::factory()->create([
            'user_id' => $user2->id,
            'lat' => 52.145,
            'lon' => 4.421
        ]);

        $photo3 = Photo::factory()->create([
            'user_id' => $user3->id,
            'lat' => 52.145,
            'lon' => 4.422
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
            'lon' => 4.420
        ]);

        Photo::factory()->create([
            'user_id' => $userPrivate->id,
            'lat' => 52.145,
            'lon' => 4.421
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
        $verifiedPhoto = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'verified' => 2,
            'filename' => 'verified-photo.jpg'
        ]);

        $unverifiedPhoto = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.421,
            'verified' => 0,
            'filename' => 'unverified-photo.jpg'
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
            'remaining' => false
        ]);

        $notPickedUp = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.421,
            'remaining' => true
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
                'lon' => 4.420 + ($i * 0.001)
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
        $this->assertEquals(1, $response->json('meta.current_page'));
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

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bbox']);
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

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bbox']);
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
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420]);

        $params = [
            'zoom' => 17,
            'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
        ];

        // First request
        $response1 = $this->getJson($this->endpoint . '?' . http_build_query($params));
        $response1->assertOk();

        // Modify database (should not affect cached response)
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.421]);

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
            'lon' => 4.420
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
            'lon' => 4.421
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
            'lon' => 4.430   // Exactly on right edge
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
            'lon' => 4.420
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
    public function it_handles_null_datetime_gracefully()
    {
        $photo = Photo::factory()->create([
            'lat' => 52.145,
            'lon' => 4.420,
            'datetime' => null
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        $feature = $response->json('features.0');
        $this->assertArrayHasKey('datetime', $feature['properties']);
    }

    /** @test */
    public function it_includes_comprehensive_metadata()
    {
        Photo::factory()->count(5)->create([
            'lat' => 52.145,
            'lon' => 4.420
        ]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15],
                'per_page' => 3,
                'categories' => ['smoking'],
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
                    'categories',
                    'from',
                    'to',
                    'generated_at'
                ]
            ]);

        $meta = $response->json('meta');
        $this->assertEquals([4.41, 52.14, 4.43, 52.15], $meta['bbox']);
        $this->assertEquals(17, $meta['zoom']);
        $this->assertEquals(['smoking'], $meta['categories']);
        $this->assertEquals('2024-01-01', $meta['from']);
        $this->assertEquals('2024-12-31', $meta['to']);
    }

    /** @test */
    public function it_handles_null_coordinates_gracefully()
    {
        // Create photos with null coordinates
        Photo::factory()->create(['lat' => null, 'lon' => 4.420]);
        Photo::factory()->create(['lat' => 52.145, 'lon' => null]);
        Photo::factory()->create(['lat' => 52.145, 'lon' => 4.420]);

        $response = $this->getJson($this->endpoint . '?' . http_build_query([
                'zoom' => 17,
                'bbox' => ['left' => 4.41, 'bottom' => 52.14, 'right' => 4.43, 'top' => 52.15]
            ]));

        $response->assertOk();

        // Should only return the photo with valid coordinates
        $this->assertCount(1, $response->json('features'));
        $this->assertEquals(1, $response->json('meta.returned'));
    }
}
