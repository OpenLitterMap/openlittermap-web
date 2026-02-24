<?php

namespace Tests\Feature\Api\Photos;

use App\Events\ImageDeleted;
use App\Models\Users\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class DeletePhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
    }

    public function test_a_user_can_delete_a_photo()
    {
        // User uploads a photo
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        // We make sure it exists
        Storage::disk('s3')->assertExists($imageAttributes['filepath']);
        Storage::disk('bbox')->assertExists($imageAttributes['filepath']);
        $user->refresh();
        $this->assertCount(1, $user->photos);
        $photo = $user->photos->last();

        // User then deletes the photo
        $this->delete('/api/photos/delete', [
            'photoId' => $photo->id
        ])->assertOk();

        Storage::disk('s3')->assertMissing($imageAttributes['filepath']);
        Storage::disk('bbox')->assertMissing($imageAttributes['filepath']);
        $this->assertCount(0, $user->photos()->withoutTrashed()->get());
        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
    }

    public function test_processed_metrics_are_cleared_when_a_user_deletes_a_photo()
    {
        // User uploads a photo
        $user = User::factory()->create();
        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($this->getImageAndAttributes())
        );
        $photo = $user->fresh()->photos->last();

        // Simulate that the photo was tagged and processed (v5: XP set on tag verification)
        $photo->update([
            'processed_at' => now(),
            'processed_xp' => 1,
            'processed_tags' => json_encode(['objects' => ['butts' => 1]]),
        ]);

        // User then deletes the photo
        $this->delete('/api/photos/delete', ['photoId' => $photo->id])->assertOk();

        // v5: MetricsService clears processed_* fields on deletion
        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
        $deletedPhoto = \App\Models\Photo::withTrashed()->find($photo->id);
        $this->assertNull($deletedPhoto->processed_at);
        $this->assertNull($deletedPhoto->processed_xp);
        $this->assertNull($deletedPhoto->processed_tags);
    }

    public function test_it_fires_image_deleted_event()
    {
        Event::fake(ImageDeleted::class);

        // User uploads a photo
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // User then deletes the photo
        $this->delete('/api/photos/delete', [
            'photoId' => $photo->id
        ]);

        Event::assertDispatched(
            ImageDeleted::class,
            function (ImageDeleted $e) use ($user, $photo) {
                return
                    $user->is($e->user) &&
                    $photo->country_id === $e->countryId &&
                    $photo->state_id === $e->stateId &&
                    $photo->city_id === $e->cityId;
            }
        );
    }

    public function test_unauthorized_users_cannot_delete_photos()
    {
        // Unauthenticated users ---------------------
        $response = $this->deleteJson('/api/photos/delete', [
            'photoId' => 1
        ]);

        $response->assertUnauthorized();

        // User uploads a photo ----------------------
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // Another user tries to delete it ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser, 'api');

        $response = $this->delete('/api/photos/delete', [
            'photoId' => $photo->id
        ]);

        $response->assertForbidden();
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $response = $this->delete('/api/photos/delete', [
            'photoId' => 0
        ]);

        $response->assertStatus(403);
    }
}
