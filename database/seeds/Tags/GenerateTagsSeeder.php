<?php

namespace Database\Seeders\Tags;

use App\Models\Litter\Categories\Material;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterState;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Photo;
use App\Tags\TagsConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenerateTagsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCategories();
            $this->seedMaterials();
            $this->seedStates();
            $this->seedCategoryObjectRelationships();
        });
    }

    /**
     * Seed categories from Photo model
     */
    protected function seedCategories(): void
    {
        $categories = Photo::categories();

        foreach ($categories as $category) {
            Category::firstOrCreate(['key' => $category]);
        }
    }

    /**
     * Seed materials
     */
    protected function seedMaterials(): void
    {
        // Get materials from the deprecated Material model
        $materials = Material::types();

        // Add any additional materials from configuration
        $configMaterials = $this->extractMaterialsFromConfig();
        $allMaterials = array_unique(array_merge($materials, $configMaterials));

        foreach ($allMaterials as $material) {
            Materials::firstOrCreate(['key' => $material]);
        }
    }

    /**
     * Seed states (degraded, etc.)
     */
    protected function seedStates(): void
    {
        $states = $this->extractStatesFromConfig();

        foreach ($states as $state) {
            LitterState::firstOrCreate(['key' => $state]);
        }
    }

    /**
     * Seed category-object relationships with materials and states
     */
    protected function seedCategoryObjectRelationships(): void
    {
        $configuration = TagsConfig::get();

        foreach ($configuration as $categoryKey => $objects) {
            $category = Category::firstOrCreate(['key' => $categoryKey]);

            foreach ($objects as $objectKey => $attributes) {
                // Create the litter object
                $litterObject = LitterObject::firstOrCreate(['key' => $objectKey]);

                // Create the pivot relationship
                $pivot = CategoryObject::firstOrCreate([
                    'category_id' => $category->id,
                    'litter_object_id' => $litterObject->id,
                ]);

                // Attach materials if present
                if (!empty($attributes['materials'])) {
                    $this->attachMaterials($pivot, $attributes['materials']);
                }

                // Attach states if present
                if (!empty($attributes['states'])) {
                    $this->attachStates($pivot, $attributes['states']);
                }

                // Handle sizes (stored as custom attributes or states)
                if (!empty($attributes['sizes'])) {
                    $this->attachSizes($pivot, $attributes['sizes']);
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
     * Attach states to a category-object pivot
     */
    protected function attachStates(CategoryObject $pivot, array $stateKeys): void
    {
        // Since the current models don't have a states relationship,
        // we'll need to implement this based on your requirements
        // For now, we'll create the states and note them for future implementation

        foreach ($stateKeys as $stateKey) {
            LitterState::firstOrCreate(['key' => $stateKey]);
            // TODO: Implement state attachment when the relationship is added to CategoryObject
        }
    }

    /**
     * Attach sizes as states or custom attributes
     */
    protected function attachSizes(CategoryObject $pivot, array $sizes): void
    {
        // Sizes can be treated as states or custom attributes
        // For now, creating them as states with a 'size_' prefix

        foreach ($sizes as $size) {
            $sizeState = LitterState::firstOrCreate(['key' => 'size_' . $size]);
            // TODO: Implement size attachment when the relationship is added
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

    /**
     * Extract all unique states from configuration
     */
    protected function extractStatesFromConfig(): array
    {
        $states = [];
        $configuration = TagsConfig::get();

        foreach ($configuration as $category => $objects) {
            foreach ($objects as $object => $attributes) {
                if (!empty($attributes['states'])) {
                    $states = array_merge($states, $attributes['states']);
                }
                if (!empty($attributes['sizes'])) {
                    foreach ($attributes['sizes'] as $size) {
                        $states[] = 'size_' . $size;
                    }
                }
            }
        }

        return array_unique($states);
    }
}
