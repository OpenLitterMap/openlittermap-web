<?php

namespace Tests\Feature;


use App\Models\User\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeletePhotoOnProductionTest extends TestCase
{
    use WithoutMiddleware;
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();
    }

    public function test_a_user_can_delete_a_photo_from_s3_on_production()
    {
        $this->markTestIncomplete('Need to change the way file path is extracted on production');

        Storage::fake('s3');

        // User uploads a photo
        $user = User::factory()->create();

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        app()->detectEnvironment(function () {
            return 'production';
        });

        $this->post('/submit', ['file' => $imageAttributes['file']]);

        // We make sure it exists
        Storage::disk('s3')->assertExists($imageAttributes['productionImageName']);

        $photo = $user->fresh()->photos->last();

        // User then deletes the photo
        $this->post('/profile/photos/delete', ['photoid' => $photo->id]);

        Storage::disk('s3')->assertMissing($imageAttributes['productionImageName']);
    }
}
