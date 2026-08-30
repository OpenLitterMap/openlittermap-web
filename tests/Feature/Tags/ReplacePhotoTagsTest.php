<?php

namespace Tests\Feature\Tags;

use App\Enums\CategoryKey;
use App\Enums\VerificationStatus;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class ReplacePhotoTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);
    }

    /**
     * Characterisation test for a KNOWN, ACCEPTED risk — it asserts today's behaviour so the
     * exposure is visible rather than discovered again later.
     *
     * An unsanctioned (category, object) pairing resolves to no CLO, so an edit round-trips on
     * the legacy payload path, where the recorded category is replaced by
     * `$object->categories()->first()`. Re-saving one of the ~179k historical tags on the 73
     * unsanctioned pairings therefore reclassifies it.
     *
     * The fallback is not removable in isolation: `createTagLegacy` has no CLO to write without
     * it, so dropping it turns the write into a 422 and breaks the legacy-client leniency that
     * `AddNewTagsToPhotosTest` pins. Repairing the 73 pairings is what removes the exposure.
     * When that lands, flip this test to assert the recorded category survives.
     */
    public function test_editing_an_unsanctioned_pairing_currently_reclassifies_it(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $marine = Category::firstWhere('key', CategoryKey::Marine->value);
        $bottle = LitterObject::firstWhere('key', 'bottle');

        // `bottle` is sanctioned under alcohol/softdrinks, never marine — the shape of the 73 gaps.
        $this->assertFalse(
            $bottle->categories()->where('categories.id', $marine->id)->exists(),
            'fixture assumes marine/bottle has no pivot'
        );

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [[
                'category' => ['id' => $marine->id, 'key' => $marine->key],
                'object' => ['id' => $bottle->id, 'key' => $bottle->key],
                'quantity' => 2,
            ]],
        ])->assertOk();

        $tag = PhotoTag::where('photo_id', $photo->id)->firstOrFail();

        $this->assertSame($bottle->id, $tag->litter_object_id);
        $this->assertNotSame($marine->id, $tag->category_id, 'reclassification no longer happens — update this test');
        $this->assertSame($bottle->categories()->first()->id, $tag->category_id);
    }

    public function test_replace_tags_deletes_old_tags_and_adds_new(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        // Add initial tags via POST
        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $cloId, 'quantity' => 2],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('photo_tags', 1);

        // Replace with different tags
        $smoking = Category::firstWhere('key', CategoryKey::Smoking->value);
        $butts = LitterObject::firstWhere('key', 'butts');
        $buttsCloId = $this->getCloId($smoking->id, $butts->id);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $buttsCloId, 'quantity' => 3],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        // Old tags replaced by new
        $this->assertDatabaseCount('photo_tags', 1);
        $tag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertEquals($buttsCloId, $tag->category_litter_object_id);
        $this->assertEquals(3, $tag->quantity);

        // Summary and XP regenerated
        $photo->refresh();
        $this->assertNotNull($photo->summary);
        $this->assertGreaterThan(0, $photo->xp);
    }

    /**
     * Stale mobile catalogs still submit the retired CLO. Same action as POST,
     * different request class — remount onto the survivor rather than 422.
     */
    public function test_replace_tags_writes_a_retired_object_as_the_survivor(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $bottle = LitterObject::firstWhere('key', 'bottle');
        $cloId = $this->getCloId($alcohol->id, $can->id);
        $survivorCloId = $this->getCloId($alcohol->id, $bottle->id);

        $can->update(['retired_at' => now(), 'merged_into_id' => $bottle->id]);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $cloId, 'quantity' => 3],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_litter_object_id' => $survivorCloId,
            'litter_object_id' => $bottle->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
            'litter_object_id' => $can->id,
        ]);
    }

    /**
     * A survivor can itself be retired by a later entry, and the stalest clients hold a key from
     * before either run. The remount follows the chain rather than landing on the middle object,
     * which is being drained in its own right.
     */
    public function test_a_chained_retirement_remounts_onto_the_final_survivor(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $bottle = LitterObject::firstWhere('key', 'bottle');
        $cup = LitterObject::firstWhere('key', 'cup');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $can->update(['retired_at' => now(), 'merged_into_id' => $bottle->id]);
        $bottle->update(['retired_at' => now(), 'merged_into_id' => $cup->id]);

        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $cloId, 'quantity' => 1]],
        ])->assertOk();

        $this->assertDatabaseHas('photo_tags', ['photo_id' => $photo->id, 'litter_object_id' => $cup->id]);
        $this->assertDatabaseMissing('photo_tags', ['photo_id' => $photo->id, 'litter_object_id' => $bottle->id]);
    }

    /** A retirement loop resolves to nothing, so the write is refused rather than looping. */
    public function test_a_retirement_cycle_is_refused(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $bottle = LitterObject::firstWhere('key', 'bottle');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $can->update(['retired_at' => now(), 'merged_into_id' => $bottle->id]);
        $bottle->update(['retired_at' => now(), 'merged_into_id' => $can->id]);

        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $cloId, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('photo_tags', 0);
    }

    /**
     * Once a retirement drops the pivot the id fails `exists` before the action runs, so nothing
     * can name the survivor any more. The copy has to at least tell the client what to do.
     */
    public function test_a_stale_clo_id_tells_the_client_to_refresh(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => 999999, 'quantity' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'tags.0.category_litter_object_id' => 'This tag is no longer available — refresh your tag list.',
            ]);
    }

    public function test_replace_tags_allows_already_tagged_photos(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create([
            'user_id' => $user->id,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
        ]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        // PUT should work even on verified photos
        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $cloId, 'quantity' => 1],
            ],
        ])->assertOk();
    }

    public function test_put_first_time_matches_post_for_trusted_user(): void
    {
        // Q1: PUT on a never-tagged photo must produce the same verified/XP as a
        // first-time POST so the auto-upload flow can tag exclusively via PUT.
        $trusted = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $trusted->id, 'verified' => 0]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $this->actingAs($trusted)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $cloId, 'quantity' => 2]],
        ])->assertOk();

        $photo->refresh();
        // Trusted user → ADMIN_APPROVED, same as a first-time POST
        $this->assertEquals(VerificationStatus::ADMIN_APPROVED->value, $photo->verified->value);
        $this->assertGreaterThan(0, $photo->xp);
        $this->assertNotNull($photo->summary);
        $this->assertDatabaseCount('photo_tags', 1);
    }

    public function test_put_first_time_marks_onboarding_complete(): void
    {
        $user = User::factory()->create([
            'verification_required' => false,
            'onboarding_completed_at' => null,
        ]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $cloId, 'quantity' => 1]],
        ])->assertOk();

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_put_clearing_tags_does_not_mark_onboarding_complete(): void
    {
        $user = User::factory()->create([
            'verification_required' => false,
            'onboarding_completed_at' => null,
        ]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        // Empty tags = clear; should not stamp onboarding
        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [],
        ])->assertOk();

        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_replace_tags_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $this->actingAs($other)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $cloId, 'quantity' => 1],
            ],
        ])->assertForbidden();
    }

    public function test_replace_tags_requires_auth(): void
    {
        $photo = Photo::factory()->create();

        $this->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => 1, 'quantity' => 1]],
        ])->assertUnauthorized();
    }

    public function test_replace_tags_deletes_extra_tags(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        // Create a tag with extra tags manually
        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_litter_object_id' => $cloId,
            'category_id' => $alcohol->id,
            'litter_object_id' => $can->id,
            'quantity' => 1,
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $tag->id,
            'tag_type' => 'brand',
            'tag_type_id' => 1,
            'quantity' => 1,
        ]);

        $this->assertDatabaseCount('photo_tag_extra_tags', 1);

        // Replace tags — old extra tags should be gone
        $smoking = Category::firstWhere('key', CategoryKey::Smoking->value);
        $butts = LitterObject::firstWhere('key', 'butts');
        $buttsCloId = $this->getCloId($smoking->id, $butts->id);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $buttsCloId, 'quantity' => 1],
            ],
        ])->assertOk();

        // Old extra tags should be deleted
        $this->assertDatabaseMissing('photo_tag_extra_tags', ['photo_tag_id' => $tag->id]);
    }
}
