<?php

namespace Tests\Feature;

use App\Models\User\User;
use Tests\TestCase;

use App\Models\Photo;

use Illuminate\Foundation\Testing\RefreshDatabase;

class UploadPhotoTest extends TestCase
{
    /** @test */
    public function a_guest_can_visit_the_homepage ()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /** @test */
    public function a_user_can_upload_a_photo ()
    {
        $user = factory(User::class)->create();

        $response = $this->post('/submit', [
            'file' => 'test.png'
        ]);

//        $response->assertOk();
        $response->assertJsonCount(1, Photo::all());
//        $response->assertStatus(200);
    }
}
