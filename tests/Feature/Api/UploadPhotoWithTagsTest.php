<?php

namespace Tests\Feature\Api;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Tag;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UploadPhotoWithTagsTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();
    }

    public function test_an_api_user_can_upload_a_photo_with_tags()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        Event::fake([ImageUploaded::class, IncrementPhotoMonth::class]);
        Carbon::setTestNow(now());

        $tag = Tag::factory()->create();
        $user = User::factory()->create([
            'active_team' => Team::factory()
        ]);

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->post('/api/photos/submit-with-tags',
            array_merge(
                $this->getApiImageAttributes($imageAttributes),
                ['tags' => json_encode([$tag->category->name => [$tag->name => 3]])]
            )
        );

        $response->assertOk()->assertJson(['success' => true]);

        $user->refresh();
        $photo = $user->photos->last();

        // Image is uploaded and tags are correct
        Storage::disk('s3')->assertExists($imageAttributes['filepath']);
        Storage::disk('bbox')->assertExists($imageAttributes['filepath']);
        $this->assertCount(1, $user->photos);
        $this->assertEquals($imageAttributes['imageName'], $photo->filename);
        $this->assertEquals($imageAttributes['dateTime'], $photo->datetime);
        $this->assertCount(1, $photo->tags);
        $this->assertEquals(3, $photo->tags->first()->pivot->quantity);

        Event::assertDispatched(ImageUploaded::class);
        Event::assertDispatched(IncrementPhotoMonth::class);
    }

    public function test_a_photo_can_be_marked_as_picked_up_or_not()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $imageAttributes = $this->getImageAndAttributes();
        $tag = Tag::factory()->create();

        // User marks the litter as picked up -------------------
        $this->post('/api/photos/submit-with-tags',
            array_merge($this->getApiImageAttributes($imageAttributes), [
                'tags' => json_encode([$tag->category->name => [$tag->name => 3]]),
                'picked_up' => true
            ])
        );

        $this->assertTrue($user->fresh()->photos->last()->picked_up);

        // User marks the litter as not picked up -------------------
        $this->post('/api/photos/submit-with-tags',
            array_merge($this->getApiImageAttributes($imageAttributes), [
                'tags' => json_encode([$tag->category->name => [$tag->name => 3]]),
                'picked_up' => false
            ])
        );

        $this->assertFalse($user->fresh()->photos->last()->picked_up);

        // User doesn't indicate whether litter is picked up -------------------
        // So it should default to user's predefined settings
        $user->items_remaining = false;
        $user->save();
        $this->post('/api/photos/submit-with-tags',
            array_merge($this->getApiImageAttributes($imageAttributes), [
                'tags' => json_encode([$tag->category->name => [$tag->name => 3]]),
            ])
        );

        $this->assertTrue($user->fresh()->photos->last()->picked_up);
    }

    public function validationDataProvider(): array
    {
        return [
            [
                'fields' => [],
                'errors' => ['photo', 'lat', 'lon', 'date', 'tags'],
            ],
            [
                'fields' => ['photo' => 'validImage', 'lat' => 5, 'lon' => 5, 'date' => now()->toDateTimeString(), 'tags' => 'test'],
                'errors' => ['tags']
            ],
        ];
    }

    /**
     * @dataProvider validationDataProvider
     */
    public function test_the_uploaded_photo_and_tags_are_validated($fields, $errors)
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        if (($fields['photo'] ?? null) == 'validImage') {
            $fields['photo'] = $this->getApiImageAttributes($this->getImageAndAttributes());
        }

        $this->postJson('/api/photos/submit-with-tags', $fields)
            ->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    public function test_the_uploaded_photo_needs_to_be_geotagged()
    {
        $tag = Tag::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->postJson('/api/photos/submit-with-tags', [
            'photo' => UploadedFile::fake()->image('some.pdf'),
            'lat' => 5, 'lon' => 5, 'date' => now()->toDateTimeString(),
            'tags' => json_encode([$tag->category->name => [$tag->name => 3]])
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_lat_and_lon_need_to_be_floats()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $this->actingAs($user, 'api');

        $this->postJson('/api/photos/submit-with-tags', [
            'photo' => $this->getApiImageAttributes($this->getImageAndAttributes()),
            'lat' => 'asdf', 'lon' => 'asdfasdf', 'date' => now()->toDateTimeString(),
            'tags' => json_encode([$tag->category->name => [$tag->name => 3]])
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lon']);
    }
}
