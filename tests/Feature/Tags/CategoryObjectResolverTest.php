<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class CategoryObjectResolverTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenerateTagsSeeder::class);

        $this->category = Category::where('key', 'other')->firstOrFail();
        CategoryObject::flushResolverCache();
    }

    public function test_it_resolves_the_clo_id_from_a_category_and_object(): void
    {
        $clo = CategoryObject::where('category_id', $this->category->id)->firstOrFail();

        $this->assertSame(
            $clo->id,
            CategoryObject::resolveId($clo->category_id, $clo->litter_object_id)
        );
    }

    public function test_it_resolves_null_when_the_pairing_has_no_pivot(): void
    {
        $orphan = LitterObject::create(['key' => 'shadowObject']);

        $this->assertNull(CategoryObject::resolveId($this->category->id, $orphan->id));
    }

    public function test_it_resolves_null_when_either_component_is_missing(): void
    {
        $clo = CategoryObject::where('category_id', $this->category->id)->firstOrFail();

        $this->assertNull(CategoryObject::resolveId(null, $clo->litter_object_id));
        $this->assertNull(CategoryObject::resolveId($clo->category_id, null));
    }

    /**
     * `MigrateTag` creates the survivor pivot and then regenerates summaries inside the same
     * process, so a map loaded before the insert would resolve the new pairing to null and write
     * the very gap this replaces.
     */
    public function test_flushing_the_cache_picks_up_a_pivot_created_later_in_the_process(): void
    {
        $object = LitterObject::create(['key' => 'lateObject']);

        $this->assertNull(CategoryObject::resolveId($this->category->id, $object->id));

        $clo = CategoryObject::create([
            'category_id' => $this->category->id,
            'litter_object_id' => $object->id,
        ]);

        CategoryObject::flushResolverCache();

        $this->assertSame($clo->id, CategoryObject::resolveId($this->category->id, $object->id));
    }

    /**
     * A retirement chain records its approved subtype on whichever hop introduced it. A stale
     * client holding the first CLO cannot know about a split made two mappings later, so the
     * resolver has to carry the type forward from the hop that set it, not from the first tombstone.
     */
    public function test_it_resolves_a_chain_to_its_final_pivot_and_the_type_introduced_mid_chain(): void
    {
        $a = $this->pivotFor('chain_a');
        $b = $this->pivotFor('chain_b');
        $c = $this->pivotFor('chain_c');
        $beer = LitterObjectType::firstOrCreate(['key' => 'beer']);

        $a->update(['merged_into_clo_id' => $b->id, 'merged_into_type_id' => null]);
        $b->update(['merged_into_clo_id' => $c->id, 'merged_into_type_id' => $beer->id]);

        $mapping = $a->fresh()->resolveActiveMapping();

        $this->assertSame($c->id, $mapping['clo']->id);
        $this->assertSame($beer->id, $mapping['type_id']);
    }

    public function test_a_later_hop_without_a_type_keeps_the_type_set_earlier_in_the_chain(): void
    {
        $a = $this->pivotFor('chain_a');
        $b = $this->pivotFor('chain_b');
        $c = $this->pivotFor('chain_c');
        $beer = LitterObjectType::firstOrCreate(['key' => 'beer']);

        $a->update(['merged_into_clo_id' => $b->id, 'merged_into_type_id' => $beer->id]);
        $b->update(['merged_into_clo_id' => $c->id, 'merged_into_type_id' => null]);

        $mapping = $a->fresh()->resolveActiveMapping();

        $this->assertSame($c->id, $mapping['clo']->id);
        $this->assertSame($beer->id, $mapping['type_id']);
    }

    public function test_an_active_pivot_resolves_to_itself_with_no_type(): void
    {
        $clo = $this->pivotFor('chain_a');

        $mapping = $clo->resolveActiveMapping();

        $this->assertSame($clo->id, $mapping['clo']->id);
        $this->assertNull($mapping['type_id']);
    }

    private function pivotFor(string $objectKey): CategoryObject
    {
        $clo = CategoryObject::firstOrCreate([
            'category_id' => $this->category->id,
            'litter_object_id' => LitterObject::firstOrCreate(['key' => $objectKey])->id,
        ]);
        CategoryObject::flushResolverCache();

        return $clo;
    }
}
