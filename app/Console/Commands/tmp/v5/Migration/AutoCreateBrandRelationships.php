<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Taggable;
use App\Models\Photo;
use App\Services\Tags\ClassifyTagsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCreateBrandRelationships extends Command
{
    protected $signature = 'olm:auto-create-brand-relationships
        {--min-usage=50 : Minimum usage count to process brand}
        {--min-percentage=10 : Minimum percentage for multiple object relationships}
        {--export : Export proposed relationships to CSV}
        {--apply : Actually create the relationships (without this, runs in dry-run mode)}';

    protected $description = 'Automatically create brand-object relationships based on analysis (dry-run by default)';

    protected ClassifyTagsService $classifyService;

    protected array $categoryColumns = [
        'smoking_id', 'food_id', 'coffee_id', 'alcohol_id',
        'softdrinks_id', 'sanitary_id', 'other_id', 'coastal_id',
        'dumping_id', 'industrial_id'
    ];

    protected array $stats = [
        'brands_processed' => 0,
        'relationships_created' => 0,
        'relationships_already_existed' => 0,
        'brands_skipped_low_usage' => 0,
        'brands_with_single_object' => 0,
        'brands_with_multiple_objects' => 0,
        'by_category' => [],
    ];

    public function __construct(ClassifyTagsService $classifyService)
    {
        parent::__construct();
        $this->classifyService = $classifyService;
    }

    public function handle()
    {
        $minUsage = (int) $this->option('min-usage');
        $minPercentage = (float) $this->option('min-percentage');
        $apply = $this->option('apply');
        $export = $this->option('export');

        if (!$apply) {
            $this->info('DRY RUN MODE - No relationships will be created.');
            $this->info('Use --apply to actually create relationships.');
            $this->info('Use --export to save proposed relationships to CSV.');
            $this->newLine();
        }

        $this->info("Processing undefined brands with usage >= {$minUsage}");
        $this->info("Creating relationships for objects that appear in >= {$minPercentage}% of photos");
        $this->newLine();

        // Get brands without relationships
        $brandsWithRelationships = DB::table('taggables')
            ->where('taggable_type', BrandList::class)
            ->distinct()
            ->pluck('taggable_id')
            ->toArray();

        $undefinedBrands = BrandList::whereNotIn('id', $brandsWithRelationships)
            ->pluck('key', 'id')
            ->toArray();

        $this->info("Found " . count($undefinedBrands) . " brands without relationships");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($undefinedBrands));
        $progressBar->start();

        $relationshipsToCreate = [];

        foreach ($undefinedBrands as $brandId => $brandKey) {
            // Count usage
            $usageCount = $this->getBrandUsageCount($brandKey);

            if ($usageCount < $minUsage) {
                $this->stats['brands_skipped_low_usage']++;
                $progressBar->advance();
                continue;
            }

            // Analyze what objects this brand appears with
            $context = $this->analyzeBrandContext($brandKey);

            if (empty($context['objects'])) {
                $progressBar->advance();
                continue;
            }

            $totalPhotos = $context['total_photos'];
            $isSingleObject = ($context['single_object_photos'] == $totalPhotos);

            if ($isSingleObject) {
                $this->stats['brands_with_single_object']++;
            } else {
                $this->stats['brands_with_multiple_objects']++;
            }

            // Determine which relationships to create
            foreach ($context['objects_by_category'] as $categoryKey => $objects) {
                foreach ($objects as $objectKey => $count) {
                    $percentage = ($count / $totalPhotos) * 100;

                    // Create relationship if:
                    // 1. It's the only object (single object scenario)
                    // 2. The object appears in >= min percentage of photos
                    if ($isSingleObject || $percentage >= $minPercentage) {
                        $relationshipsToCreate[] = [
                            'brand_id' => $brandId,
                            'brand_key' => $brandKey,
                            'category_key' => $categoryKey,
                            'object_key' => $objectKey,
                            'count' => $count,
                            'percentage' => round($percentage, 1),
                            'reason' => $isSingleObject ? 'single_object' : 'above_threshold',
                            'total_photos' => $totalPhotos
                        ];
                    }
                }
            }

            $this->stats['brands_processed']++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show summary
        $this->info("=" . str_repeat("=", 78));
        $this->info("RELATIONSHIP DISCOVERY COMPLETE");
        $this->info("=" . str_repeat("=", 78));
        $this->info("Relationships to create: " . count($relationshipsToCreate));
        $this->newLine();

        if (count($relationshipsToCreate) > 0) {
            // Group by brand for display
            $byBrand = [];
            foreach ($relationshipsToCreate as $rel) {
                $byBrand[$rel['brand_key']][] = $rel;
            }

            // Show statistics
            $singleObjectRels = array_filter($relationshipsToCreate, fn($r) => $r['reason'] === 'single_object');
            $thresholdRels = array_filter($relationshipsToCreate, fn($r) => $r['reason'] === 'above_threshold');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Unique brands', number_format(count($byBrand))],
                    ['Total relationships', number_format(count($relationshipsToCreate))],
                    ['Single-object relationships', number_format(count($singleObjectRels))],
                    ['Threshold-based relationships', number_format(count($thresholdRels))],
                    ['Brands skipped (low usage)', number_format($this->stats['brands_skipped_low_usage'])],
                ]
            );

            $this->newLine();

            // Show sample of what will be created
            $this->info("Sample relationships (first 10 brands):");
            $sample = array_slice($byBrand, 0, 10, true);

            foreach ($sample as $brandKey => $relationships) {
                $totalPhotos = $relationships[0]['total_photos'];
                $this->info("  {$brandKey} (appears in {$totalPhotos} photos):");
                foreach ($relationships as $rel) {
                    $this->line(sprintf(
                        "    → %s:%s (%d photos, %.1f%%) [%s]",
                        $rel['category_key'],
                        $rel['object_key'],
                        $rel['count'],
                        $rel['percentage'],
                        $rel['reason']
                    ));
                }
            }

            if (count($byBrand) > 10) {
                $this->info("  ... and " . (count($byBrand) - 10) . " more brands");
            }
        }

        // Export if requested
        if ($export) {
            $this->exportProposedRelationships($relationshipsToCreate);
        }

        if (!$apply) {
            $this->newLine();
            $this->warn("This was a DRY RUN - no relationships were created.");
            $this->info("To create these relationships, run with --apply flag");
            $this->info("To export these relationships to CSV, run with --export flag");
            return 0;
        }

        // Apply the relationships
        $this->newLine();
        $this->info("Creating relationships...");

        $progressBar = $this->output->createProgressBar(count($relationshipsToCreate));
        $progressBar->start();

        foreach ($relationshipsToCreate as $rel) {
            $this->createRelationship(
                $rel['brand_id'],
                $rel['brand_key'],
                $rel['category_key'],
                $rel['object_key']
            );

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayStats();

        // Export created relationships for review
        $this->exportCreatedRelationships($relationshipsToCreate);

        return 0;
    }

    protected function getBrandUsageCount(string $brandKey): int
    {
        $count = 0;

        // Check if it's an official brand column
        if (in_array($brandKey, Brand::types())) {
            $count += DB::table('brands')
                ->where($brandKey, '>', 0)
                ->count();
        }

        // Check custom tags
        $count += DB::table('custom_tags')
            ->where('tag', 'brand:' . $brandKey)
            ->orWhere('tag', 'brand=' . $brandKey)
            ->count();

        return $count;
    }

    protected function analyzeBrandContext(string $brandKey): array
    {
        $context = [
            'total_photos' => 0,
            'single_object_photos' => 0,
            'multiple_object_photos' => 0,
            'objects' => [],
            'objects_by_category' => [],
        ];

        // Find photos with this brand
        $photoIds = [];

        // From brands table
        if (in_array($brandKey, Brand::types())) {
            $ids = DB::table('brands')
                ->join('photos', 'photos.brands_id', '=', 'brands.id')
                ->where("brands.{$brandKey}", '>', 0)
                ->pluck('photos.id')
                ->toArray();
            $photoIds = array_merge($photoIds, $ids);
        }

        // From custom tags
        $customIds = DB::table('custom_tags')
            ->where(function($q) use ($brandKey) {
                $q->where('tag', 'brand:' . $brandKey)
                    ->orWhere('tag', 'brand=' . $brandKey);
            })
            ->pluck('photo_id')
            ->toArray();
        $photoIds = array_merge($photoIds, $customIds);

        $photoIds = array_unique($photoIds);
        $context['total_photos'] = count($photoIds);

        if (empty($photoIds)) {
            return $context;
        }

        // Analyze each photo
        Photo::whereIn('id', $photoIds)
            ->with(['smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
                'sanitary', 'coastal', 'dumping', 'industrial', 'other'])
            ->chunk(500, function($photos) use (&$context) {
                foreach ($photos as $photo) {
                    $photoObjects = $this->extractPhotoObjects($photo);

                    if (count($photoObjects) === 1) {
                        $context['single_object_photos']++;
                    } elseif (count($photoObjects) > 1) {
                        $context['multiple_object_photos']++;
                    }

                    foreach ($photoObjects as $obj) {
                        // Track overall
                        if (!isset($context['objects'][$obj['object']])) {
                            $context['objects'][$obj['object']] = 0;
                        }
                        $context['objects'][$obj['object']]++;

                        // Track by category
                        if (!isset($context['objects_by_category'][$obj['category']])) {
                            $context['objects_by_category'][$obj['category']] = [];
                        }
                        if (!isset($context['objects_by_category'][$obj['category']][$obj['object']])) {
                            $context['objects_by_category'][$obj['category']][$obj['object']] = 0;
                        }
                        $context['objects_by_category'][$obj['category']][$obj['object']]++;
                    }
                }
            });

        return $context;
    }

    protected function extractPhotoObjects(Photo $photo): array
    {
        $objects = [];

        $categories = ['smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'other'];

        foreach ($categories as $categoryKey) {
            $idField = "{$categoryKey}_id";

            if (empty($photo->$idField)) {
                continue;
            }

            $categoryData = $photo->$categoryKey;
            if (!$categoryData) {
                continue;
            }

            if (!method_exists($categoryData, 'types')) {
                continue;
            }

            foreach ($categoryData->types() as $tagKey) {
                if (!empty($categoryData->$tagKey) && $categoryData->$tagKey > 0) {
                    // Normalize the tag key
                    $normalizedKey = $this->normalizeObjectKey($tagKey);

                    // Skip if this is actually a brand
                    if (BrandList::where('key', $normalizedKey)->exists()) {
                        continue;
                    }

                    $objects[] = [
                        'category' => $categoryKey,
                        'object' => $normalizedKey,
                        'quantity' => (int) $categoryData->$tagKey
                    ];
                }
            }
        }

        return $objects;
    }

    protected function normalizeObjectKey(string $tagKey): string
    {
        // Use ClassifyTagsService to normalize deprecated tags
        $mapping = ClassifyTagsService::normalizeDeprecatedTag($tagKey);

        if ($mapping !== null && isset($mapping['object'])) {
            return $mapping['object'];
        }

        return $tagKey;
    }

    protected function createRelationship(
        int $brandId,
        string $brandKey,
        string $categoryKey,
        string $objectKey
    ): void {
        // Get category
        $category = Category::where('key', $categoryKey)->first();
        if (!$category) {
            $this->error("Category not found: {$categoryKey}");
            return;
        }

        // Normalize object key
        $normalizedObjectKey = $this->normalizeObjectKey($objectKey);

        // Get or create object
        $object = LitterObject::firstOrCreate(['key' => $normalizedObjectKey]);

        // Get or create CategoryObject pivot
        $categoryObject = CategoryObject::firstOrCreate([
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
        ]);

        // Check if relationship already exists
        $exists = Taggable::where('category_litter_object_id', $categoryObject->id)
            ->where('taggable_type', BrandList::class)
            ->where('taggable_id', $brandId)
            ->exists();

        if ($exists) {
            $this->stats['relationships_already_existed']++;
            return;
        }

        // Create the relationship
        Taggable::create([
            'category_litter_object_id' => $categoryObject->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $brandId,
            'quantity' => 1,
        ]);

        $this->stats['relationships_created']++;

        // Track by category
        if (!isset($this->stats['by_category'][$categoryKey])) {
            $this->stats['by_category'][$categoryKey] = 0;
        }
        $this->stats['by_category'][$categoryKey]++;
    }

    protected function exportProposedRelationships(array $relationships): void
    {
        if (empty($relationships)) {
            $this->warn('No relationships to export.');
            return;
        }

        $filename = storage_path('app/brand-relationships-proposed-' . date('Y-m-d-His') . '.csv');
        $handle = fopen($filename, 'w');

        // Headers
        fputcsv($handle, [
            'Brand ID',
            'Brand Key',
            'Category',
            'Object',
            'Photos with this combo',
            'Total photos with brand',
            'Percentage',
            'Reason',
            'Will Create?'
        ]);

        // Data
        foreach ($relationships as $rel) {
            fputcsv($handle, [
                $rel['brand_id'],
                $rel['brand_key'],
                $rel['category_key'],
                $rel['object_key'],
                $rel['count'],
                $rel['total_photos'],
                $rel['percentage'] . '%',
                $rel['reason'],
                'YES'
            ]);
        }

        fclose($handle);
        $this->newLine();
        $this->info("✓ Exported proposed relationships to: {$filename}");
        $this->info("Review this file to see what relationships will be created");
    }

    protected function exportCreatedRelationships(array $relationships): void
    {
        if (empty($relationships)) {
            return;
        }

        $filename = storage_path('app/brand-relationships-created-' . date('Y-m-d-His') . '.csv');
        $handle = fopen($filename, 'w');

        // Headers
        fputcsv($handle, [
            'Brand ID',
            'Brand Key',
            'Category',
            'Object',
            'Photos with this combo',
            'Total photos with brand',
            'Percentage',
            'Reason',
            'Status'
        ]);

        // Data
        foreach ($relationships as $rel) {
            fputcsv($handle, [
                $rel['brand_id'],
                $rel['brand_key'],
                $rel['category_key'],
                $rel['object_key'],
                $rel['count'],
                $rel['total_photos'],
                $rel['percentage'] . '%',
                $rel['reason'],
                'CREATED'
            ]);
        }

        fclose($handle);
        $this->info("✓ Exported created relationships to: {$filename}");
    }

    protected function displayStats(): void
    {
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Brands analyzed', number_format($this->stats['brands_processed'])],
                ['Brands skipped (low usage)', number_format($this->stats['brands_skipped_low_usage'])],
                ['Brands with single object', number_format($this->stats['brands_with_single_object'])],
                ['Brands with multiple objects', number_format($this->stats['brands_with_multiple_objects'])],
                ['', ''],
                ['Relationships created', number_format($this->stats['relationships_created'])],
                ['Relationships already existed', number_format($this->stats['relationships_already_existed'])],
            ]
        );

        if (!empty($this->stats['by_category']) && $this->stats['relationships_created'] > 0) {
            $this->newLine();
            $this->info('Relationships created by category:');
            $this->table(
                ['Category', 'Count'],
                array_map(fn($cat, $count) => [$cat, number_format($count)],
                    array_keys($this->stats['by_category']),
                    array_values($this->stats['by_category']))
            );
        }
    }
}
