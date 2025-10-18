<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Services\Tags\ClassifyTagsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAllBrandPivots extends Command
{
    protected $signature = 'olm:v5:process-all-brands
                            {--batch=1000 : Photos per batch}
                            {--threshold=2 : Minimum occurrences to create pivot}
                            {--dry-run : Preview without creating pivots}
                            {--limit= : Process only N photos (for testing)}';

    protected $description = 'Process ALL photos with brands to create pivots';

    private ClassifyTagsService $classifyTags;
    private array $coOccurrences = [];
    private int $photosProcessed = 0;
    private int $photosWithBrands = 0;

    public function __construct(ClassifyTagsService $classifyTags)
    {
        parent::__construct();
        $this->classifyTags = $classifyTags;
    }

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $threshold = (int) $this->option('threshold');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        // Count total photos with brands
        $totalQuery = DB::table('photos')->whereNotNull('brands_id');
        if ($limit) {
            $totalQuery->limit($limit);
        }
        $totalPhotos = $totalQuery->count();

        $this->info("════════════════════════════════════════════");
        $this->info("Processing Brand-Object Relationships at Scale");
        $this->info("════════════════════════════════════════════");
        $this->info("Total photos with brands: " . number_format($totalPhotos));
        $this->info("Batch size: " . number_format($batchSize));
        $this->info("Threshold: {$threshold} occurrences");
        $this->info("Mode: " . ($dryRun ? "DRY RUN" : "LIVE"));
        $this->newLine();

        $startTime = microtime(true);

        // Process in batches using chunk
        $bar = $this->output->createProgressBar($totalPhotos);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% / %estimated:-6s% | Memory: %memory:6s%');
        $bar->start();

        DB::table('photos')
            ->whereNotNull('brands_id')
            ->when($limit, fn($q) => $q->limit($limit))
            ->orderBy('id')
            ->chunk($batchSize, function ($photos) use ($bar) {
                foreach ($photos as $photo) {
                    $this->processPhoto($photo);
                    $bar->advance();
                }

                // Clear memory periodically
                if ($this->photosProcessed % 5000 === 0) {
                    $this->consolidateAndClearMemory();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Final consolidation
        $this->consolidateAndClearMemory();

        // Process discovered relationships
        $this->processDiscoveredRelationships($threshold, $dryRun);

        // Report results
        $duration = round(microtime(true) - $startTime, 2);
        $this->displayResults($duration, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Process a single photo efficiently
     */
    private function processPhoto($photo): void
    {
        $this->photosProcessed++;

        // Get tags efficiently using direct queries
        $brands = $this->getBrands($photo);
        if (empty($brands)) {
            return;
        }

        $this->photosWithBrands++;

        $objects = $this->getObjects($photo);
        if (empty($objects)) {
            return;
        }

        // Record co-occurrences
        foreach ($brands as $brandKey => $brandQty) {
            foreach ($objects as $object) {
                if ($brandQty === $object['quantity']) {
                    $key = "{$object['category_key']}.{$object['object_key']}.{$brandKey}";

                    if (!isset($this->coOccurrences[$key])) {
                        $this->coOccurrences[$key] = [
                            'category_id' => $object['category_id'],
                            'category_key' => $object['category_key'],
                            'object_key' => $object['object_key'],
                            'brand_key' => $brandKey,
                            'count' => 0
                        ];
                    }

                    $this->coOccurrences[$key]['count']++;
                }
            }
        }
    }

    /**
     * Get brands from photo efficiently
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
     * Get objects from photo efficiently
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

            $excludeColumns = ['id', 'created_at', 'updated_at'];

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
     * Periodically consolidate and clear memory
     */
    private function consolidateAndClearMemory(): void
    {
        // Keep only significant co-occurrences
        $this->coOccurrences = array_filter($this->coOccurrences, fn($item) => $item['count'] >= 2);

        // Force garbage collection
        gc_collect_cycles();
    }

    /**
     * Process discovered relationships and create pivots
     */
    private function processDiscoveredRelationships(int $threshold, bool $dryRun): void
    {
        $this->info("\nProcessing discovered relationships...\n");

        // Filter by threshold and sort
        $significantRelationships = array_filter(
            $this->coOccurrences,
            fn($item) => $item['count'] >= $threshold
        );

        uasort($significantRelationships, fn($a, $b) => $b['count'] <=> $a['count']);

        $created = 0;

        foreach ($significantRelationships as $data) {
            if (!$dryRun && $this->createPivot($data)) {
                $created++;
            }
        }

        if ($dryRun) {
            $this->info("Would create " . count($significantRelationships) . " pivot relationships");
        } else {
            $this->info("Created {$created} pivot relationships");
        }
    }

    /**
     * Create a pivot relationship
     */
    private function createPivot(array $data): bool
    {
        try {
            // Get IDs
            $objectId = DB::table('litter_objects')
                ->where('key', $data['object_key'])
                ->value('id');

            $brandId = DB::table('brandslist')
                ->where('key', $data['brand_key'])
                ->value('id');

            if (!$objectId || !$brandId) {
                // Auto-create if missing
                if (!$objectId) {
                    $objectId = DB::table('litter_objects')->insertGetId([
                        'key' => $data['object_key'],
                        'crowdsourced' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                if (!$brandId) {
                    $brandId = DB::table('brandslist')->insertGetId([
                        'key' => $data['brand_key'],
                        'crowdsourced' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Get or create CategoryObject
            $categoryObject = DB::table('category_litter_object')
                ->where('category_id', $data['category_id'])
                ->where('litter_object_id', $objectId)
                ->first();

            if (!$categoryObject) {
                $categoryObjectId = DB::table('category_litter_object')->insertGetId([
                    'category_id' => $data['category_id'],
                    'litter_object_id' => $objectId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $categoryObjectId = $categoryObject->id;
            }

            // Create taggable if not exists
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
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Failed to create pivot", [
                'data' => $data,
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
                ['Unique relationships found', number_format(count($this->coOccurrences))],
                ['Processing time', round($duration, 2) . ' seconds'],
                ['Speed', round($this->photosProcessed / $duration, 0) . ' photos/sec'],
                ['Memory peak', round(memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MB'],
            ]
        );

        // Show top relationships
        $this->newLine();
        $this->info("Top Brand-Object Relationships:");

        $top = array_slice($this->coOccurrences, 0, 20, true);

        $this->table(
            ['Category', 'Object', 'Brand', 'Occurrences'],
            array_map(fn($item) => [
                $item['category_key'],
                $item['object_key'],
                $item['brand_key'],
                number_format($item['count'])
            ], $top)
        );

        // Save detailed log
        if (!$dryRun) {
            $logFile = storage_path('logs/brand_pivot_all_' . date('Y-m-d_His') . '.json');
            file_put_contents($logFile, json_encode($this->coOccurrences, JSON_PRETTY_PRINT));
            $this->info("\nDetailed log saved to: {$logFile}");
        }
    }
}

