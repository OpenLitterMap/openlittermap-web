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

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);

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

    public function test_delete_decrements_user_xp(): void
    {
        $user = User::factory()->create(['xp' => 50]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => now(),
            'processed_xp' => 20,
            'processed_tags' => json_encode(['objects' => [1 => 3]]),
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(30, $user->xp);
    }

    public function test_delete_unprocessed_photo_does_not_decrement_xp(): void
    {
        $user = User::factory()->create(['xp' => 5]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => null,
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $user->refresh();
        $this->assertEquals(5, $user->xp);
    }

    public function test_processed_photo_is_hard_deleted_with_metrics_reversed(): void
    {
        $user = User::factory()->create(['xp' => 10]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => now(),
            'processed_xp' => 3,
            'processed_tags' => json_encode(['objects' => ['butts' => 1]]),
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        $this->assertNull(Photo::withTrashed()->find($photo->id));

        $user->refresh();
        $this->assertEquals(7, $user->xp);
    }

    public function test_unprocessed_photo_is_hard_deleted(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'processed_at' => null,
        ]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        $this->assertNull(Photo::withTrashed()->find($photo->id));
    }

    public function test_photo_tags_are_cascade_deleted(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        // Create a photo_tag manually
        \DB::table('photo_tags')->insert([
            'photo_id' => $photo->id,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id]);

        $this->actingAs($user)
            ->post('/api/profile/photos/delete', ['photoid' => $photo->id])
            ->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        $this->assertDatabaseMissing('photo_tags', ['photo_id' => $photo->id]);
    }
}
