<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseTagMigration extends Command
{
    protected $signature = 'olm:diagnose-migration {photo}';
    protected $description = 'Diagnose why objects are NULL in migrated photos';

    public function handle()
    {
        $photoId = $this->argument('photo');
        $photo = Photo::with(['photoTags.category', 'photoTags.object'])->find($photoId);

        if (!$photo) {
            $this->error("Photo #{$photoId} not found");
            return self::FAILURE;
        }

        $this->info("=== DIAGNOSTIC FOR PHOTO #{$photoId} ===\n");

        // 1. Check old tags
        $oldTags = $photo->tags();
        $this->info("1️⃣  OLD TAGS (v4):");
        $this->line(json_encode($oldTags, JSON_PRETTY_PRINT));

        // 2. Check PhotoTags in database
        $this->info("\n2️⃣  PHOTO_TAGS TABLE:");
        $photoTagsRaw = DB::table('photo_tags')
            ->where('photo_id', $photoId)
            ->get();

        foreach ($photoTagsRaw as $pt) {
            $this->line("PhotoTag #{$pt->id}:");
            $this->line("  category_id: " . ($pt->category_id ?? 'NULL'));
            $this->line("  litter_object_id: " . ($pt->litter_object_id ?? 'NULL') . " ← Should NOT be NULL!");
            $this->line("  custom_tag_primary_id: " . ($pt->custom_tag_primary_id ?? 'NULL'));
            $this->line("  quantity: {$pt->quantity}");
        }

        // 3. Check what object IDs exist for deprecated tags
        $this->info("\n3️⃣  CHECKING OBJECT LOOKUP:");

        $deprecatedMappings = [
            'beerCan' => 'beer_can',
            'beerBottle' => 'beer_bottle',
            'tinCan' => 'soda_can',
            'bottleLabel' => 'label',
            'waterBottle' => 'water_bottle',
        ];

        foreach ($deprecatedMappings as $oldKey => $newKey) {
            $object = DB::table('litter_objects')->where('key', $newKey)->first();
            if ($object) {
                $this->info("  ✓ {$oldKey} → {$newKey} (ID: {$object->id})");
            } else {
                $this->error("  ✗ {$oldKey} → {$newKey} NOT FOUND IN DATABASE!");
            }
        }

        // 4. Check if objects were auto-created
        $this->info("\n4️⃣  AUTO-CREATED OBJECTS:");
        $autoCreated = DB::table('litter_objects')
            ->where('crowdsourced', true)
            ->orWhere('created_at', '>', $photo->migrated_at)
            ->get(['id', 'key', 'crowdsourced']);

        if ($autoCreated->isEmpty()) {
            $this->line("  No auto-created objects");
        } else {
            foreach ($autoCreated as $obj) {
                $this->line("  • {$obj->key} (ID: {$obj->id}, crowdsourced: {$obj->crowdsourced})");
            }
        }

        // 5. Check ClassifyTagsService behavior
        $this->info("\n5️⃣  TESTING TAG CLASSIFICATION:");

        try {
            $classifyService = app(\App\Services\Tags\ClassifyTagsService::class);

            foreach ($oldTags as $category => $items) {
                if ($category === 'brands') continue;

                foreach ($items as $tag => $qty) {
                    $result = $classifyService->classify($tag);
                    $this->line("  {$tag}:");
                    $this->line("    Type: " . ($result['type'] ?? 'undefined'));
                    $this->line("    ID: " . ($result['id'] ?? 'NULL'));
                    $this->line("    Key: " . ($result['key'] ?? 'NULL'));

                    if (isset($result['materials'])) {
                        $this->line("    Materials: " . implode(', ', $result['materials']));
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("  Failed to test classification: " . $e->getMessage());
        }

        // 6. Check if litter_objects table is populated
        $this->info("\n6️⃣  LITTER_OBJECTS TABLE STATUS:");
        $objectCount = DB::table('litter_objects')->count();
        $this->line("  Total objects in database: {$objectCount}");

        if ($objectCount === 0) {
            $this->error("  ❌ CRITICAL: litter_objects table is EMPTY!");
            $this->error("  Run: php artisan db:seed --class=GenerateTagsSeeder");
        }

        // 7. Check category IDs
        $this->info("\n7️⃣  CATEGORY LOOKUP:");
        foreach (array_keys($oldTags) as $categoryKey) {
            if ($categoryKey === 'brands') continue;

            $category = DB::table('categories')->where('key', $categoryKey)->first();
            if ($category) {
                $this->info("  ✓ {$categoryKey} → ID: {$category->id}");
            } else {
                $this->error("  ✗ {$categoryKey} NOT FOUND IN DATABASE!");
            }
        }

        return self::SUCCESS;
    }
}
