<?php

namespace Tests\Feature\User;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UsersUploadsFiltersTest extends TestCase
{
    use HasPhotoUploads;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->setUpPhotoUploads();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_filter_by_tag_key(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $this->addTagToPhoto($photo, 'smoking', 'butts');

        $photoFood = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $this->addTagToPhoto($photoFood, 'food', 'wrapper');

        // Filter for butts
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tag=butts');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photo->id, $response->json('photos.0.id'));

        // Partial match
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tag=butt');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));

        // No match
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tag=nonexistent');

        $response->assertOk();
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    /** @test */
    public function test_filter_by_custom_tag(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photoTag = $this->addTagToPhoto($photo, 'smoking', 'butts');

        // Create a custom tag and attach it
        $customTagId = DB::table('custom_tags_new')->insertGetId([
            'key' => 'test_custom_tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom_tag',
            'tag_type_id' => $customTagId,
            'quantity' => 1,
        ]);

        $photoNoCustom = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $this->addTagToPhoto($photoNoCustom, 'food', 'wrapper');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?custom_tag=test_custom');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photo->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_filter_by_date_range(): void
    {
        $photoOld = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photoOld->update(['datetime' => '2024-01-15 12:00:00']);

        $photoRecent = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photoRecent->update(['datetime' => '2026-06-15 12:00:00']);

        // Filter date_from only
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?date_from=2026-01-01');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoRecent->id, $response->json('photos.0.id'));

        // Filter date_to only
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?date_to=2025-01-01');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoOld->id, $response->json('photos.0.id'));

        // Combined range
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?date_from=2024-01-01&date_to=2024-12-31');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoOld->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_filter_by_country(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        $otherCountry = Country::factory()->create(['country' => 'Ireland']);
        $otherState = State::factory()->create(['country_id' => $otherCountry->id]);
        $otherCity = City::factory()->create(['state_id' => $otherState->id, 'country_id' => $otherCountry->id]);

        $photoIreland = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photoIreland->update([
            'country_id' => $otherCountry->id,
            'state_id' => $otherState->id,
            'city_id' => $otherCity->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?country=Ireland');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoIreland->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_filter_by_state(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?state=Pennsylvania');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));

        // Non-existent state
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?state=Nonexistent');

        $response->assertOk();
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    /** @test */
    public function test_filter_by_city(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?city=Latimore Township');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /** @test */
    public function test_filter_by_verified_status(): void
    {
        $photoUnverified = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        // Default verified = 0

        $photoVerified = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photoVerified->update(['verified' => 2]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?verified=0');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoUnverified->id, $response->json('photos.0.id'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?verified=2');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photoVerified->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_filter_by_id_with_operators(): void
    {
        $photo1 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo2 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo3 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        // Exact match
        $response = $this->actingAs($this->user)
            ->getJson("/api/v3/user/photos?id={$photo2->id}&id_operator==");

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photo2->id, $response->json('photos.0.id'));

        // Greater than
        $response = $this->actingAs($this->user)
            ->getJson("/api/v3/user/photos?id={$photo1->id}&id_operator=>");

        $response->assertOk();
        $this->assertEquals(2, $response->json('pagination.total'));

        // Less than
        $response = $this->actingAs($this->user)
            ->getJson("/api/v3/user/photos?id={$photo3->id}&id_operator=<");

        $response->assertOk();
        $this->assertEquals(2, $response->json('pagination.total'));

        // Invalid operator defaults to =
        $response = $this->actingAs($this->user)
            ->getJson("/api/v3/user/photos?id={$photo2->id}&id_operator=!=");

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photo2->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_combined_filters(): void
    {
        $photo1 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo1->update(['summary' => 'smoking: butts x1', 'datetime' => '2026-06-15 12:00:00']);
        $this->addTagToPhoto($photo1, 'smoking', 'butts', true);

        $photo2 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo2->update(['summary' => 'food: wrapper x1', 'datetime' => '2026-06-15 12:00:00']);
        $this->addTagToPhoto($photo2, 'food', 'wrapper', false);

        // tagged + picked_up + date range
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tagged=true&picked_up=true&date_from=2026-01-01');

        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($photo1->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_transform_photo_output_structure(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $this->addTagToPhoto($photo, 'smoking', 'butts');

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos');

        $response->assertOk();
        $response->assertJsonStructure([
            'photos' => [
                '*' => [
                    'id',
                    'filename',
                    'datetime',
                    'lat',
                    'lon',
                    'model',
                    'picked_up',
                    'remaining',
                    'verified',
                    'country',
                    'state',
                    'city',
                    'display_name',
                    'team_id',
                    'created_at',
                    'new_tags',
                    'summary',
                    'xp',
                    'total_tags',
                ],
            ],
        ]);

        $photoData = $response->json('photos.0');
        $this->assertEquals($photo->id, $photoData['id']);
        $this->assertIsInt($photoData['verified']);
        $this->assertEquals('United States of America', $photoData['country']);
        $this->assertEquals('Pennsylvania', $photoData['state']);
        $this->assertEquals('Latimore Township', $photoData['city']);
        $this->assertNotEmpty($photoData['new_tags']);
    }

    /** @test */
    public function test_locations_endpoint_returns_hierarchical_structure(): void
    {
        // Create photos in two countries
        $photo1 = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(withAddress: []),
            $this->user
        );

        $country2 = Country::factory()->create(['country' => 'Ireland']);
        $state2 = State::factory()->create(['state' => 'Munster', 'country_id' => $country2->id]);
        $city2a = City::factory()->create(['city' => 'Cork', 'state_id' => $state2->id, 'country_id' => $country2->id]);
        $city2b = City::factory()->create(['city' => 'Limerick', 'state_id' => $state2->id, 'country_id' => $country2->id]);

        $photo2 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo2->update(['country_id' => $country2->id, 'state_id' => $state2->id, 'city_id' => $city2a->id]);

        $photo3 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo3->update(['country_id' => $country2->id, 'state_id' => $state2->id, 'city_id' => $city2b->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/locations');

        $response->assertOk();
        $response->assertJsonStructure([
            'locations' => [
                '*' => [
                    'country',
                    'states' => [
                        '*' => [
                            'state',
                            'cities',
                        ],
                    ],
                ],
            ],
        ]);

        $locations = $response->json('locations');
        $this->assertCount(2, $locations);

        // Find Ireland in results
        $ireland = collect($locations)->firstWhere('country', 'Ireland');
        $this->assertNotNull($ireland);
        $this->assertCount(1, $ireland['states']);
        $this->assertEquals('Munster', $ireland['states'][0]['state']);
        $this->assertCount(2, $ireland['states'][0]['cities']);
        $this->assertContains('Cork', $ireland['states'][0]['cities']);
        $this->assertContains('Limerick', $ireland['states'][0]['cities']);
    }

    /** @test */
    public function test_locations_endpoint_excludes_soft_deleted_photos(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo->delete(); // soft delete

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/locations');

        $response->assertOk();
        $this->assertEmpty($response->json('locations'));
    }

    /** @test */
    public function test_locations_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v3/user/photos/locations');
        $response->assertUnauthorized();
    }

    /** @test */
    public function test_locations_endpoint_empty_for_new_user(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/locations');

        $response->assertOk();
        $this->assertEmpty($response->json('locations'));
    }

    /** @test */
    public function test_stats_with_db_fallback(): void
    {
        // Create photos with total_tags set (simulating tagged photos without Redis)
        $photo1 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        $photo1->update(['summary' => 'smoking: butts x3', 'total_tags' => 3]);

        $photo2 = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        // untagged photo (no summary)

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/stats');

        $response->assertOk();
        $this->assertEquals(2, $response->json('totalPhotos'));
        $this->assertEquals(3, $response->json('totalTags'));
        $this->assertEquals(1, $response->json('leftToTag'));
        $this->assertEquals(50, $response->json('taggedPercentage'));
    }

    /** @test */
    public function test_stats_excludes_verified_jpg_placeholder(): void
    {
        $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        // Create a placeholder photo
        Photo::factory()->for($this->user)->create(['filename' => '/assets/verified.jpg']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/stats');

        $response->assertOk();
        $this->assertEquals(1, $response->json('totalPhotos'));
    }

    /** @test */
    public function test_new_tags_with_extra_tag_only_photo_tag(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);

        // Create a PhotoTag with null CLO (extra-tag-only)
        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'quantity' => 1,
            'picked_up' => true,
        ]);

        // Attach a brand as extra tag
        $brandId = DB::table('brandslist')->insertGetId([
            'key' => 'test_brand',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'brand',
            'tag_type_id' => $brandId,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos');

        $response->assertOk();
        $tags = $response->json('photos.0.new_tags');
        $this->assertCount(1, $tags);
        $this->assertNull($tags[0]['category_litter_object_id']);
        $this->assertArrayNotHasKey('category', $tags[0]);
        $this->assertArrayNotHasKey('object', $tags[0]);
        $this->assertArrayHasKey('extra_tags', $tags[0]);
        $this->assertEquals('brand', $tags[0]['extra_tags'][0]['type']);
    }

    private function addTagToPhoto(Photo $photo, string $categoryKey, string $objectKey, ?bool $pickedUp = null): PhotoTag
    {
        $category = Category::where('key', $categoryKey)->first();
        $object = LitterObject::where('key', $objectKey)->first();

        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        return PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'quantity' => 1,
            'picked_up' => $pickedUp,
        ]);
    }
}
