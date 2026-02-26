<?php

namespace Database\Seeders\Tags;

use App\Models\Litter\Categories\Material;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\LitterState;
use App\Models\Litter\Tags\CategoryObject;
use App\Tags\TagsConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateTagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedMaterials();
            $this->seedTypes();
            $this->seedCategoryObjectRelationships();
            $this->seedCustomTags();
        });
    }

    /**
     * Seed materials from the deprecated Material model + config
     */
    protected function seedMaterials(): void
    {
        $materials = Material::types();

        $configMaterials = $this->extractMaterialsFromConfig();
        $allMaterials = array_unique(array_merge($materials, $configMaterials));

        foreach ($allMaterials as $material) {
            Materials::firstOrCreate(['key' => $material]);
        }
    }

    /**
     * Seed litter object types
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

    protected function seedCustomTags(): void
    {
        if (!Schema::hasTable('custom_tags')) {
            return;
        }

        $existing = CustomTagNew::count();
        if ($existing > 0) {
            return;
        }

        $tags = DB::table('custom_tags')
            ->select('tag')
            ->distinct()
            ->pluck('tag')
            ->filter()
            ->unique();

        foreach ($tags as $tag) {
            CustomTagNew::firstOrCreate(['key' => trim($tag)]);
        }
    }

    /**
     * Seed categories, objects, CLO pivots, materials, and types from TagsConfig
     */
    protected function seedCategoryObjectRelationships(): void
    {
        $configuration = TagsConfig::get();

        foreach ($configuration as $categoryKey => $objects) {
            $category = Category::firstOrCreate(['key' => $categoryKey]);

            foreach ($objects as $objectKey => $attributes) {
                $litterObject = LitterObject::firstOrCreate(['key' => $objectKey]);

                $pivot = CategoryObject::firstOrCreate([
                    'category_id' => $category->id,
                    'litter_object_id' => $litterObject->id,
                ]);

                // Attach materials if present
                if (!empty($attributes['materials'])) {
                    $this->attachMaterials($pivot, $attributes['materials']);
                }

                // Attach types if present (v5.1)
                if (!empty($attributes['types'])) {
                    $this->attachTypes($pivot, $attributes['types']);
                }
            }
        }
    }

    /**
     * Attach materials to a category-object pivot
     */
    protected function attachMaterials(CategoryObject $pivot, array $materialKeys): void
    {
        $materialIds = [];

        foreach ($materialKeys as $materialKey) {
            $material = Materials::firstOrCreate(['key' => $materialKey]);
            $materialIds[] = $material->id;
        }

        if (!empty($materialIds)) {
            $pivot->materials()->syncWithoutDetaching($materialIds);
        }
    }

    /**
     * Attach types to a category-object pivot via category_object_types
     */
    protected function attachTypes(CategoryObject $pivot, array $typeKeys): void
    {
        $typeIds = LitterObjectType::whereIn('key', $typeKeys)->pluck('id')->toArray();

        if (!empty($typeIds)) {
            $pivot->types()->syncWithoutDetaching($typeIds);
        }
    }

    /**
     * Extract all unique materials from configuration
     */
    protected function extractMaterialsFromConfig(): array
    {
        $materials = [];
        $configuration = TagsConfig::get();

        foreach ($configuration as $category => $objects) {
            foreach ($objects as $object => $attributes) {
                if (!empty($attributes['materials'])) {
                    $materials = array_merge($materials, $attributes['materials']);
                }
            }
        }

        return array_unique($materials);
    }
}
