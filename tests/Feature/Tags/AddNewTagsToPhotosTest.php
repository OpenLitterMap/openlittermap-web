<?php

namespace Tests\Feature\Tags;

use App\LitterModels;
use App\Models\User\User;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

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
    public function test_it_adds_tags_to_a_photo (): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->photos->last();

        $category = Category::where('key', 'smoking')->first();
        $object = LitterObject::where('key', 'butts')->first();
        $pickedUp = true;
        $quantity = 3;
        $brand = BrandList::where('key', 'marlboro')->first();
        $materials = Materials::whereIn('key', ['plastic', 'paper'])->get();

        $tags = [
            [
                'category' => ['id' => $category->id],
                'object' => ['id' => $object->id],
                'picked_up' => $pickedUp,
                'quantity' => $quantity,
                'materials' => [
                    ['id' => $materials[0]->id, 'key' => $materials[0]->key],
                    ['id' => $materials[1]->id, 'key' => $materials[1]->key]
                ],
                'brands' => [
                    $brand
                ],
                'custom_tags' => [
                    'new tag 1',
                    'new tag 2'
                ]
            ]
        ];

        $response = $this->post('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => $tags
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'picked_up' => $pickedUp,
            'quantity' => $quantity,
        ]);

        // Retrieve the created photo tag record.
        $photoTag = PhotoTag::where('photo_id', $photo->id)
            ->where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();
        $this->assertNotNull($photoTag);

        // Assert that extra tag records were created for the materials.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::MATERIALS->value,
            'tag_type_id'  => $materials[0]->id,
            'quantity'     => 1,  // assuming a default extra quantity of 1
        ]);
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::MATERIALS->value,
            'tag_type_id'  => $materials[1]->id,
            'quantity'     => 1,
        ]);

        // Assert that an extra tag record was created for the brand.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::BRANDS->value,
            'tag_type_id'  => $brand->id,
            'quantity'     => 1,
        ]);

        // For custom tags, first ensure the tags are created.
        $customTag1 = CustomTagNew::where('key', 'new tag 1')->first();
        $customTag2 = CustomTagNew::where('key', 'new tag 2')->first();
        $this->assertNotNull($customTag1);
        $this->assertNotNull($customTag2);

        // Assert that extra tag records were created for each custom tag.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::CUSTOM_TAGS->value,
            'tag_type_id'  => $customTag1->id,
            'quantity'     => 1,
        ]);
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::CUSTOM_TAGS->value,
            'tag_type_id'  => $customTag2->id,
            'quantity'     => 1,
        ]);
    }

    public function test_it_shows_errors_if_object_does_not_match_category (): void
    {
        $this->seed(GenerateTagsSeeder::class);

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
