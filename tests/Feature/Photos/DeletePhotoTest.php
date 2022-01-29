<?php

namespace Tests\Feature\Photos;


use App\Events\ImageDeleted;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
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

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $imageAttributes['file']]);

        // We make sure it exists
        Storage::disk('s3')->assertExists($imageAttributes['filepath']);
        Storage::disk('bbox')->assertExists($imageAttributes['filepath']);
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);
        $this->assertCount(1, $user->photos);
        $photo = $user->photos->last();

        // User then deletes the photo
        $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded); // TODO shouldn't it decrement?
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);
        Storage::disk('s3')->assertMissing($imageAttributes['filepath']);
        Storage::disk('bbox')->assertMissing($imageAttributes['filepath']);
        $this->assertCount(0, $user->photos);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    public function test_leaderboards_are_updated_when_a_user_deletes_a_photo()
    {
        // User uploads a photo
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user)->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);
        $photo = $user->fresh()->photos->last();

        // User has uploaded an image, so their xp is 1
        Redis::zadd("xp.users", 1, $user->id);
        Redis::zadd("xp.country.$photo->country_id", 1, $user->id);
        Redis::zadd("xp.country.$photo->country_id.state.$photo->state_id", 1, $user->id);
        Redis::zadd("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", 1, $user->id);

        // User then deletes the photo
        $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(0, Redis::zscore("xp.users", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $user->id));
    }

    public function test_it_fires_image_deleted_event()
    {
        Event::fake(ImageDeleted::class);

        // User uploads a photo
        $user = User::factory()->create();

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $imageAttributes['file']]);

        $photo = $user->fresh()->photos->last();

        // User then deletes the photo
        $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

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
        $response = $this->post('/profile/photos/delete', ['photoid' => 1]);

        $response->assertRedirect('login');

        // User uploads a photo ----------------------
        $user = User::factory()->create();

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $imageAttributes['file']]);

        $photo = $user->fresh()->photos->last();

        // Another user tries to delete it ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

        $response->assertForbidden();
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/profile/photos/delete', ['photoid' => 0]);

        $response->assertNotFound();
    }
}
