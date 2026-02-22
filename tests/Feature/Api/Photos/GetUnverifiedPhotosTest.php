<?php

namespace Tests\Feature\Api\Photos;

use App\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('deprecated')]
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

        // Other user uploads a photo (should be ignored)
        $this->actingAs($otherUser)
            ->post('/submit', ['photo' => $this->getImageAndAttributes()['file']]);

        // Assert no photos are returned for our test user yet
        $this->actingAs($user, 'api')
            ->getJson('/api/check-web-photos')
            ->assertOk()
            ->assertJson(['photos' => []]);

        // Our user uploads an unverified photo
        $this->actingAs($user)->post('/submit', ['photo' => $this->getImageAndAttributes()['file']]);
        $firstPhotoId = $user->fresh()->photos()->orderBy('id')->first()->id;

        // Then uploads a second, which we mark as verified
        $this->actingAs($user)->post('/submit', ['photo' => $this->getImageAndAttributes()['file']]);
        $secondPhoto = $user->fresh()->photos()->orderByDesc('id')->first();
        $secondPhoto->verified = 2;
        $secondPhoto->save();

        // Final check: only the first unverified photo should be returned
        $response = $this->actingAs($user, 'api')
            ->getJson('/api/check-web-photos')
            ->assertOk()
            ->json();

//        $this->assertCount(1, $response['photos']);
//        $this->assertEquals($firstPhotoId, $response['photos'][0]['id']);
    }
}
