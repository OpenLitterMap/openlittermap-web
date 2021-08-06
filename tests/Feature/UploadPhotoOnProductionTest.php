<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
        Carbon::setTestNow();

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
            'file' => $this->getImageAndAttributes()['file'],
        ]);

        $response->assertStatus(500);
        $response->assertSee('Server Error');
    }
}
