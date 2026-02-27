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

    public function test_replace_tags_deletes_old_tags_and_adds_new(): void
    {
        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        // Add initial tags via POST
        $this->actingAs($user, 'api')->postJson('/api/v3/tags', [
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

        $this->actingAs($user, 'api')->putJson('/api/v3/tags', [
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
        $this->actingAs($user, 'api')->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $cloId, 'quantity' => 1],
            ],
        ])->assertOk();
    }

    public function test_replace_tags_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        $alcohol = Category::firstWhere('key', CategoryKey::Alcohol->value);
        $can = LitterObject::firstWhere('key', 'can');
        $cloId = $this->getCloId($alcohol->id, $can->id);

        $this->actingAs($other, 'api')->putJson('/api/v3/tags', [
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

        $this->actingAs($user, 'api')->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['category_litter_object_id' => $buttsCloId, 'quantity' => 1],
            ],
        ])->assertOk();

        // Old extra tags should be deleted
        $this->assertDatabaseMissing('photo_tag_extra_tags', ['photo_tag_id' => $tag->id]);
    }
}
