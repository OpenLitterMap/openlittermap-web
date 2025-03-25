<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryLitterObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\Taggable;
use App\Models\Photo;
use Illuminate\Console\Command;

class PreMigrationScript extends Command
{
    protected $signature = 'olm:update:pre-migration-v5';

    protected $description = 'Generate taggable CategoryLitterObject relationships from existing photo tags';

    public function handle()
    {
        $this->info("🚀 Starting pre-migration taggable discovery...");

        Photo::query()
            ->select('id')
            ->with('customTags') // eager load for performance
            ->chunkById(500, function ($photos) {
                foreach ($photos as $photo) {
                    $tags = $photo->tags(); // array: category => [tag => quantity]
                    $customTags = $photo->customTags;

                    $objects = [];

                    // Step 1: Build or find all CategoryLitterObject entries
                    foreach ($tags as $categoryKey => $tagList) {
                        // Skip brand and material categories here
                        if (in_array($categoryKey, ['brands', 'brand', 'material', 'materials'])) {
                            continue;
                        }

                        $category = Category::firstOrCreate(['key' => $categoryKey]);

                        foreach ($tagList as $tagKey => $quantity) {
                            $object = LitterObject::firstOrCreate(['key' => $tagKey]);

                            $clo = CategoryLitterObject::firstOrCreate([
                                'category_id' => $category->id,
                                'litter_object_id' => $object->id,
                            ]);

                            $objects[] = $clo;
                        }
                    }

                    // Step 2: Parse brands & materials from custom tags
                    $brands = [];
                    $materials = [];

                    foreach ($customTags as $customTag) {
                        $parts = preg_split('/[:,=]/', strtolower($customTag->tag));

                        foreach ($parts as $i => $part) {
                            if (in_array($part, ['brand', 'brands']) && isset($parts[$i + 1])) {
                                $brandKeys = preg_split('/[.|,]/', $parts[$i + 1]);
                                foreach ($brandKeys as $key) {
                                    if ($key) $brands[] = trim($key);
                                }
                            }

                            if (in_array($part, ['material', 'materials']) && isset($parts[$i + 1])) {
                                $materialKeys = preg_split('/[.|,]/', $parts[$i + 1]);
                                foreach ($materialKeys as $key) {
                                    if ($key) $materials[] = trim($key);
                                }
                            }
                        }
                    }

                    // Step 3: Match brands and materials to each CLO
                    foreach ($objects as $clo) {
                        foreach ($brands as $brandKey) {
                            $brand = BrandList::firstOrCreate(['key' => $brandKey]);
                            $this->incrementTaggable($clo->id, BrandList::class, $brand->id);
                        }

                        foreach ($materials as $materialKey) {
                            $material = Materials::firstOrCreate(['key' => $materialKey]);
                            $this->incrementTaggable($clo->id, Materials::class, $material->id);
                        }
                    }
                }
            });

        $this->info("✅ Finished populating taggable relationships.");
    }

    protected function incrementTaggable(int $cloId, string $type, int $id, int $quantity = 1): void
    {
        $taggable = Taggable::firstOrNew([
            'category_litter_object_id' => $cloId,
            'taggable_type' => $type,
            'taggable_id' => $id,
        ]);

        $taggable->count += $quantity;
        $taggable->save();
    }
}
