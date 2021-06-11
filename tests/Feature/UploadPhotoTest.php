<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

use App\Models\Photo;

class UploadPhotoTest extends TestCase
{
    /** @test */
    public function a_user_can_upload_a_photo()
    {
        // need to fake the storage
        // and upload a fake image with location data
        $this->markTestIncomplete();

        $this->withExceptionHandling();

        $user = User::factory()->create();

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('image.jpg');

        $response = $this->post('/submit', [
            'file' => $file,
        ]);

        $response->assertJsonCount(1, Photo::all());
    }
}
