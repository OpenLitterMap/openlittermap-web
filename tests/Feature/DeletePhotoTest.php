<?php

namespace Tests\Feature;


use App\Models\User\User;
use Tests\TestCase;

class DeletePhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->assertFileExists($imageAttributes['filepath']);
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);
        $this->assertCount(1, $user->photos);
        $photo = $user->photos->last();

        // User then deletes the photo
        $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded); // TODO should it happen?
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);
        $this->assertFileDoesNotExist($imageAttributes['filepath']);
        $this->assertCount(0, $user->photos);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
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
