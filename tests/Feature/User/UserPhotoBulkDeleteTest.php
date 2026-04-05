<?php

namespace Tests\Feature\User;

use App\Models\Photo;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserPhotoBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->user = User::factory()->create([
            'xp' => 10,
        ]);
    }

    public function test_bulk_delete_soft_deletes_user_photos(): void
    {
        $photos = Photo::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'verified' => 0,
            'verification' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/profile/photos/delete', [
                'selectAll' => false,
                'inclIds' => $photos->pluck('id')->toArray(),
                'filters' => [
                    'id' => '',
                    'dateRange' => ['start' => null, 'end' => null],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        foreach ($photos as $photo) {
            $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        }
    }

    public function test_bulk_delete_decrements_user_counters(): void
    {
        $photos = Photo::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'verified' => 0,
            'verification' => 0,
            'processed_at' => now(),
            'processed_xp' => 3,
            'processed_tags' => json_encode(['objects' => [1 => 1]]),
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/user/profile/photos/delete', [
                'selectAll' => false,
                'inclIds' => $photos->pluck('id')->toArray(),
                'filters' => [
                    'id' => '',
                    'dateRange' => ['start' => null, 'end' => null],
                ],
            ])
            ->assertOk();

        $this->user->refresh();
        // 10 - (2 × 3) = 4
        $this->assertEquals(4, $this->user->xp);
    }

    public function test_bulk_delete_cannot_delete_another_users_photos(): void
    {
        $otherUser = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $otherUser->id,
            'verified' => 0,
            'verification' => 0,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/user/profile/photos/delete', [
                'selectAll' => false,
                'inclIds' => [$photo->id],
                'filters' => [
                    'id' => '',
                    'dateRange' => ['start' => null, 'end' => null],
                ],
            ])
            ->assertOk();

        // Photo should NOT be deleted (belongs to another user — filtered out by FilterPhotos trait)
        $this->assertDatabaseHas('photos', ['id' => $photo->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_calls_metrics_reversal_for_processed_photos(): void
    {
        $photo = Photo::factory()->create([
            'user_id' => $this->user->id,
            'verified' => 0,
            'verification' => 0,
            'processed_at' => now(),
            'processed_fp' => 'test_fp',
            'processed_tags' => json_encode([
                'objects' => ['cigarette_butt' => 1],
                'materials' => [],
                'brands' => [],
                'custom_tags' => [],
            ]),
            'processed_xp' => 1,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/user/profile/photos/delete', [
                'selectAll' => false,
                'inclIds' => [$photo->id],
                'filters' => [
                    'id' => '',
                    'dateRange' => ['start' => null, 'end' => null],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
    }

    public function test_bulk_delete_with_select_all(): void
    {
        $photos = Photo::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'verified' => 0,
            'verification' => 0,
        ]);

        // selectAll=true with empty exclIds deletes all
        $this->actingAs($this->user)
            ->postJson('/api/user/profile/photos/delete', [
                'selectAll' => true,
                'exclIds' => [],
                'filters' => [
                    'id' => '',
                    'dateRange' => ['start' => null, 'end' => null],
                ],
            ])
            ->assertOk();

        foreach ($photos as $photo) {
            $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        }
    }
}
