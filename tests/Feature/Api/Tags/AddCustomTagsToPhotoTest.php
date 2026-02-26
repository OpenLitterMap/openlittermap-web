<?php

namespace Tests\Feature\Api\Tags;

use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class AddCustomTagsToPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
        $this->seed(GenerateTagsSeeder::class);

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    public static function validationDataProvider(): array
    {
        return [
            ['tags' => ['tag1', 'Tag1'], 'errors' => ['custom_tags.0', 'custom_tags.1']],// uniqueness
            ['tags' => ['ta'], 'errors' => ['custom_tags.0']], // min length 3
            ['tags' => [str_repeat('a', 101)], 'errors' => ['custom_tags.0']], // max length 100
            ['tags' => ['tag1', 'tag2', 'tag3', 'tag4'], 'errors' => ['custom_tags']], // max 3 tags
        ];
    }

    public function test_a_user_can_add_custom_tags_to_a_photo()
    {
        /** @var User $user */
        $user = User::factory()->create();
        Redis::zrem('xp.users', $user->id);

        $this->actingAs($user, 'api');
        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));

        $photo = $user->fresh()->photos->last();

        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'custom_tags' => ['tag1', 'tag2', 'tag3']
        ])->assertOk();

        // v5: custom tags stored as extra_tags on photo_tags
        $photo->refresh();
        $customTagKeys = $photo->photoTags
            ->flatMap(fn ($pt) => $pt->extraTags->where('tag_type', 'custom_tag'))
            ->map(fn ($extra) => $extra->extraTag?->key)
            ->sort()
            ->values()
            ->toArray();

        $this->assertEquals(['tag1', 'tag2', 'tag3'], $customTagKeys);
    }

    /**
     * @dataProvider validationDataProvider
     */
    public function test_it_validates_the_custom_tags($tags, $errors)
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));

        $photo = $user->fresh()->photos->last();

        $response = $this->postJson('/api/add-tags', [
            'photo_id' => $photo->id,
            'custom_tags' => $tags
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($errors);
        $this->assertCount(0, $photo->fresh()->customTags);
    }
}
