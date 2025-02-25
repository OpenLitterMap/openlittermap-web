<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryLitterObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class GenerateTagsSeederTest extends TestCase
{
    /** @test */
    public function test_it_seeds_categories(): void
    {
        // Run the seeder
        $this->seed(GenerateTagsSeeder::class);

        $categories = Category::all();

        $minCategories = [
            'smoking',
            'food',
            'alcohol'
        ];

        // Assert that categories are created
        foreach ($minCategories as $categoryKey) {
            $this->assertDatabaseHas('categories', ['key' => $categoryKey]);
        }

        // Assert the total number of categories
        $this->assertGreaterThan(10, count($categories));
    }

    /** @test */
    public function test_it_seeds_litter_objects(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        // Check a specific LitterObject
        $this->assertDatabaseHas('litter_objects', ['key' => 'water_bottle']);

        // Assert that 'bottle' is associated with the 'alcohol' category
        $alcoholCategory = Category::where('key', 'alcohol')->first();
        $bottleObject = LitterObject::where('key', 'beer_bottle')->first();
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

        $beerObject = LitterObject::where('key', 'beer_bottle')->first();
        $glassMaterial = Materials::where('key', 'glass')->first();
        $this->assertTrue($beerObject->materials->contains($glassMaterial));

        // Check the beer does not have rubber material
        $rubberMaterial = Materials::where('key', 'rubber')->first();
        $this->assertFalse($beerObject->materials->contains($rubberMaterial));

        $notMaterials = ['butts', 'beer_bottle', 'bottle'];

        foreach ($notMaterials as $notMaterial) {
            $this->assertDatabaseMissing('materials', ['key' => $notMaterial]);
        }
    }

    /** @test */
    public function test_it_correctly_establishes_relationships_between_models(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        // Verify that 'butts' LitterObject is associated with 'smoking' Category
        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();

        $categoryLitterObject = CategoryLitterObject::where([
            'category_id' => $smokingCategory->id,
            'litter_object_id' => $buttsObject->id
        ])->first();

        // Verify that 'butts' LitterObject has associated Materials
        $plasticMaterial = Materials::where('key', 'plastic')->first();
        $this->assertTrue($categoryLitterObject->materials->contains($plasticMaterial));

        $rubberMaterial = Materials::where('key', 'rubber')->first();
        $this->assertFalse($categoryLitterObject->materials->contains($rubberMaterial));
    }

    /** @test */
    public function test_it_associates_materials_correctly(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        // Cup is used across 3 categories.
        $cupObject = LitterObject::where('key', 'cup')->first();
        $expectedMaterials = ['ceramic', 'foam', 'paper', 'plastic', 'metal'];
        $notExpectedMaterials = ['cotton', 'nylon'];

        // Aggregate all materials from the pivot records of the associated categories.
        $aggregatedMaterials = $cupObject->categories->flatMap(function ($category) {
            return $category->pivot->materials;
        })->unique('id');

        \Log::info($aggregatedMaterials);

        foreach ($expectedMaterials as $materialKey) {
            $materialModel = Materials::where('key', $materialKey)->first();
            $this->assertTrue(
                $aggregatedMaterials->contains($materialModel),
                "Failed asserting that material '{$materialKey}' is associated with cup."
            );
        }

        foreach ($notExpectedMaterials as $materialKey) {
            $materialModel = Materials::where('key', $materialKey)->first();
            $this->assertFalse(
                $aggregatedMaterials->contains($materialModel),
                "Failed asserting that material '{$materialKey}' is not associated with cup."
            );
        }
    }

    /** @test */
    /** @test */
    public function it_does_not_duplicate_entries()
    {
        // Run the seeder multiple times
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateTagsSeeder::class);

        // Ensure that entries are not duplicated
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
