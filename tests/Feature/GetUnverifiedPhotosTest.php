<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GetUnverifiedPhotosTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();
    }

    public function test_it_returns_unverified_photos_for_tagging()
    {
        $otherUser = User::factory()->create(['verification_required' => true]);
        $unverifiedUser = User::factory()->create(['verification_required' => true]);
        $imageAttributes = $this->getImageAndAttributes();

        // Some other user uploads a photo, it shouldn't be included in our results
        $this->actingAs($otherUser);

        $this->post('/submit', ['file' => $imageAttributes['file']]);

        $this->actingAs($unverifiedUser);

        // We upload a photo, we expect it to be returned
        $this->post('/submit', ['file' => $imageAttributes['file']]);

        $unverifiedPhoto = $unverifiedUser->fresh()->photos->last();

        // We upload another photo, which gets verified, and shouldn't be returned
        $this->post('/submit', ['file' => $imageAttributes['file']]);

        $verifiedPhoto = $unverifiedUser->fresh()->photos->last();
        $verifiedPhoto->verified = 2;
        $verifiedPhoto->verification = 1;
        $verifiedPhoto->save();

        $response = $this->get('/photos')
            ->assertOk()
            ->json();

        $this->assertEquals(1, $response['remaining'] ?? 0);
        $this->assertEquals(2, $response['total'] ?? 0);
        $this->assertCount(1, $response['photos']['data'] ?? []);
        $this->assertEquals($unverifiedPhoto->id, $response['photos']['data'][0]['id'] ?? 0);

        File::delete($imageAttributes['filepath']);
    }
}
