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
