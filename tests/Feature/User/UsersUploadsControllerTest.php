<?php

namespace Tests\Feature\User;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UsersUploadsControllerTest extends TestCase
{
    use HasPhotoUploads;

    protected User $user;
    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->setUpPhotoUploads();
        $this->imageAndAttributes = $this->getImageAndAttributes();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function test_index_returns_user_photos(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos');

        $response->assertOk()
            ->assertJsonStructure([
                'photos',
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /** @test */
    public function test_index_filters_by_picked_up_true(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);
        $this->addTagToPhoto($photo, pickedUp: true);

        $photoNotPickedUp = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(),
            $this->user
        );
        $this->addTagToPhoto($photoNotPickedUp, pickedUp: false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?picked_up=true');

        $response->assertOk();
        $photos = $response->json('photos');
        $this->assertCount(1, $photos);
        $this->assertEquals($photo->id, $photos[0]['id']);
    }

    /** @test */
    public function test_index_filters_by_picked_up_false(): void
    {
        $photoPickedUp = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);
        $this->addTagToPhoto($photoPickedUp, pickedUp: true);

        $photoNotPickedUp = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(),
            $this->user
        );
        $this->addTagToPhoto($photoNotPickedUp, pickedUp: false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?picked_up=false');

        $response->assertOk();
        $photos = $response->json('photos');
        $this->assertCount(1, $photos);
        $this->assertEquals($photoNotPickedUp->id, $photos[0]['id']);
    }

    /** @test */
    public function test_index_returns_all_photos_without_picked_up_filter(): void
    {
        $photo1 = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);
        $this->addTagToPhoto($photo1, pickedUp: true);

        $photo2 = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(),
            $this->user
        );
        $this->addTagToPhoto($photo2, pickedUp: false);

        $photo3 = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(),
            $this->user
        );
        $this->addTagToPhoto($photo3, pickedUp: null);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos');

        $response->assertOk();
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    /** @test */
    public function test_index_filters_by_tagged_state(): void
    {
        $taggedPhoto = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);
        $taggedPhoto->update(['summary' => 'smoking: butts x1']);

        $untaggedPhoto = $this->createPhotoFromImageAttributes(
            $this->getImageAndAttributes(),
            $this->user
        );

        // Filter: tagged only
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tagged=true');
        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($taggedPhoto->id, $response->json('photos.0.id'));

        // Filter: untagged only
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?tagged=false');
        $response->assertOk();
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertEquals($untaggedPhoto->id, $response->json('photos.0.id'));
    }

    /** @test */
    public function test_index_respects_per_page_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->createPhotoFromImageAttributes($this->getImageAndAttributes(), $this->user);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('photos'));
        $this->assertEquals(3, $response->json('pagination.total'));
        $this->assertEquals(2, $response->json('pagination.last_page'));
    }

    /** @test */
    public function test_index_returns_new_tags_with_picked_up_status(): void
    {
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $this->user);
        $this->addTagToPhoto($photo, pickedUp: true);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos');

        $response->assertOk();
        $tags = $response->json('photos.0.new_tags');
        $this->assertNotEmpty($tags);
        $this->assertSame(1, $tags[0]['picked_up']);
    }

    /** @test */
    public function test_stats_endpoint_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v3/user/photos/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'totalPhotos',
                'totalTags',
                'leftToTag',
                'taggedPercentage',
            ]);
    }

    /** @test */
    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v3/user/photos');
        $response->assertUnauthorized();
    }

    private function addTagToPhoto(Photo $photo, ?bool $pickedUp): void
    {
        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();

        $clo = CategoryObject::where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'category_litter_object_id' => $clo->id,
            'quantity' => 1,
            'picked_up' => $pickedUp,
        ]);
    }
}
