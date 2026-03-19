<?php

namespace Database\Seeders\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
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
     * Seed materials extracted from TagsConfig
     */
    protected function seedMaterials(): void
    {
        foreach (TagsConfig::allMaterialKeys() as $material) {
            Materials::firstOrCreate(['key' => $material]);
        }
    }

    /**
     * Seed litter object types extracted from TagsConfig
     */
    protected function seedTypes(): void
    {
        foreach (TagsConfig::allTypeKeys() as $key) {
            $name = ucwords(str_replace('_', ' ', $key));
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
     * Seed categories, objects, CLO pivots, materials, and types from TagsConfig.
     * Also ensures the 'unclassified' system category exists (used by ClassifyTagsService
     * for deprecated v4 alias resolution, not shown in UI).
     */
    protected function seedCategoryObjectRelationships(): void
    {
        $configuration = TagsConfig::get();

        // Ensure 'unclassified' system category exists with an 'other' CLO.
        // Required by ClassifyTagsService (v4 alias resolution) and UpdateTagsService.
        $unclassified = Category::firstOrCreate(['key' => CategoryKey::Unclassified->value]);
        $otherObj = LitterObject::firstOrCreate(['key' => 'other']);
        CategoryObject::firstOrCreate([
            'category_id' => $unclassified->id,
            'litter_object_id' => $otherObj->id,
        ]);

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

}
