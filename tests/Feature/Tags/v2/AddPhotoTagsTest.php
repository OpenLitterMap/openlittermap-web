<?php

namespace Tests\Feature\Tags\v2;

use App\Actions\Tags\AddTagsToPhotoActionNew;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\User\User;
use App\Actions\Badges\CheckLocationTypeAward;
use App\Actions\Locations\UpdateLeaderboardsForLocationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddPhotoTagsTest extends TestCase
{
    use RefreshDatabase;

    protected $action;

    public function setUp(): void
    {
        parent::setUp();

        $updateLeaderboards = \Mockery::mock(UpdateLeaderboardsForLocationAction::class);
        $updateLeaderboards->shouldReceive('updateLeaderboardsAndRewardXP')->andReturnTrue();

        $checkAward = \Mockery::mock(CheckLocationTypeAward::class);
        $checkAward->shouldReceive('checkLandUseAward')->andReturnTrue();

        $this->action = new AddTagsToPhotoActionNew($updateLeaderboards, $checkAward);
    }

    /** @test */
    public function it_adds_basic_category_and_object_tags_to_a_photo()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $category->litterObjects()->attach($object);

        $photo = Photo::factory()->create();

        $tags = [[
            'category' => ['id' => $category->id],
            'object' => ['id' => $object->id],
            'quantity' => 2,
            'picked_up' => true,
        ]];

        $photoTags = $this->action->run($user->id, $photo->id, $tags);

        $this->assertCount(1, $photoTags);
        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 2,
            'picked_up' => 1,
        ]);
    }

    /** @test */
    public function it_throws_an_exception_if_object_is_not_in_category()
    {
        $this->expectException(\Exception::class);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $object = LitterObject::factory()->create(); // not attached to category
        $photo = Photo::factory()->create();

        $tags = [[
            'category' => ['id' => $category->id],
            'object' => ['id' => $object->id],
        ]];

        $this->action->run($user->id, $photo->id, $tags);
    }

    /** @test */
    public function it_creates_custom_tag_and_sets_primary()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $tags = [[
            'custom' => 'illegal_dumping'
        ]];

        $photoTags = $this->action->run($user->id, $photo->id, $tags);

        $this->assertDatabaseHas('custom_tags_new', ['key' => 'illegal_dumping']);
        $this->assertEquals(
            'illegal_dumping',
            $photoTags[0]->fresh()->primaryCustomTag?->key
        );
    }

    /** @test */
    public function it_attaches_extra_tags_for_brands_materials_and_customs()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $category = Category::factory()->create();
        $object = LitterObject::factory()->create();
        $category->litterObjects()->attach($object);

        $material = Materials::factory()->create();
        $brand = BrandList::factory()->create();
        $custom = CustomTagNew::factory()->create();

        $tags = [[
            'category' => ['id' => $category->id],
            'object' => ['id' => $object->id],
            'materials' => [['id' => $material->id]],
            'brands' => [['id' => $brand->id, 'key' => $brand->key]],
            'custom_tags' => [['key' => $custom->key]],
        ]];

        $photoTags = $this->action->run($user->id, $photo->id, $tags);

        $this->assertCount(1, $photoTags);
        $this->assertDatabaseHas('photo_tag_extra_tags', ['tag_type' => 'material', 'tag_type_id' => $material->id]);
        $this->assertDatabaseHas('photo_tag_extra_tags', ['tag_type' => 'brand', 'tag_type_id' => $brand->id]);
        $this->assertDatabaseHas('photo_tag_extra_tags', ['tag_type' => 'custom_tag', 'tag_type_id' => $custom->id]);
    }

    /** @test */
    public function it_sets_created_by_when_creating_new_custom_tag()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $tags = [[ 'custom' => 'illegal_dumping' ]];

        $this->action->run($user->id, $photo->id, $tags);

        $this->assertDatabaseHas('custom_tags_new', [
            'key' => 'illegal_dumping',
            'created_by' => $user->id,
        ]);
    }

    /** @test */
    public function it_does_not_override_created_by_for_existing_custom_tags()
    {
        $otherUser = User::factory()->create(['id' => 999]);
        $existing = CustomTagNew::factory()->create(['key' => 'littering', 'created_by' => $otherUser->id]);
        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $this->action->run($user->id, $photo->id, [[ 'custom' => 'littering' ]]);

        $this->assertEquals($otherUser->id, $existing->fresh()->created_by);
    }

    /** @test */
    public function it_strips_html_and_whitespace_from_custom_tags()
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $this->action->run($user->id, $photo->id, [[
            'custom_tags' => [['key' => ' <b>neat_tag</b> ']]
        ]]);

        $this->assertDatabaseHas('custom_tags_new', ['key' => 'neat_tag']);
    }

    /** @test */
    public function it_throws_for_invalid_custom_tag()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid custom tag.');

        $user = User::factory()->create();
        $photo = Photo::factory()->create();

        $this->action->run($user->id, $photo->id, [[
            'custom_tags' => [['key' => '🔥']]
        ]]);
    }

}
