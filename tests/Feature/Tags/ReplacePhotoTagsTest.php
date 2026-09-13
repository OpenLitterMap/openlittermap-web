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

    /** A rejected historical edit must preserve the observation, extras and photo summary. */
    public function test_editing_an_unsanctioned_pairing_is_rejected_without_changing_the_photo(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id, 'summary' => ['existing' => true], 'xp' => 7]);
        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $butts = LitterObject::firstWhere('key', 'butts');
        $this->assertFalse($butts->categories()->where('categories.id', $alcohol->id)->exists());

        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $alcohol->id,
            'litter_object_id' => $butts->id,
            'category_litter_object_id' => null,
            'quantity' => 2,
            'picked_up' => false,
        ]);
        $extra = PhotoTagExtraTags::create([
            'photo_tag_id' => $tag->id, 'tag_type' => 'brand', 'tag_type_id' => 1, 'quantity' => 2,
        ]);
        $beforePhoto = $photo->fresh()->getAttributes();
        $beforeTag = $tag->fresh()->getAttributes();
        $beforeExtra = $extra->fresh()->getAttributes();

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [[
                'category' => ['id' => $alcohol->id, 'key' => $alcohol->key],
                'object' => ['id' => $butts->id, 'key' => $butts->key],
                'quantity' => 3,
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('tags');

        $this->assertSame($beforePhoto, $photo->fresh()->getAttributes());
        $this->assertSame($beforeTag, $tag->fresh()->getAttributes());
        $this->assertSame($beforeExtra, $extra->fresh()->getAttributes());
        $this->assertDatabaseCount('photo_tags', 1);
    }

    /**
     * End to end for the stale mobile client. It caches `/api/tags/all` for days and cannot ship,
     * so after `beer_can → can --type=beer` it still submits the old CLO. The remount must land
     * the write on `can` AND carry `beer`; before, the subtype was lost because the retirement
     * record could not say what the approved split was.
     */
    public function test_a_stale_clo_submission_lands_on_the_survivor_with_the_approved_type(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $other = Category::firstWhere('key', CategoryKey::Other->value);
        $retired = LitterObject::firstOrCreate(['key' => 'beer_can_legacy']);
        $survivor = LitterObject::firstWhere('key', 'can');
        $beer = \App\Models\Litter\Tags\LitterObjectType::firstOrCreate(['key' => 'beer']);

        $staleCloId = $this->getCloId($other->id, $retired->id);
        $survivorCloId = $this->getCloId($other->id, $survivor->id);
        \Illuminate\Support\Facades\DB::table('category_object_types')->insertOrIgnore([
            'category_litter_object_id' => $survivorCloId,
            'litter_object_type_id' => $beer->id,
        ]);
        \App\Models\Litter\Tags\CategoryObject::flushResolverCache();

        $this->artisan('olm:migrate-tag', [
            'retired' => 'beer_can_legacy',
            'desired' => 'can',
            '--type' => 'beer',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $staleCloId, 'quantity' => 1]],
        ])->assertOk();

        $tag = PhotoTag::where('photo_id', $photo->id)->firstOrFail();

        $this->assertSame($survivor->id, $tag->litter_object_id);
        $this->assertSame($beer->id, $tag->litter_object_type_id, 'stale submission must keep the approved subtype');
    }

    /**
     * Two mappings later, the stale client still submits the first CLO. The chain resolves to the
     * final survivor and the subtype has to come from the hop that introduced it, not from the
     * first recorded redirect, which recorded no type.
     */
    public function test_a_stale_clo_two_mappings_old_lands_on_the_final_survivor_with_the_type_introduced_later(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $other = Category::firstWhere('key', CategoryKey::Other->value);

        $a = LitterObject::firstOrCreate(['key' => 'chain_a']);
        $b = LitterObject::firstOrCreate(['key' => 'chain_b']);
        $c = LitterObject::firstOrCreate(['key' => 'chain_c']);
        $beer = \App\Models\Litter\Tags\LitterObjectType::firstOrCreate(['key' => 'beer']);

        $aCloId = $this->getCloId($other->id, $a->id);
        $this->getCloId($other->id, $b->id);
        $cCloId = $this->getCloId($other->id, $c->id);
        \Illuminate\Support\Facades\DB::table('category_object_types')->insertOrIgnore([
            'category_litter_object_id' => $cCloId,
            'litter_object_type_id' => $beer->id,
        ]);
        \App\Models\Litter\Tags\CategoryObject::flushResolverCache();

        $this->artisan('olm:migrate-tag', ['retired' => 'chain_a', 'desired' => 'chain_b', '--apply' => true])->assertExitCode(0);
        $this->artisan('olm:migrate-tag', ['retired' => 'chain_b', 'desired' => 'chain_c', '--type' => 'beer', '--apply' => true])->assertExitCode(0);

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['category_litter_object_id' => $aCloId, 'quantity' => 1]],
        ])->assertOk();

        $tag = PhotoTag::where('photo_id', $photo->id)->firstOrFail();

        $this->assertSame($c->id, $tag->litter_object_id);
        $this->assertSame($beer->id, $tag->litter_object_type_id, 'type introduced on the second hop must survive');
    }

    /**
     * A legacy `{ object, category }` payload names the pairing by keys. After a pure category
     * move that pairing is retired; the write must follow it rather than re-populate the
     * pairing the migration just emptied.
     */
    public function test_a_legacy_write_naming_a_moved_pairing_lands_on_the_target_category(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $other = Category::firstWhere('key', CategoryKey::Other->value);
        $dumping = Category::firstWhere('key', CategoryKey::Dumping->value);
        $object = LitterObject::firstOrCreate(['key' => 'moved_pairing']);

        $sourceCloId = $this->getCloId($other->id, $object->id);
        $targetCloId = $this->getCloId($dumping->id, $object->id);
        \App\Models\Litter\Tags\CategoryObject::whereKey($sourceCloId)->update(['merged_into_clo_id' => $targetCloId]);
        \App\Models\Litter\Tags\CategoryObject::flushResolverCache();

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['object' => 'moved_pairing', 'category' => 'other', 'quantity' => 1]],
        ])->assertOk();

        $tag = PhotoTag::where('photo_id', $photo->id)->firstOrFail();

        $this->assertSame($dumping->id, $tag->category_id, 'write must follow the tombstone, not refill the moved pairing');
        $this->assertSame($targetCloId, $tag->category_litter_object_id);
    }

    public function test_a_legacy_write_naming_a_split_object_carries_the_approved_type(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $other = Category::firstWhere('key', CategoryKey::Other->value);
        $old = LitterObject::firstOrCreate(['key' => 'split_old']);
        $new = LitterObject::firstOrCreate(['key' => 'split_new']);
        $beer = \App\Models\Litter\Tags\LitterObjectType::firstOrCreate(['key' => 'beer']);

        $oldCloId = $this->getCloId($other->id, $old->id);
        $newCloId = $this->getCloId($other->id, $new->id);
        \Illuminate\Support\Facades\DB::table('category_object_types')->insertOrIgnore([
            'category_litter_object_id' => $newCloId,
            'litter_object_type_id' => $beer->id,
        ]);
        $old->update(['retired_at' => now(), 'merged_into_id' => $new->id]);
        \App\Models\Litter\Tags\CategoryObject::whereKey($oldCloId)->update(['merged_into_clo_id' => $newCloId, 'merged_into_type_id' => $beer->id]);
        \App\Models\Litter\Tags\CategoryObject::flushResolverCache();

        $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [['object' => 'split_old', 'category' => 'other', 'quantity' => 1]],
        ])->assertOk();

        $tag = PhotoTag::where('photo_id', $photo->id)->firstOrFail();

        $this->assertSame($new->id, $tag->litter_object_id);
        $this->assertSame($beer->id, $tag->litter_object_type_id, 'legacy payload must pick up the approved subtype');
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
