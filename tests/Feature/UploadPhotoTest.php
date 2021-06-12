<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use App\Models\Photo;

class UploadPhotoTest extends TestCase
{
    private $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imagePath = storage_path('framework/testing/1x1.jpg');
    }

    public function test_a_user_can_upload_a_photo()
    {
        Storage::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $exifImage = file_get_contents($this->imagePath);

        $file = UploadedFile::fake()->createWithContent(
            'image.jpg',
            $exifImage
        );

        $this->assertEquals(0, $user->has_uploaded);

        $response = $this->post('/submit', [
            'file' => $file,
        ]);

        $response->assertOk()->assertJson(['msg' => 'success']);

        $user->refresh();

        $this->assertEquals(1, $user->has_uploaded);

        // Assert image is 500 x 500
        // assert xp and total_images increase

        $this->assertCount(1, Photo::all());
    }

    public function test_unauthenticated_users_cannot_upload_photos()
    {
        $response = $this->post('/submit', [
            'file' => 'file',
        ]);

        $response->assertRedirect('login');
    }

    public function test_the_uploaded_photo_is_validated()
    {
        Storage::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/submit', ['file' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $nonImage = UploadedFile::fake()->image('some.pdf');

        $this->postJson('/submit', [
            'file' => $nonImage
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_it_throws_server_error_when_user_has_no_images_remaining()
    {
        Storage::fake();

        $user = User::factory()->create([
            'images_remaining' => 0
        ]);

        $this->actingAs($user);

        $image = UploadedFile::fake()->createWithContent(
            'image.jpg',
            file_get_contents($this->imagePath)
        );

        $response = $this->post('/submit', [
            'file' => $image
        ]);

        $response->assertStatus(500);
    }

    public function test_it_throws_server_error_when_photo_has_no_location_data()
    {
        Storage::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $image = UploadedFile::fake()->image('image.jpg');

        $response = $this->post('/submit', [
            'file' => $image
        ]);

        $response->assertStatus(500);
    }

    // Datetime checks

    public function test_it_uploads_the_photo_to_s3_on_production()
    {
        $this->markTestIncomplete();
    }

    public function test_it_throws_server_error_when_user_uploads_the_same_photo_on_production()
    {
        $this->markTestIncomplete();
    }

    public function test_image_uploaded_event_is_fired()
    {
        $this->markTestIncomplete();
    }

    public function test_increment_photo_month_event_is_fired()
    {
        $this->markTestIncomplete();
    }
}
