<?php

namespace Database\Seeders\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedLitterObjectTypesSeeder extends Seeder
{
    /**
     * Seed litter object types, the unclassified category, canonical objects,
     * CLO pivots for typed combos, and category_object_types mappings.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedTypes();
            $this->seedUnclassifiedCategory();
            $this->seedCanonicalObjects();
            $this->seedTypeMappings();
        });
    }

    /**
     * Seed the ~17 litter object types (what was in the container).
     */
    protected function seedTypes(): void
    {
        $types = [
            'beer' => 'Beer',
            'wine' => 'Wine',
            'spirits' => 'Spirits',
            'cider' => 'Cider',
            'water' => 'Water',
            'soda' => 'Soda',
            'juice' => 'Juice',
            'energy' => 'Energy Drink',
            'sports' => 'Sports Drink',
            'coffee' => 'Coffee',
            'tea' => 'Tea',
            'milk' => 'Milk',
            'smoothie' => 'Smoothie',
            'iced_tea' => 'Iced Tea',
            'sparkling_water' => 'Sparkling Water',
            'plant_milk' => 'Plant Milk',
            'unknown' => 'Unknown',
        ];

        foreach ($types as $key => $name) {
            LitterObjectType::firstOrCreate(['key' => $key], ['name' => $name]);
        }
    }

    /**
     * Seed the unclassified category (system-only, hidden from UI).
     */
    protected function seedUnclassifiedCategory(): void
    {
        Category::firstOrCreate(['key' => CategoryKey::Unclassified->value]);
    }

    /**
     * Create canonical objects that don't yet exist.
     */
    protected function seedCanonicalObjects(): void
    {
        $objects = [
            'broken_glass' => 'Broken Glass',
            'carton' => 'Carton',
            'straw_wrapper' => 'Straw Wrapper',
            'coffee_pod' => 'Coffee Pod',
        ];

        foreach ($objects as $key => $name) {
            LitterObject::firstOrCreate(['key' => $key]);
        }
    }

    /**
     * Seed CLO pivots and category_object_types for all typed combos.
     */
    protected function seedTypeMappings(): void
    {
        Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);

        $mappings = $this->getTypeMappings();

        foreach ($mappings as $mapping) {
            $category = Category::where('key', $mapping['category'])->first();
            $object = LitterObject::where('key', $mapping['object'])->first();

            if (!$category || !$object) {
                continue;
            }

            // Create CLO pivot if it doesn't exist
            $clo = CategoryObject::firstOrCreate([
                'category_id' => $category->id,
                'litter_object_id' => $object->id,
            ]);

            // Get type IDs
            $typeIds = LitterObjectType::whereIn('key', $mapping['types'])->pluck('id')->toArray();

            // Sync types (additive — won't remove existing)
            $clo->types()->syncWithoutDetaching($typeIds);
        }
    }

    /**
     * Type mappings from the architecture spec.
     * Every typed CLO includes 'unknown'.
     */
    protected function getTypeMappings(): array
    {
        return [
            ['category' => CategoryKey::Alcohol->value, 'object' => 'bottle', 'types' => ['beer', 'wine', 'spirits', 'cider', 'unknown']],
            ['category' => CategoryKey::Alcohol->value, 'object' => 'can', 'types' => ['beer', 'cider', 'spirits', 'unknown']],
            ['category' => CategoryKey::Alcohol->value, 'object' => 'pint_glass', 'types' => ['beer', 'cider', 'unknown']],
            ['category' => CategoryKey::Alcohol->value, 'object' => 'wine_glass', 'types' => ['wine', 'unknown']],
            ['category' => CategoryKey::Alcohol->value, 'object' => 'shot_glass', 'types' => ['spirits', 'unknown']],

            ['category' => CategoryKey::Softdrinks->value, 'object' => 'bottle', 'types' => ['water', 'soda', 'juice', 'energy', 'sports', 'tea', 'milk', 'smoothie', 'unknown']],
            ['category' => CategoryKey::Softdrinks->value, 'object' => 'can', 'types' => ['soda', 'energy', 'juice', 'iced_tea', 'sparkling_water', 'unknown']],
            ['category' => CategoryKey::Softdrinks->value, 'object' => 'carton', 'types' => ['juice', 'milk', 'iced_tea', 'plant_milk', 'unknown']],
            ['category' => CategoryKey::Softdrinks->value, 'object' => 'cup', 'types' => ['coffee', 'tea', 'soda', 'smoothie', 'unknown']],
        ];
    }
}
