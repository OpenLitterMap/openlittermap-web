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
    }

    /** @test */
    public function test_it_seeds_materials_correctly(): void
    {
        $this->seed(CategoryLitterObjectSeeder::class);

        $materials = ['glass', 'plastic', 'aluminium'];

        foreach ($materials as $material) {
            $this->assertDatabaseHas('materials', ['key' => $material]);
        }

        $beerTagType = TagType::where('key', 'beer')->first();
        $glassMaterial = Materials::where('key', 'glass')->first();

        $this->assertTrue($beerTagType->materials->contains($glassMaterial));
    }

    /** @test */
    public function test_it_correctly_establishes_relationships_between_models(): void
    {
        // Run the seeder
        $this->seed(CategoryLitterObjectSeeder::class);

        // Verify that 'butts' LitterObject is associated with 'smoking' Category
        $smokingCategory = Category::where('key', 'smoking')->first();
        $buttsObject = LitterObject::where('key', 'butts')->first();

        $this->assertTrue($smokingCategory->litterObjects->contains($buttsObject));

        // Verify that 'butts' LitterObject has associated Materials
        $plasticMaterial = Materials::where('key', 'plastic')->first();
        $this->assertTrue($buttsObject->materials->contains($plasticMaterial));
    }
}
