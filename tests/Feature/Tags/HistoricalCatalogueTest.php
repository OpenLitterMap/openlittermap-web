<?php
namespace Tests\Feature\Tags;

use Tests\TestCase;
use App\Models\Litter\Tags\{Category, CategoryObject, LitterObject, PhotoTag};
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\DB;

class HistoricalCatalogueTest extends TestCase
{
    public function test_historical_clos_resolve_but_are_absent_from_all_discovery_paths(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $object = LitterObject::where('key', 'randomLitter')->sole();
        $clo = CategoryObject::where('litter_object_id', $object->id)->sole();
        $this->assertFalse((bool) $clo->is_selectable);
        $this->assertSame($clo->id, CategoryObject::resolveId($clo->category_id, $object->id));
        $this->actingAs($user)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['category_litter_object_id' => $clo->id, 'quantity' => 2]]])->assertOk();
        $all = $this->getJson('/api/tags/all')->assertOk()->json();
        $this->assertNotContains($object->id, array_column($all['objects'], 'id'));
        $this->assertNotContains($clo->id, array_column($all['category_objects'], 'id'));
        $gloves = collect($all['objects'])->firstWhere('key', 'gloves');
        $this->assertSame(['medical'], array_column($gloves['categories'], 'key'));
        $this->getJson('/api/tags?object=randomLitter')->assertOk()->assertJsonPath('tags', []);
        $this->assertStringNotContainsString('randomLitter', $this->getJson('/api/v3/user/top-tags')->assertOk()->getContent());
        $this->putJson('/api/v3/user/quick-tags', ['tags' => [[
            'clo_id' => $clo->id, 'quantity' => 2, 'picked_up' => null, 'materials' => [], 'brands' => [],
        ]]])->assertOk();
        $this->getJson('/api/v3/user/quick-tags')->assertOk()->assertJsonPath('tags.0.clo_id', $clo->id);
        $this->artisan('olm:verify-tag-integrity')->expectsOutputToContain('Historical photo tags: 1')
            ->expectsOutputToContain('Historical quick tags: 1')->assertExitCode(0);
    }

    public function test_object_only_inference_ignores_historical_categories_and_explicit_categories_win(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $user->id]);
        $gloves = LitterObject::where('key', 'gloves')->sole();
        $this->actingAs($user)->postJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['object' => ['id' => $gloves->id]]]])->assertOk();
        $this->assertSame(Category::where('key', 'medical')->value('id'), $photo->photoTags()->sole()->category_id);
        $sanitary = Category::where('key', 'sanitary')->value('id');
        $this->putJson('/api/v3/tags', ['photo_id' => $photo->id, 'tags' => [['object' => ['id' => $gloves->id], 'category_id' => $sanitary]]])->assertOk();
        $this->assertSame($sanitary, $photo->photoTags()->sole()->category_id);
    }

    public function test_preparation_is_idempotent_preserves_data_and_does_not_restore_redirects(): void
    {
        $other = Category::create(['key' => 'other']);
        $old = LitterObject::create(['key' => 'plasticBags']);
        $photo = Photo::factory()->create(['xp' => 123]);
        $tag = PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $other->id, 'litter_object_id' => $old->id, 'quantity' => 2]);
        $before = $tag->fresh()->getAttributes();
        $this->assertNull(CategoryObject::resolveId($other->id, $old->id));
        $this->seed(GenerateTagsSeeder::class);
        $source = CategoryObject::findOrFail(CategoryObject::resolveId($other->id, $old->id));
        $target = CategoryObject::where('category_id', $other->id)->where('litter_object_id', LitterObject::where('key', 'plastic_bag')->value('id'))->sole();
        $source->update(['merged_into_clo_id' => $target->id]);
        $old->update(['retired_at' => now(), 'merged_into_id' => $target->litter_object_id]);
        $catalogue = DB::table('category_litter_object')->orderBy('id')->get()->toJson();
        $this->seed(GenerateTagsSeeder::class);
        $this->assertSame($catalogue, DB::table('category_litter_object')->orderBy('id')->get()->toJson());
        $this->assertSame($before, $tag->fresh()->getAttributes());
        $this->assertSame(123, (int) $photo->fresh()->xp);
    }
}
