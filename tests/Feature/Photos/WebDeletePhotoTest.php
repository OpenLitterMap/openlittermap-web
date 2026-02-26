<?php

namespace Tests\Feature\Photos;

use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebDeletePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');
    }

    public function test_user_can_delete_own_photo_via_profile(): void
    {
        $user = User::factory()->create();

        // Put files on fake S3/bbox disks
        $filepath = '2026/01/01/test-image.jpg';
        Storage::disk('s3')->put($filepath, 'photo-content');
        Storage::disk('bbox')->put($filepath, 'photo-content');

        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'filename' => $filepath,
            'five_hundred_square_filepath' => $filepath,
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);

        Storage::disk('s3')->assertMissing($filepath);
        Storage::disk('bbox')->assertMissing($filepath);
    }

    public function test_user_cannot_delete_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertForbidden();

        $this->assertDatabaseHas('photos', ['id' => $photo->id, 'deleted_at' => null]);
    }

    public function test_delete_decrements_user_counters(): void
    {
        $user = User::factory()->create(['xp' => 5, 'total_images' => 3]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(4, $user->xp);
        $this->assertEquals(2, $user->total_images);
    }

    public function test_processed_photo_has_metrics_reversed(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => now(),
            'processed_xp' => 3,
            'processed_tags' => json_encode(['objects' => ['butts' => 1]]),
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);

        $deletedPhoto = Photo::withTrashed()->find($photo->id);
        $this->assertNull($deletedPhoto->processed_at);
        $this->assertNull($deletedPhoto->processed_xp);
        $this->assertNull($deletedPhoto->processed_tags);
    }

    public function test_unprocessed_photo_skips_metrics_reversal(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => null,
        ]);

        $this->assertNull($photo->processed_at);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertSoftDeleted('photos', ['id' => $photo->id]);
    }
}
