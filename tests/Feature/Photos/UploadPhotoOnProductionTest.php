<?php

namespace Tests\Feature\Photos;

use App\Models\User\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

use App\Models\Photo;

class UploadPhotoOnProductionTest extends TestCase
{
    use WithoutMiddleware;
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();
    }

    public function test_it_throws_server_error_when_user_uploads_photos_with_the_same_datetime_on_production()
    {
        $imageAndAttributes = $this->getImageAndAttributes();
        Carbon::setTestNow(now());
        $user = User::factory()->create();
        $this->actingAs($user);

        Photo::factory()->create([
            'user_id' => $user->id,
            'datetime' => now()
        ]);

        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->post('/submit', [
            'file' => $imageAndAttributes['file'],
        ]);

        $response->assertStatus(500);
        $response->assertSee('Server Error');
    }

    public function test_it_does_not_allow_uploading_photos_more_than_once_in_the_mobile_app()
    {
        Carbon::setTestNow(now());

        $user = User::factory()->create(['id' => 2]);

        $this->actingAs($user, 'api');

        Photo::factory()->create([
            'user_id' => $user->id,
            'datetime' => now()
        ]);

        app()->detectEnvironment(function () {
            return 'production';
        });

        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $response->assertOk();
        $response->assertJson([
            'success' => false,
            'msg' => "photo-already-uploaded"
        ]);
    }
}
