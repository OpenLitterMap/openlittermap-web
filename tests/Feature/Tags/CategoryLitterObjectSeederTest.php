<?php

namespace Tests\Feature\Tags;

use Tests\TestCase;
use App\Models\TagType;
use App\Models\Category;
use App\Models\Materials;
use App\Models\LitterObject;
use Database\Seeders\CategoryLitterObjectSeeder;

class CategoryLitterObjectSeederTest extends TestCase
{
    /** @test */
    public function test_it_seeds_categories(): void
    {
        // Run the seeder
        $this->seed(CategoryLitterObjectSeeder::class);

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
        $this->seed(CategoryLitterObjectSeeder::class);

        // Check a specific LitterObject
        $this->assertDatabaseHas('litter_objects', ['key' => 'bottle']);

        // Assert that 'bottle' is associated with the 'alcohol' category
        $alcoholCategory = Category::where('key', 'alcohol')->first();
        $bottleObject = LitterObject::where('key', 'bottle')->first();
        $this->assertTrue($alcoholCategory->litterObjects->contains($bottleObject));

        // Check the butts object is not in the alcohol category
        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->assertFalse($alcoholCategory->litterObjects->contains($buttsObject));
    }

    /** @test */
    public function test_it_seeds_materials(): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $materials = ['glass', 'plastic', 'aluminium'];

        foreach ($materials as $material) {
            $this->assertDatabaseHas('materials', ['key' => $material]);
        }

        $beerTagType = TagType::where('key', 'beer_bottle')->first();
        $glassMaterial = Materials::where('key', 'glass')->first();
        $this->assertTrue($beerTagType->materials->contains($glassMaterial));

        // Check the beer does not have rubber material
        $rubberMaterial = Materials::where('key', 'rubber')->first();
        $this->assertFalse($beerTagType->materials->contains($rubberMaterial));

        $notMaterials = ['butts', 'beer_bottle', 'bottle'];

        foreach ($notMaterials as $notMaterial) {
            $this->assertDatabaseMissing('materials', ['key' => $notMaterial]);
        }
    }

    /** @test */
    public function test_it_seeds_tag_types(): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $this->assertDatabaseHas('tag_types', ['key' => 'beer_bottle']);

        $bottleObject = LitterObject::where('key', 'bottle')->first();
        $beerTagType = TagType::where('key', 'beer_bottle')->first();

        $this->assertTrue($bottleObject->tagTypes->contains($beerTagType));

        $buttsObject = LitterObject::where('key', 'butts')->first();
        $this->assertFalse($bottleObject->tagTypes->contains($buttsObject));
    }

    /** @test */
    public function test_it_correctly_establishes_relationships_between_models(): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        // Verify that 'butts' LitterObject is associated with 'smoking' Category
        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();

        $this->assertTrue($smokingCategory->litterObjects->contains($buttsObject));

        // Verify that 'butts' LitterObject has associated Materials
        $plasticMaterial = Materials::where('key', 'plastic')->first();
        $this->assertTrue($buttsObject->materials->contains($plasticMaterial));

        $rubberMaterial = Materials::where('key', 'rubber')->first();
        $this->assertFalse($buttsObject->materials->contains($rubberMaterial));
    }

    /** @test */
    public function test_it_associates_materials_correctly(): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $cupObject = LitterObject::where('key', 'cup')->first();
        $materials = ['ceramic', 'foam', 'paper', 'plastic', 'metal'];

        foreach ($materials as $material) {
            $materialModel = Materials::where('key', $material)->first();
            $this->assertTrue($cupObject->materials->contains($materialModel));
        }

        $notMaterials = ['cotton', 'nylon'];

        foreach ($notMaterials as $notMaterial) {
            $materialModel = Materials::where('key', $notMaterial)->first();
            $this->assertFalse($cupObject->materials->contains($materialModel));
        }
    }

    /** @test */
    /** @test */
    public function it_does_not_duplicate_entries()
    {
        // Run the seeder multiple times
        $this->seed(CategoryLitterObjectSeeder::class);
        $this->seed(CategoryLitterObjectSeeder::class);

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
