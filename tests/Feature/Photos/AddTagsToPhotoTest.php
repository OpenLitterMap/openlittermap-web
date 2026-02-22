<?php

namespace Tests\Feature\Photos;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tests the v5 tagging flow via POST /api/v3/tags (PhotoTagsController).
 *
 * Replaces:
 *   - Tests\Feature\Photos\AddTagsToPhotoTest (old web /add-tags route)
 *   - Tests\Feature\Api\Tags\AddTagsToPhotoTest (old api /api/add-tags route)
 *
 * Photos are created via factory — upload flow has its own tests.
 * Metrics are handled by MetricsService via TagsVerifiedByAdmin event.
 */
class AddTagsToPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
        ]);
    }

    // ─── Happy path ───

    public function test_a_user_can_add_tags_to_a_photo()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 3,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $photo->refresh();

        $this->assertCount(1, $photo->photoTags);

        $photoTag = $photo->photoTags->first();
        $this->assertEquals(3, $photoTag->quantity);
        $this->assertTrue((bool) $photoTag->picked_up);

        $this->assertNotNull($photo->summary);
        $this->assertIsArray($photo->summary);
        $this->assertGreaterThan(0, $photo->xp);
    }

    public function test_a_user_can_add_multiple_tags_to_a_photo()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 3,
                    'picked_up' => true,
                ],
                [
                    'category' => 'alcohol',
                    'object' => 'beer_bottle',
                    'quantity' => 5,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $photo->refresh();

        $this->assertCount(2, $photo->photoTags);
        $this->assertArrayHasKey('tags', $photo->summary);
        $this->assertArrayHasKey('totals', $photo->summary);
        $this->assertGreaterThanOrEqual(8, $photo->xp);
    }

    public function test_a_user_can_add_tags_with_materials()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $materialId = \App\Models\Litter\Tags\Materials::first()->id;

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'alcohol',
                    'object' => 'beer_bottle',
                    'quantity' => 2,
                    'picked_up' => false,
                    'materials' => [
                        ['id' => $materialId, 'quantity' => 2],
                    ],
                ],
            ],
        ])->assertOk();

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertNotNull($photoTag);

        $materialExtras = $photoTag->extraTags()->where('tag_type', 'material')->get();
        $this->assertCount(1, $materialExtras);
        $this->assertEquals(2, $materialExtras->first()->quantity);
    }

    // ─── Picked up ───

    public function test_a_photo_can_be_marked_as_picked_up()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'remaining' => 1,
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk();

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertTrue((bool) $photoTag->picked_up);
    }

    public function test_a_photo_can_be_marked_as_not_picked_up()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 1,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertFalse((bool) $photoTag->picked_up);
    }

    // ─── XP & Summary ───

    public function test_xp_is_calculated_from_tags()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 5,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $photo->refresh();

        $this->assertGreaterThanOrEqual(5, $photo->xp);
    }

    // ─── Verification & events ───

    public function test_it_fires_tags_verified_by_admin_event_for_trusted_user()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 3,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk();

        $photo->refresh();

        $this->assertEquals(1, $photo->verification);
        $this->assertEquals(2, $photo->verified);

        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            fn (TagsVerifiedByAdmin $e) => $e->photo_id === $photo->id
        );
    }

    public function test_untrusted_user_tags_require_verification()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        $user = User::factory()->create(['verification_required' => true]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 1,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        $photo->refresh();

        $this->assertEquals(0.1, $photo->verification);

        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    // ─── Authorization ───

    public function test_it_forbids_adding_tags_to_a_verified_photo()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => 1,
        ]);

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 3,
                    'picked_up' => true,
                ],
            ],
        ])->assertForbidden();

        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get());
    }

    public function test_it_forbids_tagging_another_users_photo()
    {
        $user = User::factory()->create();
        $otherPhoto = Photo::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v3/tags', [
            'photo_id' => $otherPhoto->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 3,
                    'picked_up' => true,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_add_tags()
    {
        $photo = Photo::factory()->create();

        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => 'smoking',
                    'object' => 'butts',
                    'quantity' => 1,
                ],
            ],
        ])->assertUnauthorized();
    }

    // ─── Validation ───

    public function test_request_photo_id_is_validated()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Missing photo_id
        $this->postJson('/api/v3/tags', [
            'tags' => [['category' => 'smoking', 'object' => 'butts', 'quantity' => 3]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);

        // Non-existing photo_id
        $this->postJson('/api/v3/tags', [
            'photo_id' => 0,
            'tags' => [['category' => 'smoking', 'object' => 'butts', 'quantity' => 3]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);
    }

    public function test_request_tags_are_validated()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        // Tags is empty
        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);

        // Tags is not an array
        $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => 'asdf',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);
    }
}
