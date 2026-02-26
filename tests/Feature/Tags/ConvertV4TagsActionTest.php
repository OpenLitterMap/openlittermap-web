<?php

namespace Tests\Feature\Tags;

use App\Actions\Tags\ConvertV4TagsAction;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConvertV4TagsActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
        ]);
    }

    public function test_it_converts_v4_tags_to_v5_photo_tags(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $v4Tags = [
            'smoking' => ['butts' => 3],
        ];

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $v4Tags, true);

        $photo->refresh();

        $this->assertTrue($photo->photoTags()->exists());
        $photoTag = $photo->photoTags()->first();
        $this->assertNotNull($photoTag->category_litter_object_id);
        $this->assertNotNull($photoTag->category_id);
        $this->assertNotNull($photoTag->litter_object_id);
        $this->assertEquals(3, $photoTag->quantity);
    }

    public function test_it_creates_summary_and_xp(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $v4Tags = [
            'smoking' => ['butts' => 2],
        ];

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $v4Tags, true);

        $photo->refresh();

        $this->assertNotNull($photo->summary);
        $this->assertIsArray($photo->summary);
        $this->assertArrayHasKey('tags', $photo->summary);
        $this->assertGreaterThan(0, $photo->xp);
    }

    public function test_it_converts_custom_tags_to_extra_tags(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, [], false, ['my-custom-tag']);

        $photo->refresh();

        $this->assertTrue($photo->photoTags()->exists());
        $photoTag = $photo->photoTags()->first();
        $customExtras = $photoTag->extraTags()->where('tag_type', 'custom_tag')->get();
        $this->assertCount(1, $customExtras);
        $this->assertEquals('my-custom-tag', $customExtras->first()->extraTag->key);
    }

    public function test_it_is_idempotent(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $v4Tags = ['smoking' => ['butts' => 1]];

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $v4Tags, true);
        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $v4Tags, true);

        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get());
    }

    public function test_it_skips_unknown_categories(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        $v4Tags = [
            'nonexistent_category' => ['butts' => 1],
        ];

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, $v4Tags, true);

        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get());
    }

    public function test_it_sets_remaining_from_picked_up(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id, 'remaining' => true]);

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, ['smoking' => ['butts' => 1]], true);

        $this->assertFalse((bool) $photo->fresh()->remaining);
    }

    public function test_it_does_not_write_to_v4_category_columns(): void
    {
        Event::fake();

        $user = User::factory()->create(['verification_required' => false]);
        $photo = Photo::factory()->create(['user_id' => $user->id]);

        app(ConvertV4TagsAction::class)->run($user->id, $photo->id, ['smoking' => ['butts' => 3]], true);

        $photo->refresh();

        // v4 category columns should NOT be populated
        $this->assertNull($photo->smoking_id);
    }
}
