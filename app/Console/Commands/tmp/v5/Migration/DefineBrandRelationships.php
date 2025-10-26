<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DefineBrandRelationships extends Command
{
    protected $signature = 'olm:v5:define-relationships
                            {--all : Analyze ALL brands, not just top N}
                            {--top=500 : Number of top brands if not using --all}
                            {--export : Export to JSON for review}
                            {--import= : Import reviewed JSON}
                            {--clear : Clear existing relationships first}';

    protected $description = 'Define EXACT brand-object relationships with proper tag validation';

    private array $relationships = [];
    private array $validObjectsByCategory = [];
    private array $stats = [
        'guaranteed_1to1' => 0,
        'consistent_patterns' => 0,
        'ambiguous_skipped' => 0,
    ];

    public function handle()
    {
        // Load valid objects from database
        $this->loadValidObjects();

        if ($importFile = $this->option('import')) {
            $this->importRelationships($importFile);
            return;
        }

        if ($this->option('clear')) {
            $this->clearExistingRelationships();
        }

        $this->info('Finding DEFINITIVE Brand-Object Relationships (with validation)');
        $this->info('===============================================================');

        // Get brands to analyze
        if ($this->option('all')) {
            $topBrands = $this->getAllBrands();
            $this->info("Analyzing ALL " . count($topBrands) . " brands\n");
        } else {
            $topBrands = $this->getTopBrands($this->option('top'));
            $this->info("Analyzing top {$this->option('top')} brands\n");
        }

        // Phase 1: Find guaranteed 1:1 relationships
        $this->info("Phase 1: Finding guaranteed 1:1 relationships...");
        $this->findGuaranteedRelationships($topBrands);

        // Phase 2: Find consistent patterns
        $this->info("\nPhase 2: Finding consistent patterns...");
        $this->findConsistentPatterns($topBrands);

        // Display results
        $this->info("\n" . str_repeat("=", 50));
        $this->displayStatistics();

        if ($this->option('export')) {
            $this->exportForReview();
        } else {
            $this->saveRelationships();
        }
    }

    private function loadValidObjects(): void
    {
        $this->info("Loading valid objects from database...");

        $objects = DB::table('litter_objects')
            ->join('category_litter_object', 'litter_objects.id', '=', 'category_litter_object.litter_object_id')
            ->join('categories', 'category_litter_object.category_id', '=', 'categories.id')
            ->select('categories.key as category', 'litter_objects.key as object')
            ->get();

        foreach ($objects as $obj) {
            if (!isset($this->validObjectsByCategory[$obj->category])) {
                $this->validObjectsByCategory[$obj->category] = [];
            }
            $this->validObjectsByCategory[$obj->category][] = $obj->object;
        }

        $this->info("Loaded " . count($objects) . " valid objects across " . count($this->validObjectsByCategory) . " categories\n");
    }

    private function isValidObject(string $category, string $object): bool
    {
        return isset($this->validObjectsByCategory[$category])
            && in_array($object, $this->validObjectsByCategory[$category]);
    }

    private function normalizeObjectKey(string $category, string $object): ?string
    {
        // First check if it's already valid in the database
        if ($this->isValidObject($category, $object)) {
            return $object;
        }

        // Check ClassifyTagsService for deprecated tag mapping
        if (class_exists(\App\Services\ClassifyTagsService::class)) {
            $classifyService = app(\App\Services\ClassifyTagsService::class);

            // Use normalizeDeprecatedTag to get the mapping
            $normalized = $classifyService->normalizeDeprecatedTag($object);
            if ($normalized !== null && isset($normalized['object'])) {
                $mappedObject = $normalized['object'];
                if ($this->isValidObject($category, $mappedObject)) {
                    return $mappedObject;
                }
            }

            // Also try getKey method
            $mappedKey = $classifyService->getKey($category, $object);
            if ($mappedKey !== null && $this->isValidObject($category, $mappedKey)) {
                return $mappedKey;
            }
        }

        // No mapping found - the object doesn't exist in the new system
        return null;
    }

    private function getAllBrands(): array
    {
        // Get ALL brands from custom tags and brands table
        $brands = [];

        // From custom tags
        $customBrands = DB::table('custom_tags')
            ->selectRaw("LOWER(REPLACE(REPLACE(SUBSTRING(tag, 7), ' ', '_'), '-', '_')) as brand, COUNT(*) as count")
            ->where('tag', 'like', 'brand:%')
            ->groupBy('brand')
            ->orderByDesc('count')
            ->pluck('count', 'brand')
            ->toArray();

        foreach ($customBrands as $brand => $count) {
            $brands[$brand] = $count;
        }

        // From brands table columns
        $columns = DB::getSchemaBuilder()->getColumnListing('brands');
        foreach ($columns as $column) {
            if (in_array($column, ['id', 'created_at', 'updated_at'])) continue;

            $count = DB::table('brands')->where($column, '>', 0)->count();
            if ($count > 0 && !isset($brands[$column])) {
                $brands[$column] = $count;
            }
        }

        arsort($brands);
        return $brands;
    }

    private function getTopBrands(int $limit): array
    {
        $allBrands = $this->getAllBrands();
        return array_slice($allBrands, 0, $limit, true);
    }

    private function findGuaranteedRelationships(array $brands): void
    {
        $bar = $this->output->createProgressBar(count($brands));

        foreach ($brands as $brandKey => $count) {
            $photos = $this->findSimplePhotos($brandKey);

            foreach ($photos as $photo) {
                $tags = $photo->tags();
                unset($tags['brands']);

                // Count total valid objects across all categories
                $totalObjects = 0;
                $singleObject = null;

                foreach ($tags as $category => $objects) {
                    if (in_array($category, ['dogshit', 'pathways', 'art'])) continue;

                    foreach ($objects as $object => $qty) {
                        // Normalize and validate the object
                        $normalizedObject = $this->normalizeObjectKey($category, $object);
                        if ($normalizedObject === null) continue; // Skip invalid objects

                        $totalObjects++;
                        if ($totalObjects == 1) {
                            $singleObject = [
                                'category' => $category,
                                'object' => $normalizedObject,  // Use normalized key
                            ];
                        }
                    }
                }

                // GUARANTEED: 1 brand, 1 valid object = definite relationship
                if ($totalObjects == 1 && $singleObject) {
                    $this->addRelationship(
                        $brandKey,
                        $singleObject['category'],
                        $singleObject['object'],
                        'guaranteed_1to1'
                    );
                    $this->stats['guaranteed_1to1']++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info(" Found {$this->stats['guaranteed_1to1']} guaranteed relationships");
    }

    private function findConsistentPatterns(array $brands): void
    {
        $bar = $this->output->createProgressBar(count($brands));

        foreach ($brands as $brandKey => $count) {
            // Skip if already has guaranteed relationship
            if (isset($this->relationships[$brandKey])) {
                $bar->advance();
                continue;
            }

            $analysis = $this->analyzeBrandPattern($brandKey);

            if (!empty($analysis)) {
                foreach ($analysis as $pattern) {
                    // CONSISTENT: Brand appears with same valid object >90% of time
                    if ($pattern['consistency'] > 0.9 && $pattern['occurrences'] >= 10) {
                        $this->addRelationship(
                            $brandKey,
                            $pattern['category'],
                            $pattern['object'],
                            'consistent_pattern'
                        );
                        $this->stats['consistent_patterns']++;
                        break; // Take first consistent pattern
                    }
                }
            }

            if (!isset($this->relationships[$brandKey])) {
                $this->stats['ambiguous_skipped']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info(" Found {$this->stats['consistent_patterns']} consistent patterns");
    }

    private function findSimplePhotos(string $brandKey): array
    {
        $photoIds = [];

        // From custom tags
        $customIds = DB::table('custom_tags')
            ->whereRaw("LOWER(tag) = ?", ['brand:' . strtolower($brandKey)])
            ->pluck('photo_id');

        // From brands table
        if (DB::getSchemaBuilder()->hasColumn('brands', $brandKey)) {
            $brandIds = DB::table('photos')
                ->join('brands', 'photos.brands_id', '=', 'brands.id')
                ->where("brands.{$brandKey}", '>', 0)
                ->pluck('photos.id');

            $photoIds = $customIds->merge($brandIds)->unique();
        } else {
            $photoIds = $customIds;
        }

        // Filter to photos with only ONE brand
        $simplePhotos = [];
        foreach ($photoIds->take(100) as $photoId) {
            $photo = Photo::find($photoId);
            if (!$photo) continue;

            // Count brands in photo
            $brandCount = DB::table('custom_tags')
                ->where('photo_id', $photoId)
                ->where('tag', 'like', 'brand:%')
                ->count();

            if ($photo->brands_id) {
                $brandRecord = DB::table('brands')->where('id', $photo->brands_id)->first();
                if ($brandRecord) {
                    foreach ($brandRecord as $key => $value) {
                        if ($value > 0 && !in_array($key, ['id', 'created_at', 'updated_at'])) {
                            $brandCount++;
                        }
                    }
                }
            }

            // Only want photos with exactly 1 brand
            if ($brandCount == 1) {
                $simplePhotos[] = $photo;
            }
        }

        return $simplePhotos;
    }

    private function analyzeBrandPattern(string $brandKey): array
    {
        $photoIds = $this->getAllPhotosWithBrand($brandKey, 200);

        if (count($photoIds) < 10) {
            return [];
        }

        $patterns = [];

        foreach ($photoIds as $photoId) {
            $photo = Photo::find($photoId);
            if (!$photo) continue;

            $tags = $photo->tags();
            unset($tags['brands']);

            foreach ($tags as $category => $objects) {
                if (in_array($category, ['dogshit', 'pathways', 'art'])) continue;

                foreach ($objects as $object => $qty) {
                    // Normalize and validate
                    $normalizedObject = $this->normalizeObjectKey($category, $object);
                    if ($normalizedObject === null) continue;

                    $key = "{$category}|{$normalizedObject}";
                    $patterns[$key] = ($patterns[$key] ?? 0) + 1;
                }
            }
        }

        // Calculate consistency
        $results = [];
        $totalPhotos = count($photoIds);

        foreach ($patterns as $key => $occurrences) {
            [$category, $object] = explode('|', $key);
            $consistency = $occurrences / $totalPhotos;

            $results[] = [
                'category' => $category,
                'object' => $object,
                'occurrences' => $occurrences,
                'consistency' => $consistency,
            ];
        }

        // Sort by consistency
        usort($results, fn($a, $b) => $b['consistency'] <=> $a['consistency']);

        return $results;
    }

    private function getAllPhotosWithBrand(string $brandKey, int $limit = 500): array
    {
        $photoIds = [];

        // From custom tags
        $customIds = DB::table('custom_tags')
            ->whereRaw("LOWER(tag) = ?", ['brand:' . strtolower($brandKey)])
            ->limit($limit)
            ->pluck('photo_id')
            ->toArray();
        $photoIds = array_merge($photoIds, $customIds);

        // From brands table
        if (DB::getSchemaBuilder()->hasColumn('brands', $brandKey)) {
            $brandIds = DB::table('photos')
                ->join('brands', 'photos.brands_id', '=', 'brands.id')
                ->where("brands.{$brandKey}", '>', 0)
                ->limit($limit)
                ->pluck('photos.id')
                ->toArray();
            $photoIds = array_merge($photoIds, $brandIds);
        }

        return array_unique(array_slice($photoIds, 0, $limit));
    }

    private function addRelationship(string $brand, string $category, string $object, string $type): void
    {
        if (!isset($this->relationships[$brand])) {
            $this->relationships[$brand] = [
                'category' => $category,
                'object' => $object,
                'type' => $type,
            ];
        }
    }

    private function displayStatistics(): void
    {
        $this->info("\n📊 Results:");
        $this->info("  Guaranteed 1:1 matches: {$this->stats['guaranteed_1to1']}");
        $this->info("  Consistent patterns: {$this->stats['consistent_patterns']}");
        $this->info("  Ambiguous (skipped): {$this->stats['ambiguous_skipped']}");
        $this->info("  Total relationships defined: " . count($this->relationships));
    }

    private function exportForReview(): void
    {
        $export = [
            'generated_at' => now()->toIso8601String(),
            'stats' => $this->stats,
            'relationships' => [],
        ];

        foreach ($this->relationships as $brand => $rel) {
            $export['relationships'][$brand] = [
                'category' => $rel['category'],
                'object' => $rel['object'],
                'type' => $rel['type'],
                'approved' => true,
            ];
        }

        $filename = storage_path('app/brand_relationships_validated.json');
        File::put($filename, json_encode($export, JSON_PRETTY_PRINT));

        $this->info("\n✅ Exported to: {$filename}");
        $this->info("Review and import with: --import={$filename}");
    }

    private function saveRelationships(): void
    {
        $created = 0;

        foreach ($this->relationships as $brand => $rel) {
            if ($this->createRelationship($brand, $rel['category'], $rel['object'])) {
                $created++;
                $this->line("✓ {$brand} → {$rel['category']}.{$rel['object']}");
            }
        }

        $this->info("\n✅ Created {$created} relationships in database");
    }

    private function createRelationship(string $brandKey, string $categoryKey, string $objectKey): bool
    {
        // Validate that the object exists
        if (!$this->isValidObject($categoryKey, $objectKey)) {
            $this->warn("Invalid object: {$categoryKey}.{$objectKey}");
            return false;
        }

        // Get/create brand
        $brandId = DB::table('brandslist')->where('key', $brandKey)->value('id');
        if (!$brandId) {
            $brandId = DB::table('brandslist')->insertGetId([
                'key' => $brandKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get category and object IDs
        $categoryId = DB::table('categories')->where('key', $categoryKey)->value('id');
        $objectId = DB::table('litter_objects')->where('key', $objectKey)->value('id');

        if (!$categoryId || !$objectId) {
            return false;
        }

        // Get/create category_litter_object
        $clo = DB::table('category_litter_object')
            ->where('category_id', $categoryId)
            ->where('litter_object_id', $objectId)
            ->first();

        if (!$clo) {
            $cloId = DB::table('category_litter_object')->insertGetId([
                'category_id' => $categoryId,
                'litter_object_id' => $objectId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $cloId = $clo->id;
        }

        // Create taggable
        $exists = DB::table('taggables')
            ->where('category_litter_object_id', $cloId)
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->where('taggable_id', $brandId)
            ->exists();

        if (!$exists) {
            DB::table('taggables')->insert([
                'category_litter_object_id' => $cloId,
                'taggable_type' => 'App\\Models\\Litter\\Tags\\BrandList',
                'taggable_id' => $brandId,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    private function clearExistingRelationships(): void
    {
        $count = DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->delete();

        $this->info("Cleared {$count} existing relationships\n");
    }

    private function importRelationships(string $filename): void
    {
        if (!File::exists($filename)) {
            $this->error("File not found: {$filename}");
            return;
        }

        $data = json_decode(File::get($filename), true);
        $created = 0;

        foreach ($data['relationships'] as $brand => $rel) {
            if ($rel['approved']) {
                if ($this->createRelationship($brand, $rel['category'], $rel['object'])) {
                    $created++;
                }
            }
        }

        $this->info("✅ Imported {$created} relationships");
    }
}
