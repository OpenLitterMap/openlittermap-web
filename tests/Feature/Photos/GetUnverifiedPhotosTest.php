<?php

namespace Tests\Feature\Photos;

use App\Models\CustomTag;
use App\Models\Photo;
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
        $this->actingAs($otherUser);

        $this->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        $this->actingAs($user);

        // We upload a photo, we expect it to be returned
        $this->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        $unverifiedPhoto = $user->fresh()->photos->first();

        $response = $this->get('/photos')
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['remaining']);
        $this->assertSame(1, $response['total']);
        $this->assertCount(1, $response['photos']['data']);
        $this->assertEquals($unverifiedPhoto->id, $response['photos']['data'][0]['id']);

        // We upload another photo, which gets verified, and shouldn't be returned
        $this->post('/submit', ['file' => $this->getImageAndAttributes()['file']]);

        $verifiedPhoto = $user->fresh()->photos->last();
        $verifiedPhoto->verified = 2;
        $verifiedPhoto->verification = 1;
        $verifiedPhoto->save();

        $response = $this->get('/photos')
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['remaining']);
        $this->assertSame(2, $response['total']);
        $this->assertCount(1, $response['photos']['data']);
        $this->assertEquals($unverifiedPhoto->id, $response['photos']['data'][0]['id']);
    }

    public function test_it_returns_the_current_users_previously_added_custom_tags()
    {
        $user = User::factory()->create();
        Photo::factory()->has(CustomTag::factory(3)->sequence(
            ['tag' => 'custom-1'], ['tag' => 'custom-2'], ['tag' => 'custom-3']
        ))->create(['user_id' => $user->id]);

        $customTags = $this->actingAs($user)
            ->get('/photos')
            ->assertOk()
            ->json('custom_tags');

        $this->assertCount(3, $customTags);
        $this->assertEqualsCanonicalizing(
            ['custom-1', 'custom-2', 'custom-3'],
            $customTags
        );
    }
}
