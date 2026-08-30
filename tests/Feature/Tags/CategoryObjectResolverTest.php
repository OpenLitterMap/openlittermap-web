<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
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
}
