<?php

namespace Tests\Feature\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class GenerateTagsSeederTest extends TestCase
{
    /**
     * The v5 migration parked ~46k items on two camelCase keys that were never in TagsConfig, so
     * neither pairing had a pivot. Object keys are snake_case singular, so `plastic_bag` and
     * `random_litter` are the surviving keys; the camelCase objects are deprecated into them by
     * `olm:migrate-tag`, which keeps the retired object and the `merged_into_id` trail rather than
     * rewriting what the old rows said. Seeding is what makes the survivors resolvable.
     *
     * @test
     */
    public function test_it_seeds_the_snake_case_survivors_for_the_camel_case_keys(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        CategoryObject::flushResolverCache();

        $category = Category::where('key', CategoryKey::Other->value)->firstOrFail();

        foreach (['plastic_bag', 'random_litter'] as $key) {
            $object = LitterObject::where('key', $key)->firstOrFail();

            $this->assertNotNull(
                CategoryObject::resolveId($category->id, $object->id),
                "{$key} must resolve to a CLO under other"
            );
        }
    }

    /**
     * `straws` and `balloons` carry rows under marine as well as their home category. Retiring
     * either object sweeps every category at once, so the marine pairing needs a declared pivot
     * before the migration runs — otherwise the command would invent one. The reviewer's note on
     * both marine entries says exactly this: add a canonical pivot rather than collapsing them
     * into generic `other`.
     *
     * @test
     */
    public function test_it_seeds_the_marine_pairings_needed_before_the_sweep(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        CategoryObject::flushResolverCache();

        $marine = Category::where('key', CategoryKey::Marine->value)->firstOrFail();

        foreach (['straw', 'balloon'] as $key) {
            $object = LitterObject::where('key', $key)->firstOrFail();

            $this->assertNotNull(
                CategoryObject::resolveId($marine->id, $object->id),
                "marine/{$key} must resolve to a CLO"
            );
        }
    }

    /**
     * The manifest proposed folding `mediumplastics` into `macroplastics`, which would have
     * collapsed the middle size class into the largest one — and into an object holding a fifth of
     * its data. The size classes are the measurement, so the middle one gets its own object rather
     * than being merged away.
     *
     * @test
     */
    public function test_it_seeds_the_middle_marine_size_class(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        CategoryObject::flushResolverCache();

        $marine = Category::where('key', CategoryKey::Marine->value)->firstOrFail();

        foreach (['macroplastics', 'medium_plastic', 'microplastics'] as $key) {
            $object = LitterObject::where('key', $key)->firstOrFail();

            $this->assertNotNull(
                CategoryObject::resolveId($marine->id, $object->id),
                "marine/{$key} must resolve to a CLO"
            );
        }
    }

    /**
     * Marine is a context, not a product class — a bottle found on a beach is both a bottle and
     * marine litter, so the pairing is declared rather than the tag being moved into alcohol.
     * Same for industrial plastic. Declaring the pivot resolves these with no tag rows moved.
     *
     * @test
     */
    public function test_it_seeds_the_context_pairings_that_were_kept_in_place(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        CategoryObject::flushResolverCache();

        $expected = [
            CategoryKey::Marine->value => ['bag', 'bottle', 'lighters'],
            CategoryKey::Industrial->value => ['plastic'],
        ];

        foreach ($expected as $categoryKey => $objectKeys) {
            $category = Category::where('key', $categoryKey)->firstOrFail();

            foreach ($objectKeys as $objectKey) {
                $object = LitterObject::where('key', $objectKey)->firstOrFail();

                $this->assertNotNull(
                    CategoryObject::resolveId($category->id, $object->id),
                    "{$categoryKey}/{$objectKey} must resolve to a CLO"
                );
            }
        }
    }

    /** @test */
    public function test_it_seeds_categories(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $categories = Category::all();

        $minCategories = [
            'alcohol',
            'art',
            'civic',
            'coffee',
            'dumping',
            'electronics',
            'food',
            'industrial',
            'marine',
            'medical',
            'other',
            'pets',
            'sanitary',
            'smoking',
            'softdrinks',
            'unclassified',
            'vehicles',
        ];

        foreach ($minCategories as $categoryKey) {
            $this->assertDatabaseHas('categories', ['key' => $categoryKey]);
        }

        $this->assertGreaterThan(10, count($categories));
    }

    /** @test */
    public function test_it_seeds_litter_objects(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        // Check canonical objects exist
        $this->assertDatabaseHas('litter_objects', ['key' => 'bottle']);
        $this->assertDatabaseHas('litter_objects', ['key' => 'can']);
        $this->assertDatabaseHas('litter_objects', ['key' => 'butts']);

        // Assert that 'bottle' is associated with the 'alcohol' category
        $alcoholCategory = Category::where('key', CategoryKey::Alcohol->value)->first();
        $bottleObject = LitterObject::where('key', 'bottle')->first();
        $this->assertTrue($alcoholCategory->litterObjects->contains($bottleObject));

        // Check the butts object is not in the alcohol category
        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->assertFalse($alcoholCategory->litterObjects->contains($buttsObject));
    }

    /** @test */
    public function test_it_seeds_materials(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $materials = ['glass', 'plastic', 'aluminium'];

        foreach ($materials as $material) {
            $this->assertDatabaseHas('materials', ['key' => $material]);
        }

        // Bottle in alcohol category should have glass material
        $bottleObject = LitterObject::where('key', 'bottle')->first();
        $this->assertNotNull($bottleObject, "Bottle object not found.");

        $alcoholCategory = Category::where('key', CategoryKey::Alcohol->value)->first();
        $clo = CategoryObject::where('category_id', $alcoholCategory->id)
            ->where('litter_object_id', $bottleObject->id)
            ->first();

        $glassMaterial = Materials::where('key', 'glass')->first();
        $this->assertNotNull($glassMaterial);
        $this->assertTrue($clo->materials->contains($glassMaterial));

        // Bottle should not have rubber material
        $rubberMaterial = Materials::where('key', 'rubber')->first();
        if ($rubberMaterial) {
            $this->assertFalse($clo->materials->contains($rubberMaterial));
        }

        // Assert that object keys are not in materials table
        $notMaterials = ['butts', 'bottle', 'can'];
        foreach ($notMaterials as $notMaterialKey) {
            $this->assertDatabaseMissing('materials', ['key' => $notMaterialKey]);
        }
    }

    /** @test */
    public function test_it_correctly_establishes_relationships_between_models(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $smokingCategory = Category::where('key', CategoryKey::Smoking->value)->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();

        $categoryLitterObject = CategoryObject::where([
            'category_id' => $smokingCategory->id,
            'litter_object_id' => $buttsObject->id
        ])->first();

        $plasticMaterial = Materials::where('key', 'plastic')->first();
        $this->assertTrue($categoryLitterObject->materials->contains($plasticMaterial));

        $rubberMaterial = Materials::where('key', 'rubber')->first();
        $this->assertFalse($categoryLitterObject->materials->contains($rubberMaterial));
    }

    /** @test */
    public function test_it_associates_materials_correctly(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        // Cup is used across multiple categories (alcohol, softdrinks)
        $cupObject = LitterObject::where('key', 'cup')->first();

        $expectedMaterials = ['bioplastic', 'ceramic', 'foam', 'paper', 'plastic', 'metal'];
        $notExpectedMaterials = ['cotton', 'nylon'];

        $aggregatedMaterials = $cupObject->categories->flatMap(function ($category) {
            return $category->pivot->materials()->get();
        });

        foreach ($expectedMaterials as $materialKey) {
            $materialModel = Materials::where('key', $materialKey)->first();
            $this->assertNotNull($materialModel, "Material record for key '{$materialKey}' not found.");
            $this->assertTrue(
                $aggregatedMaterials->contains(function ($item) use ($materialModel) {
                    return $item->id === $materialModel->id;
                }),
                "Failed asserting that material '{$materialKey}' is associated with cup."
            );
        }

        foreach ($notExpectedMaterials as $materialKey) {
            $materialModel = Materials::where('key', $materialKey)->first();
            if ($materialModel) {
                $this->assertFalse(
                    $aggregatedMaterials->contains(function ($item) use ($materialModel) {
                        return $item->id === $materialModel->id;
                    }),
                    "Failed asserting that material '{$materialKey}' is not associated with cup."
                );
            }
        }
    }

    /** @test */
    public function it_does_not_duplicate_entries()
    {
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateTagsSeeder::class);

        $categoryCount = Category::count();
        $uniqueCategories = Category::distinct('key')->count('key');
        $this->assertEquals($categoryCount, $uniqueCategories);

        $litterObjectCount = LitterObject::count();
        $uniqueLitterObjects = LitterObject::distinct('key')->count('key');
        $this->assertEquals($litterObjectCount, $uniqueLitterObjects);

        $materialCount = Materials::count();
        $uniqueMaterials = Materials::distinct('key')->count('key');
        $this->assertEquals($materialCount, $uniqueMaterials);
    }
}
