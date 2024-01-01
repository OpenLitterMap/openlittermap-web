<?php

namespace Tests\Feature\Api;

use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UploadPhotoWithCustomTagsTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');
        $this->setImagePath();
    }

//    public function validationDataProvider(): array
//    {
//        return [
//            ['tags' => ['tag1', 'Tag1'], 'errors' => ['custom_tags.0', 'custom_tags.1']],// uniqueness
//            ['tags' => ['ta'], 'errors' => ['custom_tags.0']], // min length 3
//            ['tags' => [str_repeat('a', 101)], 'errors' => ['custom_tags.0']], // max length 100
//            // max 10 tags
//            ['tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6', 'tag7', 'tag8', 'tag9', 'tag10', 'tag11'], 'errors' => ['custom_tags']],
//        ];
//    }

    public function test_an_api_user_can_upload_a_photo_with_custom_tags()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $this->assertSame(0, $user->fresh()->xp);

        $response = $this->post('/api/photos/submit-with-tags', [...$this->getApiImageAttributes($this->getImageAndAttributes()), 'custom_tags' => ['tag1', 'tag2', 'tag3']]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertSame(
            ['tag1', 'tag2', 'tag3'],
            $user->fresh()->photos->last()->customTags->pluck('tag')->toArray()
        );
        $this->assertSame(4, $user->fresh()->xp); // 1 + 3
    }

//    public function test_an_api_user_can_upload_a_photo_with_custom_tags()
//    {
//        /** @var User $user */
//        $user = User::factory()->create();
//        $this->actingAs($user, 'api');
//        $this->assertEquals(0, $user->fresh()->xp);
//
//        $response = $this->post('/api/photos/submit-with-tags', array_merge(
//            $this->getApiImageAttributes($this->getImageAndAttributes()),
//            ['custom_tags' => ['tag1', 'tag2', 'tag3']]
//        ));
//
//        $response->assertOk()->assertJson(['success' => true]);
//
//        $this->assertEquals(
//            ['tag1', 'tag2', 'tag3'],
//            $user->fresh()->photos->last()->customTags->pluck('tag')->toArray()
//        );
//        $this->assertEquals(4, $user->fresh()->xp); // 1 + 3
//    }
//

//    /**
//     * @dataProvider validationDataProvider
//     */
//    public function test_it_validates_the_custom_tags($tags, $errors)
//    {
//        /** @var User $user */
//        $user = User::factory()->create();
//        $this->actingAs($user, 'api');
//
//        $response = $this->postJson('/api/photos/submit-with-tags', array_merge(
//            $this->getApiImageAttributes($this->getImageAndAttributes()),
//            ['custom_tags' => $tags]
//        ));
//
//        // $response->assertStatus(422);
//        // $response->assertJsonValidationErrors($errors);
//        $this->assertCount(0, $user->fresh()->photos);
//    }
}
