<?php

namespace App\Console\Commands\Tags;

use App\Enums\CategoryKey;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\LitterObjectType;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\Taggable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidateObjects extends Command
{
    protected $signature = 'olm:consolidate-objects {--dry-run : Show what would change without writing}';

    protected $description = 'Consolidate prefixed objects into canonical objects with types (Phase 2)';

    protected int $mapped = 0;
    protected int $backfilled = 0;
    protected int $nullNull = 0;
    protected int $taggablesRemapped = 0;
    protected int $errors = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $prefix = $dryRun ? '[DRY-RUN] ' : '';

        $this->info("{$prefix}Starting object consolidation...");

        $totalBefore = PhotoTag::count();
        $this->info("Total photo_tags: {$totalBefore}");

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            // Step 1: Ensure canonical objects exist (seeder is idempotent — safe in dry-run)
            $this->info("{$prefix}Step 1: Ensuring canonical objects exist...");
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\Tags\\GenerateTagsSeeder',
                '--no-interaction' => true,
            ]);

            // Step 2+3: Map prefixed objects → canonical + type
            $this->info("{$prefix}Step 2-3: Mapping prefixed objects...");
            $this->mapPrefixedObjects($dryRun);

            // Step 4: Remap taggables
            $this->info("{$prefix}Step 4: Remapping taggables...");
            $this->remapTaggables($dryRun);

            // Step 5: Backfill category_litter_object_id for remaining tags
            $this->info("{$prefix}Step 5: Backfilling CLO IDs...");
            $this->backfillCloIds($dryRun);

            // Step 6: Handle null-null tags
            $this->info("{$prefix}Step 6: Handling null-null tags...");
            $this->handleNullNullTags($dryRun);

            if (!$dryRun) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error("Failed: {$e->getMessage()}");
            Log::error('olm:consolidate-objects failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Command::FAILURE;
        }

        // Step 7: Report
        $this->newLine();
        $this->info("{$prefix}=== Consolidation Report ===");
        $this->info("Total photo_tags:      {$totalBefore}");
        $this->info("Mapped (prefixed):     {$this->mapped}");
        $this->info("Backfilled (CLO):      {$this->backfilled}");
        $this->info("Null-null fallback:    {$this->nullNull}");
        $this->info("Taggables remapped:    {$this->taggablesRemapped}");
        $this->info("Errors/skipped:        {$this->errors}");

        return Command::SUCCESS;
    }

    /**
     * Map prefixed objects to canonical objects + types.
     */
    protected function mapPrefixedObjects(bool $dryRun): void
    {
        $mappings = $this->getObjectMappings();

        foreach ($mappings as $mapping) {
            $oldObject = LitterObject::where('key', $mapping['old_object'])->first();
            if (!$oldObject) {
                continue; // Object doesn't exist, nothing to map
            }

            $newObject = LitterObject::where('key', $mapping['new_object'])->first();
            $newCategory = Category::where('key', $mapping['new_category'])->first();

            if (!$newObject || !$newCategory) {
                $this->warn("Missing target: {$mapping['new_category']}/{$mapping['new_object']}");
                $this->errors++;
                continue;
            }

            $newCLO = CategoryObject::where('category_id', $newCategory->id)
                ->where('litter_object_id', $newObject->id)
                ->first();

            if (!$newCLO) {
                $this->warn("Missing CLO: {$mapping['new_category']}/{$mapping['new_object']}");
                $this->errors++;
                continue;
            }

            $typeId = null;
            if (!empty($mapping['type'])) {
                $typeId = LitterObjectType::where('key', $mapping['type'])->value('id');
                if (!$typeId) {
                    $this->warn("Missing type: {$mapping['type']}");
                    $this->errors++;
                    continue;
                }
            }

            // Resolve old category filter if specified (for shared objects like 'cup' in multiple categories)
            $oldCategory = null;
            if (!empty($mapping['old_category'])) {
                $oldCategory = Category::where('key', $mapping['old_category'])->first();
                if (!$oldCategory) {
                    continue; // Old category doesn't exist, no tags to remap
                }
            }

            // Count affected rows
            $query = PhotoTag::where('litter_object_id', $oldObject->id);
            if ($oldCategory) {
                $query->where('category_id', $oldCategory->id);
            }
            $count = $query->count();

            if ($count === 0) {
                continue;
            }

            $this->line("  {$mapping['old_object']} → {$mapping['new_category']}/{$mapping['new_object']}" .
                ($typeId ? " (type: {$mapping['type']})" : '') .
                " [{$count} rows]");

            if (!$dryRun) {
                $updateData = [
                    'category_id' => $newCategory->id,
                    'litter_object_id' => $newObject->id,
                    'category_litter_object_id' => $newCLO->id,
                ];
                if ($typeId) {
                    $updateData['litter_object_type_id'] = $typeId;
                }

                $updateQuery = PhotoTag::where('litter_object_id', $oldObject->id);
                if ($oldCategory) {
                    $updateQuery->where('category_id', $oldCategory->id);
                }
                $updateQuery->update($updateData);
            }

            $this->mapped += $count;
        }
    }

    /**
     * Remap taggables from old CLOs to new CLOs.
     */
    protected function remapTaggables(bool $dryRun): void
    {
        $remaps = $this->getTaggableRemaps();

        foreach ($remaps as $remap) {
            $oldCat = Category::where('key', $remap['old_category'])->first();
            $oldObj = LitterObject::where('key', $remap['old_object'])->first();
            $newCat = Category::where('key', $remap['new_category'])->first();
            $newObj = LitterObject::where('key', $remap['new_object'])->first();

            if (!$oldCat || !$oldObj || !$newCat || !$newObj) {
                continue;
            }

            $oldCLO = CategoryObject::where('category_id', $oldCat->id)
                ->where('litter_object_id', $oldObj->id)->first();
            $newCLO = CategoryObject::where('category_id', $newCat->id)
                ->where('litter_object_id', $newObj->id)->first();

            if (!$oldCLO || !$newCLO || $oldCLO->id === $newCLO->id) {
                continue;
            }

            $count = Taggable::where('category_litter_object_id', $oldCLO->id)->count();
            if ($count === 0) {
                continue;
            }

            $this->line("  Taggables: CLO {$oldCLO->id} ({$remap['old_category']}/{$remap['old_object']}) → CLO {$newCLO->id} ({$remap['new_category']}/{$remap['new_object']}) [{$count} rows]");

            if (!$dryRun) {
                // Handle potential duplicate taggables (same new CLO + taggable_type + taggable_id)
                // Delete duplicates first, then update remaining
                $existingNewTaggables = Taggable::where('category_litter_object_id', $newCLO->id)
                    ->get(['taggable_type', 'taggable_id']);

                if ($existingNewTaggables->isNotEmpty()) {
                    // Delete old taggables that would be duplicates
                    foreach ($existingNewTaggables as $existing) {
                        Taggable::where('category_litter_object_id', $oldCLO->id)
                            ->where('taggable_type', $existing->taggable_type)
                            ->where('taggable_id', $existing->taggable_id)
                            ->delete();
                    }
                }

                // Update remaining
                Taggable::where('category_litter_object_id', $oldCLO->id)
                    ->update(['category_litter_object_id' => $newCLO->id]);
            }

            $this->taggablesRemapped += $count;
        }
    }

    /**
     * Backfill category_litter_object_id for tags that have category+object but no CLO.
     */
    protected function backfillCloIds(bool $dryRun): void
    {
        $count = PhotoTag::whereNull('category_litter_object_id')
            ->whereNotNull('category_id')
            ->whereNotNull('litter_object_id')
            ->count();

        $this->line("  Tags needing CLO backfill: {$count}");

        if ($count === 0 || $dryRun) {
            $this->backfilled = $count;
            return;
        }

        PhotoTag::whereNull('category_litter_object_id')
            ->whereNotNull('category_id')
            ->whereNotNull('litter_object_id')
            ->chunkById(1000, function ($tags) {
                foreach ($tags as $tag) {
                    $clo = CategoryObject::where('category_id', $tag->category_id)
                        ->where('litter_object_id', $tag->litter_object_id)
                        ->first();

                    if ($clo) {
                        $tag->update(['category_litter_object_id' => $clo->id]);
                        $this->backfilled++;
                    } else {
                        Log::warning('olm:consolidate-objects: CLO missing', [
                            'photo_tag_id' => $tag->id,
                            'category_id' => $tag->category_id,
                            'litter_object_id' => $tag->litter_object_id,
                        ]);
                        $this->errors++;
                    }
                }
            });
    }

    /**
     * Handle tags where both category_id and litter_object_id are null.
     * Map to unclassified + other.
     */
    protected function handleNullNullTags(bool $dryRun): void
    {
        $count = PhotoTag::whereNull('category_litter_object_id')
            ->where(function ($q) {
                $q->whereNull('category_id')
                  ->orWhereNull('litter_object_id');
            })
            ->count();

        $this->line("  Null-null/partial tags: {$count}");

        if ($count === 0 || $dryRun) {
            $this->nullNull = $count;
            return;
        }

        $unclassifiedCat = Category::where('key', CategoryKey::Unclassified->value)->first();
        $otherObject = LitterObject::where('key', 'other')->first();

        if (!$unclassifiedCat || !$otherObject) {
            $this->error('Missing unclassified category or other object');
            $this->errors += $count;
            return;
        }

        $unclassifiedCLO = CategoryObject::firstOrCreate([
            'category_id' => $unclassifiedCat->id,
            'litter_object_id' => $otherObject->id,
        ]);

        $updated = PhotoTag::whereNull('category_litter_object_id')
            ->where(function ($q) {
                $q->whereNull('category_id')
                  ->orWhereNull('litter_object_id');
            })
            ->update([
                'category_id' => $unclassifiedCat->id,
                'litter_object_id' => $otherObject->id,
                'category_litter_object_id' => $unclassifiedCLO->id,
            ]);

        $this->nullNull = $updated;
    }

    /**
     * Complete mapping of old → new objects.
     * Each entry: old_object, new_object, new_category, optional type, optional old_category.
     */
    protected function getObjectMappings(): array
    {
        return [
            // ── Alcohol: prefixed → canonical + type ──
            ['old_object' => 'beer_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'beer'],
            ['old_object' => 'cider_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'cider'],
            ['old_object' => 'spirits_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'spirits'],
            ['old_object' => 'wine_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'wine'],
            ['old_object' => 'beer_can', 'new_object' => 'can', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'beer'],
            ['old_object' => 'spirits_can', 'new_object' => 'can', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'spirits'],
            ['old_object' => 'cider_can', 'new_object' => 'can', 'new_category' => CategoryKey::Alcohol->value, 'type' => 'cider'],

            // Alcohol: camelCase → snake_case
            ['old_object' => 'bottleTop', 'new_object' => 'bottle_cap', 'new_category' => CategoryKey::Alcohol->value, 'type' => null],
            ['old_object' => 'bottletops', 'new_object' => 'bottle_cap', 'new_category' => CategoryKey::Alcohol->value, 'type' => null],
            ['old_object' => 'sixPackRings', 'new_object' => 'six_pack_rings', 'new_category' => CategoryKey::Alcohol->value, 'type' => null],
            ['old_object' => 'brokenGlass', 'new_object' => 'broken_glass', 'new_category' => CategoryKey::Alcohol->value, 'type' => null, 'old_category' => CategoryKey::Alcohol->value],
            ['old_object' => 'pullRing', 'new_object' => 'pull_ring', 'new_category' => CategoryKey::Alcohol->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],

            // ── Softdrinks: prefixed → canonical + type: prefixed → canonical + type ──
            ['old_object' => 'water_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'water'],
            ['old_object' => 'fizzy_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'soda'],
            ['old_object' => 'juice_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'juice'],
            ['old_object' => 'energy_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'energy'],
            ['old_object' => 'sports_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'sports'],
            ['old_object' => 'iceTea_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'tea'],
            ['old_object' => 'milk_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'milk'],
            ['old_object' => 'smoothie_bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'smoothie'],
            ['old_object' => 'soda_can', 'new_object' => 'can', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'soda'],
            ['old_object' => 'energy_can', 'new_object' => 'can', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'energy'],
            ['old_object' => 'juice_can', 'new_object' => 'can', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'juice'],
            ['old_object' => 'icedTea_can', 'new_object' => 'can', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'iced_tea'],
            ['old_object' => 'sparklingWater_can', 'new_object' => 'can', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'sparkling_water'],
            ['old_object' => 'juice_carton', 'new_object' => 'carton', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'juice'],
            ['old_object' => 'milk_carton', 'new_object' => 'carton', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'milk'],
            ['old_object' => 'icedTea_carton', 'new_object' => 'carton', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'iced_tea'],
            ['old_object' => 'plantMilk_carton', 'new_object' => 'carton', 'new_category' => CategoryKey::Softdrinks->value, 'type' => 'plant_milk'],
            ['old_object' => 'drinkingGlass', 'new_object' => 'broken_glass', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null],
            ['old_object' => 'straw_packaging', 'new_object' => 'straw_wrapper', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null],

            // Softdrinks category merge (objects that keep their key) (objects that keep their key)
            ['old_object' => 'cup', 'new_object' => 'cup', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'brokenGlass', 'new_object' => 'broken_glass', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'lid', 'new_object' => 'lid', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'label', 'new_object' => 'label', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'straw', 'new_object' => 'straw', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'packaging', 'new_object' => 'packaging', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'juice_pouch', 'new_object' => 'juice_pouch', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Softdrinks->value],

            // ── Coffee → Softdrinks (category merge) ──
            ['old_object' => 'cup', 'new_object' => 'cup', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],
            ['old_object' => 'lid', 'new_object' => 'lid', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],
            ['old_object' => 'pod', 'new_object' => 'coffee_pod', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null],
            ['old_object' => 'sleeves', 'new_object' => 'packaging', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],
            ['old_object' => 'stirrer', 'new_object' => 'cutlery', 'new_category' => CategoryKey::Food->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],
            ['old_object' => 'packaging', 'new_object' => 'packaging', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coffee->value],

            // ── Smoking: camelCase → snake_case ──
            ['old_object' => 'tobaccoPouch', 'new_object' => 'tobacco_pouch', 'new_category' => CategoryKey::Smoking->value, 'type' => null],
            ['old_object' => 'rollingPapers', 'new_object' => 'rolling_papers', 'new_category' => CategoryKey::Smoking->value, 'type' => null],
            ['old_object' => 'vapePen', 'new_object' => 'vape_pen', 'new_category' => CategoryKey::Smoking->value, 'type' => null],
            ['old_object' => 'vapeOil', 'new_object' => 'vape_cartridge', 'new_category' => CategoryKey::Smoking->value, 'type' => null],

            // ── Sanitary → split: personal_care + medical ──
            // Personal care items
            ['old_object' => 'wipes', 'new_object' => 'wipes', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'wetwipes', 'new_object' => 'wipes', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'nappies', 'new_object' => 'nappies', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'earSwabs', 'new_object' => 'ear_swabs', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'toothbrush', 'new_object' => 'toothbrush', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'toothpasteTube', 'new_object' => 'toothpaste_tube', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'toothpasteBox', 'new_object' => 'other', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'dentalFloss', 'new_object' => 'dental_floss', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'deodorant_can', 'new_object' => 'deodorant_can', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'sanitaryPad', 'new_object' => 'sanitary_pad', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'tampon', 'new_object' => 'tampon', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'menstrual', 'new_object' => 'menstrual_cup', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'condoms', 'new_object' => 'condom', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'condom_wrapper', 'new_object' => 'condom_wrapper', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'mouthwashBottle', 'new_object' => 'other', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'toothpick', 'new_object' => 'other', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'hair_tie', 'new_object' => 'other', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],
            ['old_object' => 'ear_plugs', 'new_object' => 'other', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null],

            // Medical items (from sanitary)
            ['old_object' => 'syringe', 'new_object' => 'syringe', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'pillPack', 'new_object' => 'pill_pack', 'new_category' => CategoryKey::Medical->value, 'type' => null],
            ['old_object' => 'medicineBottle', 'new_object' => 'medicine_bottle', 'new_category' => CategoryKey::Medical->value, 'type' => null],
            ['old_object' => 'bandage', 'new_object' => 'bandage', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'plaster', 'new_object' => 'plaster', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'gloves', 'new_object' => 'gloves', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'facemask', 'new_object' => 'face_mask', 'new_category' => CategoryKey::Medical->value, 'type' => null],
            ['old_object' => 'sanitiser', 'new_object' => 'sanitiser', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Medical->value, 'type' => null, 'old_category' => CategoryKey::Sanitary->value],

            // ── Automobile → Vehicles ──
            ['old_object' => 'car_part', 'new_object' => 'car_part', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'battery', 'new_object' => 'battery', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'alloy', 'new_object' => 'wheel', 'new_category' => CategoryKey::Vehicles->value, 'type' => null],
            ['old_object' => 'bumper', 'new_object' => 'bumper', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'exhaust', 'new_object' => 'car_part', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'engine', 'new_object' => 'car_part', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'mirror', 'new_object' => 'mirror', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'light', 'new_object' => 'light', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'license_plate', 'new_object' => 'license_plate', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'oil_can', 'new_object' => 'other', 'new_category' => CategoryKey::Vehicles->value, 'type' => null],
            ['old_object' => 'tyre', 'new_object' => 'tyre', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'wheel', 'new_object' => 'wheel', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Vehicles->value, 'type' => null, 'old_category' => CategoryKey::Automobile->value],
            ['old_object' => 'automobile', 'new_object' => 'car_part', 'new_category' => CategoryKey::Vehicles->value, 'type' => null],

            // ── Coastal → Marine ──
            ['old_object' => 'buoys', 'new_object' => 'buoy', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'rope', 'new_object' => 'rope', 'new_category' => CategoryKey::Marine->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'fishing_nets', 'new_object' => 'fishing_net', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'plastics', 'new_object' => 'macroplastics', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'microplastics', 'new_object' => 'microplastics', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'mediumplastics', 'new_object' => 'macroplastics', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'macroplastics', 'new_object' => 'macroplastics', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'styrofoam', 'new_object' => 'styrofoam', 'new_category' => CategoryKey::Marine->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'shotgun_cartridges', 'new_object' => 'shotgun_cartridge', 'new_category' => CategoryKey::Marine->value, 'type' => null],

            // Coastal duplicates → existing canonical categories
            ['old_object' => 'bag', 'new_object' => 'bag', 'new_category' => CategoryKey::Food->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'bottle', 'new_object' => 'bottle', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'straws', 'new_object' => 'straw', 'new_category' => CategoryKey::Softdrinks->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'lighters', 'new_object' => 'lighters', 'new_category' => CategoryKey::Smoking->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'balloons', 'new_object' => 'other', 'new_category' => CategoryKey::Marine->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'lego', 'new_object' => 'other', 'new_category' => CategoryKey::Marine->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Marine->value, 'type' => null, 'old_category' => CategoryKey::Coastal->value],

            // ── Dumping → Industrial ──
            ['old_object' => 'dumping', 'new_object' => 'dumping_small', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'dump', 'new_object' => 'dumping_small', 'new_category' => CategoryKey::Industrial->value, 'type' => null],

            // Industrial: camelCase → snake_case
            ['old_object' => 'oilDrum', 'new_object' => 'oil_drum', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'paintCan', 'new_object' => 'container', 'new_category' => CategoryKey::Industrial->value, 'type' => null],

            // ── Electronics: camelCase → snake_case ──
            ['old_object' => 'mobilePhone', 'new_object' => 'phone', 'new_category' => CategoryKey::Electronics->value, 'type' => null],
            ['old_object' => 'laptop', 'new_object' => 'other', 'new_category' => CategoryKey::Electronics->value, 'type' => null],
            ['old_object' => 'tablet', 'new_object' => 'other', 'new_category' => CategoryKey::Electronics->value, 'type' => null],
            ['old_object' => 'batteries', 'new_object' => 'battery', 'new_category' => CategoryKey::Electronics->value, 'type' => null],
            ['old_object' => 'elec_small', 'new_object' => 'other', 'new_category' => CategoryKey::Electronics->value, 'type' => null],
            ['old_object' => 'elec_large', 'new_object' => 'other', 'new_category' => CategoryKey::Electronics->value, 'type' => null],

            // ── Pets: rename ──
            ['old_object' => 'dogshit', 'new_object' => 'dog_waste', 'new_category' => CategoryKey::Pets->value, 'type' => null],
            ['old_object' => 'dogshit_in_bag', 'new_object' => 'dog_waste_in_bag', 'new_category' => CategoryKey::Pets->value, 'type' => null],

            // ── Other category → redistribute ──
            ['old_object' => 'randomLitter', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'bagsLitter', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'overflowingBins', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'plasticBags', 'new_object' => 'bag', 'new_category' => CategoryKey::Food->value, 'type' => null],
            ['old_object' => 'trafficCone', 'new_object' => 'other', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'washingUp', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'cableTie', 'new_object' => 'other', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'clothing', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Other->value],
            ['old_object' => 'balloons', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Other->value],
            ['old_object' => 'life_buoy', 'new_object' => 'buoy', 'new_category' => CategoryKey::Marine->value, 'type' => null],
            ['old_object' => 'furniture', 'new_object' => 'dumping_medium', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'mattress', 'new_object' => 'dumping_large', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'appliance', 'new_object' => 'dumping_large', 'new_category' => CategoryKey::Industrial->value, 'type' => null],
            ['old_object' => 'graffiti', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'umbrella', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'posters', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Other->value],

            // ── Food: crisps size variants ──
            ['old_object' => 'crisp_small', 'new_object' => 'crisp_packet', 'new_category' => CategoryKey::Food->value, 'type' => null],
            ['old_object' => 'crisp_large', 'new_object' => 'crisp_packet', 'new_category' => CategoryKey::Food->value, 'type' => null],
            ['old_object' => 'glass_jar', 'new_object' => 'jar', 'new_category' => CategoryKey::Food->value, 'type' => null],

            // ── Canonical object renames (Phase 2.1) ──
            ['old_object' => 'filters', 'new_object' => 'cigarette_filter', 'new_category' => CategoryKey::Smoking->value, 'type' => null, 'old_category' => CategoryKey::Smoking->value],
            ['old_object' => 'menstrual', 'new_object' => 'menstrual_cup', 'new_category' => CategoryKey::PersonalCare->value, 'type' => null, 'old_category' => CategoryKey::PersonalCare->value],
            ['old_object' => 'crisps', 'new_object' => 'crisp_packet', 'new_category' => CategoryKey::Food->value, 'type' => null, 'old_category' => CategoryKey::Food->value],
            ['old_object' => 'oil', 'new_object' => 'oil_container', 'new_category' => CategoryKey::Industrial->value, 'type' => null, 'old_category' => CategoryKey::Industrial->value],
            ['old_object' => 'chemical', 'new_object' => 'chemical_container', 'new_category' => CategoryKey::Industrial->value, 'type' => null, 'old_category' => CategoryKey::Industrial->value],

            // ── Material category → unclassified ──
            ['old_object' => 'plastic', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Material->value],
            ['old_object' => 'paper', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Other->value],
            ['old_object' => 'metal', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Other->value],
            ['old_object' => 'aluminium', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'wood', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'copper', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],
            ['old_object' => 'titanium', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],

            // ── Industrial: plastic (duplicate of material) ──
            ['old_object' => 'plastic', 'new_object' => 'other', 'new_category' => CategoryKey::Industrial->value, 'type' => null, 'old_category' => CategoryKey::Industrial->value],

            // ── Art → unclassified ──
            ['old_object' => 'item', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null],

            // ── Stationery → unclassified (small category, keep data) ──
            ['old_object' => 'book', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'pen', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'pencil', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'magazine', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'marker', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'notebook', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'stapler', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'paperClip', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'rubberBand', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
            ['old_object' => 'other', 'new_object' => 'other', 'new_category' => CategoryKey::Unclassified->value, 'type' => null, 'old_category' => CategoryKey::Stationery->value],
        ];
    }

    /**
     * Taggable remaps: old CLO → new CLO.
     * Only needed where the category+object combo changes (different CLO).
     */
    protected function getTaggableRemaps(): array
    {
        $remaps = [];

        // For every object mapping where the CLO identity changes, create a remap
        foreach ($this->getObjectMappings() as $m) {
            $oldCat = $m['old_category'] ?? null;

            // If no old_category specified, try to infer from existing CLO data
            if (!$oldCat) {
                // For objects like beer_bottle, the old category is the same as the TagsConfig category
                // We need to determine this from the existing CLO rows
                $oldObj = LitterObject::where('key', $m['old_object'])->first();
                if (!$oldObj) {
                    continue;
                }

                $existingClos = CategoryObject::where('litter_object_id', $oldObj->id)->get();
                foreach ($existingClos as $clo) {
                    $category = Category::find($clo->category_id);
                    if ($category) {
                        $remaps[] = [
                            'old_category' => $category->key,
                            'old_object' => $m['old_object'],
                            'new_category' => $m['new_category'],
                            'new_object' => $m['new_object'],
                        ];
                    }
                }
            } else {
                $remaps[] = [
                    'old_category' => $oldCat,
                    'old_object' => $m['old_object'],
                    'new_category' => $m['new_category'],
                    'new_object' => $m['new_object'],
                ];
            }
        }

        return $remaps;
    }
}
