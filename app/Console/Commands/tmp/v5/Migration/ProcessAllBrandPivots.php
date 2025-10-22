<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Services\Tags\ClassifyTagsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAllBrandPivots extends Command
{
    protected $signature = 'olm:v5:process-all-brands
        {--batch=5000 : Photos per batch}
        {--dry-run : Preview without creating pivots}';

    protected $description = 'Process ALL photos with brands to create comprehensive pivots';

    private ClassifyTagsService $classifyTags;
    private array $createdPivots = [];
    private array $unmatchedCases = [];
    private int $photosProcessed = 0;
    private int $photosWithBrands = 0;
    private int $pivotsCreated = 0;

    public function __construct(ClassifyTagsService $classifyTags)
    {
        parent::__construct();
        $this->classifyTags = $classifyTags;
    }

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');

        // Count total photos with brands
        $totalPhotos = DB::table('photos')->whereNotNull('brands_id')->count();

        $this->info("════════════════════════════════════════════");
        $this->info("Processing ALL Brand-Object Relationships");
        $this->info("════════════════════════════════════════════");
        $this->info("Total photos with brands: " . number_format($totalPhotos));
        $this->info("Batch size: " . number_format($batchSize));
        $this->info("Mode: " . ($dryRun ? "DRY RUN" : "LIVE"));
        $this->newLine();

        $startTime = microtime(true);

        // Process in batches using chunk
        $bar = $this->output->createProgressBar($totalPhotos);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% / %estimated:-6s% | Memory: %memory:6s%');
        $bar->start();

        DB::table('photos')
            ->whereNotNull('brands_id')
            ->orderBy('id')
            ->chunk($batchSize, function ($photos) use ($bar, $dryRun) {
                foreach ($photos as $photo) {
                    $this->processPhoto($photo, $dryRun);
                    $bar->advance();
                }

                // Force garbage collection periodically
                if ($this->photosProcessed % 10000 === 0) {
                    gc_collect_cycles();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Report results
        $duration = round(microtime(true) - $startTime, 2);
        $this->displayResults($duration, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Process a single photo applying the matching rules
     */
    private function processPhoto($photo, bool $dryRun): void
    {
        $this->photosProcessed++;

        // Get brands from THIS photo
        $brands = $this->getBrands($photo);
        if (empty($brands)) {
            return;
        }

        $this->photosWithBrands++;

        // Get objects from THIS photo ONLY
        $objects = $this->getObjects($photo);
        if (empty($objects)) {
            // Photo has brands but no objects - valid for brands-only photos
            // These will be handled during migration as brands-only PhotoTags
            // No pivots needed here
            return;
        }

        // Apply matching rules ONLY between brands and objects in THIS photo
        $this->applyMatchingRules($photo->id, $brands, $objects, $dryRun);
    }

    /**
     * Apply the 5 matching rules
     */
    private function applyMatchingRules(int $photoId, array $brands, array $objects, bool $dryRun): void
    {
        $brandCount = count($brands);
        $objectCount = count($objects);

        // Rule 1: Single object + Single brand = Always match
        if ($objectCount === 1 && $brandCount === 1) {
            $brand = array_key_first($brands);
            $object = $objects[0];

            if (!$dryRun) {
                $this->createPivot($object['category_id'], $object['object_key'], $brand);
            }
            $this->pivotsCreated++;
        }
        // Rule 3: Single object + Multiple brands = All brands to single object
        elseif ($objectCount === 1 && $brandCount > 1) {
            $object = $objects[0];

            foreach ($brands as $brandKey => $brandQty) {
                if (!$dryRun) {
                    $this->createPivot($object['category_id'], $object['object_key'], $brandKey);
                }
                $this->pivotsCreated++;
            }
        }
        // Rule 2: Multiple objects + Single brand = Brand to highest quantity object
        elseif ($objectCount > 1 && $brandCount === 1) {
            $brand = array_key_first($brands);

            // Find object with highest quantity
            $highestQtyObject = $this->getHighestQuantityObject($objects);

            if (!$dryRun) {
                $this->createPivot($highestQtyObject['category_id'], $highestQtyObject['object_key'], $brand);
            }
            $this->pivotsCreated++;
        }
        // Rule 4 & 5: Multiple objects + Multiple brands = Match by quantity
        else {
            $this->matchByQuantity($photoId, $brands, $objects, $dryRun);
        }
    }

    /**
     * Match multiple brands to multiple objects by quantity
     */
    private function matchByQuantity(int $photoId, array $brands, array $objects, bool $dryRun): void
    {
        $matchedObjects = [];
        $unmatchedBrands = [];

        // Sort both by quantity descending for better matching
        arsort($brands);
        usort($objects, fn($a, $b) => $b['quantity'] <=> $a['quantity']);

        // First pass: Exact quantity matches
        foreach ($brands as $brandKey => $brandQty) {
            $matched = false;

            foreach ($objects as $index => $object) {
                // Skip if object already matched
                if (in_array($index, $matchedObjects)) {
                    continue;
                }

                // Exact quantity match
                if ($brandQty === $object['quantity']) {
                    if (!$dryRun) {
                        $this->createPivot($object['category_id'], $object['object_key'], $brandKey);
                    }
                    $this->pivotsCreated++;
                    $matchedObjects[] = $index;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $unmatchedBrands[$brandKey] = $brandQty;
            }
        }

        // Second pass: Unmatched brands go to highest available object
        if (!empty($unmatchedBrands)) {
            foreach ($unmatchedBrands as $brandKey => $brandQty) {
                // Find highest quantity unmatched object
                $highestUnmatched = null;
                foreach ($objects as $index => $object) {
                    if (!in_array($index, $matchedObjects)) {
                        $highestUnmatched = $object;
                        $matchedObjects[] = $index;
                        break;
                    }
                }

                if ($highestUnmatched) {
                    if (!$dryRun) {
                        $this->createPivot($highestUnmatched['category_id'], $highestUnmatched['object_key'], $brandKey);
                    }
                    $this->pivotsCreated++;
                } else {
                    // No available objects left
                    $this->unmatchedCases[] = [
                        'photo_id' => $photoId,
                        'brand' => $brandKey,
                        'quantity' => $brandQty,
                        'reason' => 'no_available_objects'
                    ];
                }
            }
        }
    }

    /**
     * Get object with highest quantity
     */
    private function getHighestQuantityObject(array $objects): array
    {
        usort($objects, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        return $objects[0];
    }

    /**
     * Get brands from photo
     */
    private function getBrands($photo): array
    {
        if (!$photo->brands_id) {
            return [];
        }

        $brandRecord = DB::table('brands')
            ->where('id', $photo->brands_id)
            ->first();

        if (!$brandRecord) {
            return [];
        }

        $brands = [];
        $excludeColumns = ['id', 'created_at', 'updated_at'];

        foreach ((array)$brandRecord as $column => $value) {
            if (!in_array($column, $excludeColumns) && $value > 0) {
                $brands[$column] = (int)$value;
            }
        }

        return $brands;
    }

    /**
     * Get objects from photo
     */
    private function getObjects($photo): array
    {
        $objects = [];

        // Define category tables to check
        $categoryTables = [
            'smoking' => 'smoking',
            'alcohol' => 'alcohol',
            'coffee' => 'coffee',
            'food' => 'food',
            'softdrinks' => 'softdrinks',
            'other' => 'other',
            'coastal' => 'coastal',
            'sanitary' => 'sanitary',
            'industrial' => 'industrial',
            'dumping' => 'dumping',
            'art' => 'art',
        ];

        foreach ($categoryTables as $categoryKey => $tableName) {
            $fkColumn = $tableName . '_id';

            if (!isset($photo->$fkColumn) || !$photo->$fkColumn) {
                continue;
            }

            $categoryId = DB::table('categories')
                ->where('key', $categoryKey)
                ->value('id');

            if (!$categoryId) {
                continue;
            }

            $record = DB::table($tableName)
                ->where('id', $photo->$fkColumn)
                ->first();

            if (!$record) {
                continue;
            }

            $excludeColumns = ['id', 'created_at', 'updated_at', 'user_id', 'photo_id'];

            foreach ((array)$record as $column => $value) {
                if (!in_array($column, $excludeColumns) && $value > 0) {
                    // Map deprecated tags to new objects
                    $mapping = ClassifyTagsService::normalizeDeprecatedTag($column);
                    $objectKey = $mapping ? ($mapping['object'] ?? $column) : $column;

                    $objects[] = [
                        'category_id' => $categoryId,
                        'category_key' => $categoryKey,
                        'object_key' => $objectKey,
                        'original_key' => $column,
                        'quantity' => (int)$value
                    ];
                }
            }
        }

        return $objects;
    }

    /**
     * Create a pivot relationship
     */
    private function createPivot(int $categoryId, string $objectKey, string $brandKey): bool
    {
        try {
            // Create a unique key for this pivot to track what we've already created
            $pivotKey = "{$categoryId}.{$objectKey}.{$brandKey}";

            // Skip if we've already created this exact pivot
            if (in_array($pivotKey, $this->createdPivots)) {
                return false;
            }

            // Get or create object ID
            $objectId = DB::table('litter_objects')
                ->where('key', $objectKey)
                ->value('id');

            if (!$objectId) {
                $objectId = DB::table('litter_objects')->insertGetId([
                    'key' => $objectKey,
                    'crowdsourced' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Get or create brand ID
            $brandId = DB::table('brandslist')
                ->where('key', $brandKey)
                ->value('id');

            if (!$brandId) {
                $brandId = DB::table('brandslist')->insertGetId([
                    'key' => $brandKey,
                    'crowdsourced' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Get or create CategoryObject
            $categoryObject = DB::table('category_litter_object')
                ->where('category_id', $categoryId)
                ->where('litter_object_id', $objectId)
                ->first();

            if (!$categoryObject) {
                $categoryObjectId = DB::table('category_litter_object')->insertGetId([
                    'category_id' => $categoryId,
                    'litter_object_id' => $objectId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $categoryObjectId = $categoryObject->id;
            }

            // Check if taggable already exists
            $exists = DB::table('taggables')
                ->where('category_litter_object_id', $categoryObjectId)
                ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
                ->where('taggable_id', $brandId)
                ->exists();

            if (!$exists) {
                DB::table('taggables')->insert([
                    'category_litter_object_id' => $categoryObjectId,
                    'taggable_type' => 'App\\Models\\Litter\\Tags\\BrandList',
                    'taggable_id' => $brandId,
                    'quantity' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Track that we created this pivot
                $this->createdPivots[] = $pivotKey;

                return true;
            }

            // Pivot already exists, track it anyway
            $this->createdPivots[] = $pivotKey;

            return false;

        } catch (\Exception $e) {
            Log::error("Failed to create pivot", [
                'category_id' => $categoryId,
                'object_key' => $objectKey,
                'brand_key' => $brandKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Display results
     */
    private function displayResults(float $duration, bool $dryRun): void
    {
        $this->info("\n════════════════════════════════════════");
        $this->info("Processing Complete");
        $this->info("════════════════════════════════════════");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Photos processed', number_format($this->photosProcessed)],
                ['Photos with brands', number_format($this->photosWithBrands)],
                ['Pivots ' . ($dryRun ? 'would be created' : 'created'), number_format($this->pivotsCreated)],
                ['Unique relationships', number_format(count(array_unique($this->createdPivots)))],
                ['Unmatched cases', number_format(count($this->unmatchedCases))],
                ['Processing time', round($duration, 2) . ' seconds'],
                ['Speed', round($this->photosProcessed / $duration, 0) . ' photos/sec'],
                ['Memory peak', round(memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MB'],
            ]
        );

        // Save detailed logs
        if (!$dryRun) {
            $timestamp = date('Y-m-d_His');

            // Save created pivots
            if (!empty($this->createdPivots)) {
                $pivotFile = storage_path("logs/brand_pivots_created_{$timestamp}.json");
                $pivotSummary = array_count_values($this->createdPivots);
                arsort($pivotSummary);
                file_put_contents($pivotFile, json_encode($pivotSummary, JSON_PRETTY_PRINT));
                $this->info("\nCreated pivots saved to: {$pivotFile}");
            }

            // Save unmatched cases
            if (!empty($this->unmatchedCases)) {
                $unmatchedFile = storage_path("logs/brand_unmatched_{$timestamp}.json");
                file_put_contents($unmatchedFile, json_encode($this->unmatchedCases, JSON_PRETTY_PRINT));
                $this->info("Unmatched cases saved to: {$unmatchedFile}");
            }
        }
    }
}
