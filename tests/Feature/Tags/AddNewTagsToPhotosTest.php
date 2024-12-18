<?php

namespace Tests\Feature\Tags;

use App\Models\Category;
use App\Models\Materials;
use App\Models\BrandList;
use App\Models\User\User;
use App\Models\LitterObject;
use Tests\TestCase;
use Tests\Feature\HasPhotoUploads;
use Illuminate\Support\Facades\Storage;
use Database\Seeders\CategoryLitterObjectSeeder;

class AddNewTagsToPhotosTest extends TestCase
{
    use HasPhotoUploads;

    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    /**
     * Test new tagging upload
     */
    public function test_it_adds_all_tags_to_a_photo (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        // User uploads an image
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        $categoryString = 'smoking';
        $objectString = 'butts';
        $pickedUpStatus = 1;
        $quantity = 3;
        $brandString = 'marlboro';
        $materials = [
            'plastic',
            'paper'
        ];

        $tags = [
            [
                'category' => $categoryString,
                'object' => $objectString,
                'picked_up' => $pickedUpStatus,
                'quantity' => $quantity,
                'brand' => $brandString,
                'materials' => [
                    $materials[0],
                    $materials[1]
                ]
            ]
        ];

        $response = $this->post('/api/tags', [
            'photoId' => $photo->id,
            'tags' => $tags
        ]);

        $response->assertStatus(200);

        $category = Category::where('key', $categoryString)->first();
        $litterObject = LitterObject::where('key', $objectString)->first();
        $brand = BrandList::where('key', $brandString)->first();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'object_id' => $litterObject->id,
            'picked_up' => $pickedUpStatus,
            'quantity' => $quantity,
            'brandlist_id' => $brand?->id
        ]);

        $materialOneId = Materials::where('key', $materials[0])->first()->id ?? null;
        $materialTwoId = Materials::where('key', $materials[1])->first()->id ?? null;

        $this->assertDatabaseHas('material_photo_tag', [
            'photo_tag_id' => 1,
            'material_id' => $materialOneId,
        ]);

        $this->assertDatabaseHas('material_photo_tag', [
            'photo_tag_id' => 1,
            'material_id' => $materialTwoId,
        ]);
    }

    public function test_it_shows_errors_if_object_does_not_match_category (): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        // User uploads an image
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        $categoryString = 'alcohol';
        $objectString = 'butts';

        $tags = [
            [
                'category' => $categoryString,
                'object' => $objectString
            ]
        ];

        $response = $this->post('/api/tags', [
            'photoId' => $photo->id,
            'tags' => $tags
        ]);

        $content = json_decode($response->getContent(), true);

        $response->assertStatus(200);

        $this->assertEquals([], $content['photoTags']);
        $this->assertDatabaseEmpty('photo_tags');
        $this->assertCount(1, $content['errors']);
        $this->assertEquals('Category does not contain object', $content['errors'][0]['msg']);
        $this->assertEquals('alcohol', $content['errors'][0]['category']);
        $this->assertEquals('butts', $content['errors'][0]['object']);
    }

    public function test_it_fails_to_upload_if_the_user_does_not_own_the_photo (): void
    {
        // User 1 does not upload an image
        $user1 = User::factory()->create();

        // User 2 uploads an image
        $user2 = User::factory()->create();

        $this->actingAs($user2, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user2->fresh()->photos->last();

        $this->assertEquals($user2->id, $photo->user_id);

        // Log in as user1
        $this->actingAs($user1, 'api');

        $response = $this->post('/api/tags', [
            'photoId' => $photo->id,
            'tags' => [
                'object' => 'butts'
            ]
        ]);

        $response->assertStatus(403);
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthenticated.', $content['msg']);
    }
}
