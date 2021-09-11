<?php

namespace Tests\Feature\Api;

use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class GetUnverifiedPhotosTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
    }

    public function test_it_returns_unverified_photos_for_tagging()
    {
        $user = User::factory()->create(['verification_required' => true]);
        $otherUser = User::factory()->create(['verification_required' => true]);

        // Some other user uploads a photo, it shouldn't be included in our results
        $this->actingAs($otherUser)
            ->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        // We haven't uploaded anything, we expect photos to be empty
        $this->actingAs($user, 'api')
            ->getJson('/api/check-web-photos')
            ->assertOk()
            ->assertJson(['photos' => []]);

        // We upload a photo, we expect it to be returned
        $this->actingAs($user)
            ->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        $unverifiedPhoto = $user->fresh()->photos->first();

        $response = $this
            ->actingAs($user, 'api')
            ->getJson('/api/check-web-photos')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['photos']);
        $this->assertEquals($unverifiedPhoto->id, $response['photos'][0]['id']);

        // We upload another photo, which gets verified, and shouldn't be returned
        $this->actingAs($user)
            ->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        $verifiedPhoto = $user->fresh()->photos->last();
        $verifiedPhoto->verified = 2;
        $verifiedPhoto->verification = 1;
        $verifiedPhoto->save();

        $response = $this
            ->actingAs($user, 'api')
            ->getJson('/api/check-web-photos')
            ->assertOk()
            ->json();

        $this->assertCount(1, $response['photos']);
        $this->assertEquals($unverifiedPhoto->id, $response['photos'][0]['id']);
    }
}
