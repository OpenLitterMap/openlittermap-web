<?php

namespace Tests\Feature\Points;

use App\Models\Photo;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PointsControllerStatsTest extends TestCase
{
    use RefreshDatabase;

    private array $testBbox;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function it_returns_stats_for_same_area_as_points()
    {
        // Arrange
        $user = User::factory()->create();
        $photo = $this->createPhotoWithLocation($user, 0.0, 51.5);

        $smoking = Category::factory()->create(['key' => 'smoking']);
        $butts = LitterObject::factory()->create(['key' => 'butts']);

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $this->getCloId($smoking->id, $butts->id),
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 5
        ]);

        $params = [
            'zoom' => 16,
            'bbox' => $this->testBbox
        ];

        // Act - Get points
        $pointsResponse = $this->getJson('/api/points?' . http_build_query($params));

        // Act - Get stats for same area
        $statsResponse = $this->getJson('/api/points/stats?' . http_build_query($params));

        // Assert
        $pointsResponse->assertOk();
        $statsResponse->assertOk();

        $pointsData = $pointsResponse->json();
        $statsData = $statsResponse->json();

        // Both should find the same photo
        $this->assertEquals(1, count($pointsData['features']));
        $this->assertEquals(1, $statsData['data']['counts']['photos']);

        // Metadata should match
        $this->assertEquals($pointsData['meta']['bbox'], $statsData['meta']['bbox']);
        $this->assertEquals($pointsData['meta']['zoom'], $statsData['meta']['zoom']);
    }

    /** @test */
    public function it_validates_required_parameters()
    {
        // Act & Assert - Missing zoom
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'bbox' => $this->testBbox
            ]));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['zoom']);

        // Act & Assert - Missing bbox
        $response = $this->getJson('/api/points/stats?zoom=16');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bbox.left', 'bbox.right', 'bbox.bottom', 'bbox.top']);

        // Act & Assert - Invalid zoom
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 10, // Below minimum
                'bbox' => $this->testBbox
            ]));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['zoom']);
    }

    /** @test */
    public function it_validates_bbox_size_for_zoom_level()
    {
        // Arrange - Create too large bbox for zoom level
        $largeBbox = [
            'left' => -10,
            'bottom' => 50,
            'right' => 10,
            'top' => 60
        ];

        // Act & Assert
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 18, // High zoom with large bbox
                'bbox' => $largeBbox
            ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bbox']);
    }

    /** @test */
    public function it_applies_category_filters()
    {
        // Arrange
        $user = User::factory()->create();

        $photo1 = $this->createPhotoWithLocation($user, 0.0, 51.5);
        $photo2 = $this->createPhotoWithLocation($user, 0.1, 51.5);

        $smoking = Category::factory()->create(['key' => 'smoking']);
        $food = Category::factory()->create(['key' => 'food']);
        $butts = LitterObject::factory()->create(['key' => 'butts']);
        $wrapper = LitterObject::factory()->create(['key' => 'wrapper']);

        // Photo 1: smoking
        PhotoTag::create([
            'photo_id' => $photo1->id,
            'category_litter_object_id' => $this->getCloId($smoking->id, $butts->id),
            'category_id' => $smoking->id,
            'litter_object_id' => $butts->id,
            'quantity' => 10
        ]);

        // Photo 2: food
        PhotoTag::create([
            'photo_id' => $photo2->id,
            'category_litter_object_id' => $this->getCloId($food->id, $wrapper->id),
            'category_id' => $food->id,
            'litter_object_id' => $wrapper->id,
            'quantity' => 5
        ]);

        // Act - Filter for smoking only
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 16,
                'bbox' => $this->testBbox,
                'categories' => ['smoking']
            ]));

        // Assert
        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(1, $data['data']['counts']['photos']);
        $this->assertEquals(10, $data['data']['counts']['total_objects']);
        $this->assertEquals(['smoking'], $data['meta']['categories']);
    }

    /** @test */
    public function it_applies_date_filters()
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
        $response = $this->getJson('/api/points/stats?' . http_build_query([
            'zoom' => 16,
            'bbox' => $this->testBbox,
            'from' => '2024-06-01',
            'to' => '2024-06-30'
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(1, $data['data']['counts']['photos']);
        $this->assertEquals('2024-06-01', $data['meta']['from']);
        $this->assertEquals('2024-06-30', $data['meta']['to']);
    }

    /** @test */
    public function it_applies_username_filter()
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
        $response = $this->getJson('/api/points/stats?' . http_build_query([
            'zoom' => 16,
            'bbox' => $this->testBbox,
            'username' => 'visible_user'
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(1, $data['data']['counts']['photos']);
        $this->assertEquals('visible_user', $data['meta']['username']);

        // Act - Filter by hidden username
        $response = $this->getJson('/api/points/stats?' . http_build_query([
            'zoom' => 16,
            'bbox' => $this->testBbox,
            'username' => 'hidden_user'
        ]));

        $data = $response->json();
        $this->assertEquals(0, $data['data']['counts']['photos']);
    }

    /** @test */
    public function it_excludes_pagination_parameters_from_stats()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createPhotoWithLocation($user, 0.0, 51.5);

        // Act - Include pagination params (should be ignored)
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 16,
                'bbox' => $this->testBbox,
                'per_page' => 50,
                'page' => 2
            ]));

        // Assert
        $response->assertOk();
        $data = $response->json();

        // Should still return the photo despite pagination params
        $this->assertEquals(1, $data['data']['counts']['photos']);

        // Pagination params should not be in meta
        $this->assertArrayNotHasKey('per_page', $data['meta']);
        $this->assertArrayNotHasKey('page', $data['meta']);
    }

    /** @test */
    public function it_returns_proper_response_structure()
    {
        // Arrange
        $user = User::factory()->create();
        $this->createPhotoWithLocation($user, 0.0, 51.5);

        // Act
        $response = $this->getJson('/api/points/stats?' . http_build_query([
                'zoom' => 16,
                'bbox' => $this->testBbox
            ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'counts' => [
                    'photos',
                    'users',
                    'teams',
                    'picked_up',
                    'not_picked_up',
                    'total_objects',
                    'total_tags'
                ],
                'by_category',
                'by_object',
                'materials',
                'brands',
                'custom_tags',
                'time_histogram'
            ],
            'meta' => [
                'bbox',
                'zoom',
                'generated_at',
                'cached'
            ]
        ]);

        $data = $response->json();

        // Verify meta structure
        $this->assertIsArray($data['meta']['bbox']);
        $this->assertCount(4, $data['meta']['bbox']);
        $this->assertEquals(16, $data['meta']['zoom']);
        $this->assertIsString($data['meta']['generated_at']);
        $this->assertIsBool($data['meta']['cached']);
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
