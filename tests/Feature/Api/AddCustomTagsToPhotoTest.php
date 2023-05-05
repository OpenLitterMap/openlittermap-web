<?php

namespace Tests\Feature\Api;

use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class AddCustomTagsToPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

//    public function validationDataProvider(): array
//    {
//        return [
//            ['tags' => ['tag1', 'Tag1'], 'errors' => ['custom_tags.0', 'custom_tags.1']],// uniqueness
//            ['tags' => ['ta'], 'errors' => ['custom_tags.0']], // min length 3
//            ['tags' => [str_repeat('a', 101)], 'errors' => ['custom_tags.0']], // max length 100
//            ['tags' => ['tag1', 'tag2', 'tag3', 'tag4'], 'errors' => ['custom_tags']], // max 3 tags
//        ];
//    }

//    public function test_a_user_can_add_custom_tags_to_a_photo()
//    {
//        /** @var User $user */
//        $user = User::factory()->create();
//        $this->actingAs($user, 'api');
//        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));
//        $photo = $user->fresh()->photos->last();
//        $this->assertEquals(1, $user->fresh()->xp);
//
//        $this->post('/api/add-tags', [
//            'photo_id' => $photo->id,
//            'custom_tags' => ['tag1', 'tag2', 'tag3']
//        ])->assertOk();
//
//        $this->assertEquals(['tag1', 'tag2', 'tag3'], $photo->fresh()->customTags->pluck('tag')->toArray());
//        $this->assertEquals(4, $user->fresh()->xp); // 1 + 3
//    }

//    /**
//     * @dataProvider validationDataProvider
//     */
//    public function test_it_validates_the_custom_tags($tags, $errors)
//    {
//        /** @var User $user */
//        $user = User::factory()->create();
//        $this->actingAs($user, 'api');
//        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));
//        $photo = $user->fresh()->photos->last();
//
//        $response = $this->postJson('/api/add-tags', [
//            'photo_id' => $photo->id,
//            'custom_tags' => $tags
//        ]);
//
//        $response->assertStatus(422);
//        $response->assertJsonValidationErrors($errors);
//        $this->assertCount(0, $photo->fresh()->customTags);
//    }
}
