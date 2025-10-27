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

class DefineBrandRelationships extends Command
{
    protected $signature = 'olm:define-brand-relationships {--analyze : Analyze undefined relationships instead of creating new ones}';

    protected $description = 'Discover and define brand-object relationships from photos with 1 object and 1 brand';

    protected ClassifyTagsService $classifyService;

    protected int $totalPhotosToProcess = 0;

    // Category columns we care about (excluding brands_id)
    protected array $categoryColumns = [
        'smoking_id', 'food_id', 'coffee_id', 'alcohol_id',
        'softdrinks_id', 'sanitary_id', 'other_id', 'coastal_id',
        'dumping_id', 'industrial_id'
    ];

    // Stats tracking
    protected array $stats = [
        'total_photos' => 0,
        'photos_with_1_object_1_brand' => 0,
        'relationships_created' => 0,
        'relationships_already_existed' => 0,
        'brand_object_pairs' => [],
        'skipped_multiple_objects' => 0,
        'skipped_multiple_brands' => 0,
        'skipped_no_brands' => 0,
        'skipped_no_objects' => 0,
        'by_category' => [],
    ];

    // Track relationships for export
    protected array $relationshipsToExport = [];

    public function __construct(ClassifyTagsService $classifyService)
    {
        parent::__construct();
        $this->classifyService = $classifyService;
    }

    public function handle()
    {
        if ($this->option('analyze')) {
            return $this->analyzeUndefinedRelationships();
        }

        $this->info('Starting brand-object relationship discovery...');

        // Build the query to find photos with brands_id and exactly one other category
        $query = $this->buildOptimizedQuery();

        // Get total count
        $this->totalPhotosToProcess = $query->count();
        $this->info("Total photos to process: " . number_format($this->totalPhotosToProcess));
        $this->newLine();

        if ($this->totalPhotosToProcess === 0) {
            $this->warn('No photos found with brands and exactly one category.');
            return;
        }

        // Process photos in batches
        $query->with([
            'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
            'other', 'customTags'
        ])
            ->chunkById(1000, function ($photos) {
                foreach ($photos as $photo) {
                    $this->processPhoto($photo);
                }

                // Calculate and show percentage
                $percentage = $this->totalPhotosToProcess > 0
                    ? round(($this->stats['total_photos'] / $this->totalPhotosToProcess) * 100, 2)
                    : 0;

                $this->info(sprintf(
                    "Progress: %s / %s (%s%%) - Found %s 1-to-1 relationships",
                    number_format($this->stats['total_photos']),
                    number_format($this->totalPhotosToProcess),
                    $percentage,
                    number_format($this->stats['photos_with_1_object_1_brand'])
                ));
            });

        $this->exportRelationships();
        $this->displayFinalStats();
    }

    protected function analyzeUndefinedRelationships()
    {
        $this->info('Analyzing undefined brand-object relationships...');
        $this->newLine();

        // Get all brands
        $allBrands = BrandList::pluck('key', 'id')->toArray();
        $this->info("Total brands in system: " . count($allBrands));

        // Get all brand IDs that have relationships defined
        $brandsWithRelationships = DB::table('taggables')
            ->where('taggable_type', BrandList::class)
            ->distinct()
            ->pluck('taggable_id')
            ->toArray();

        $this->info("Brands WITH defined relationships: " . count($brandsWithRelationships));

        // Find brands without any relationships
        $brandsWithoutRelationships = [];
        foreach ($allBrands as $id => $key) {
            if (!in_array($id, $brandsWithRelationships)) {
                $brandsWithoutRelationships[$id] = $key;
            }
        }

        $this->info("Brands WITHOUT any relationships: " . count($brandsWithoutRelationships));
        $this->newLine();

        // Analyze usage of undefined brands
        if (!empty($brandsWithoutRelationships)) {
            $this->info("Checking usage of undefined brands in photos...");

            $undefinedBrandUsage = [];

            // Get official brand columns from Brand::types()
            $officialBrandColumns = Brand::types();

            // Check usage in old brands table (only for official brand columns)
            foreach ($brandsWithoutRelationships as $brandId => $brandKey) {
                $count = 0;

                // Check if this brand is an official column in brands table
                if (in_array($brandKey, $officialBrandColumns)) {
                    $count = DB::table('brands')
                        ->where($brandKey, '>', 0)
                        ->count();
                }

                // Also check custom_tags for both official and custom brands
                $customCount = DB::table('custom_tags')
                    ->where('tag', 'brand:' . $brandKey)
                    ->orWhere('tag', 'brand=' . $brandKey)
                    ->count();

                $totalCount = $count + $customCount;

                if ($totalCount > 0) {
                    $undefinedBrandUsage[$brandKey] = $totalCount;
                }
            }

            // Sort by usage
            arsort($undefinedBrandUsage);

            // Show top 50 undefined brands
            $topUndefined = array_slice($undefinedBrandUsage, 0, 50, true);

            $this->table(
                ['Brand', 'Photos Using It'],
                array_map(function($key, $count) {
                    return [$key, number_format($count)];
                }, array_keys($topUndefined), $topUndefined)
            );

            $this->newLine();
            $this->info("Total undefined brands with usage: " . count($undefinedBrandUsage));
            $this->info("Total photos affected: " . number_format(array_sum($undefinedBrandUsage)));
        }

        // Export undefined brands list
        $filename = storage_path('app/undefined-brands-' . date('Y-m-d-His') . '.csv');
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['Brand ID', 'Brand Key', 'Usage Count']);

        foreach ($brandsWithoutRelationships as $id => $key) {
            $usage = $undefinedBrandUsage[$key] ?? 0;
            fputcsv($handle, [$id, $key, $usage]);
        }

        fclose($handle);
        $this->info("Exported undefined brands to: {$filename}");

        return 0;
    }

    protected function buildOptimizedQuery()
    {
        // Start with photos that have brands
        $query = Photo::whereNotNull('brands_id')
            ->where('brands_id', '>', 0);

        // Add condition for exactly one other category
        $query->where(function ($q) {
            $categoryCountExpression = [];
            foreach ($this->categoryColumns as $column) {
                $categoryCountExpression[] = "CASE WHEN {$column} IS NOT NULL AND {$column} > 0 THEN 1 ELSE 0 END";
            }

            $countSql = '(' . implode(' + ', $categoryCountExpression) . ') = 1';
            $q->whereRaw($countSql);
        });

        return $query;
    }

    protected function processPhoto(Photo $photo): void
    {
        $this->stats['total_photos']++;

        // Extract all tags from the photo
        $tags = $this->extractAllTags($photo);

        // Count objects and brands
        $objectCount = count($tags['objects']);
        $brandCount = count($tags['brands']);

        // Track skipping reasons
        if ($objectCount === 0) {
            $this->stats['skipped_no_objects']++;
            return;
        }

        if ($brandCount === 0) {
            $this->stats['skipped_no_brands']++;
            return;
        }

        if ($objectCount > 1) {
            $this->stats['skipped_multiple_objects']++;
            $this->info("Photo {$photo->id}: Multiple objects ({$objectCount}), skipping");
            return;
        }

        if ($brandCount > 1) {
            $this->stats['skipped_multiple_brands']++;
            $this->info("Photo {$photo->id}: Multiple brands ({$brandCount}), skipping");
            return;
        }

        // We have exactly 1 object and 1 brand!
        $this->stats['photos_with_1_object_1_brand']++;

        $object = $tags['objects'][0];
        $brand = $tags['brands'][0];

        // Track by category
        $categoryKey = $tags['category_key'] ?? 'unknown';
        if (!isset($this->stats['by_category'][$categoryKey])) {
            $this->stats['by_category'][$categoryKey] = 0;
        }
        $this->stats['by_category'][$categoryKey]++;

        $this->info("Photo {$photo->id}: Found 1-to-1 relationship: {$object['key']} ↔ {$brand['key']} ({$categoryKey})");

        // Track for export
        $this->relationshipsToExport[] = [
            'photo_id' => $photo->id,
            'category' => $categoryKey,
            'object' => $object['key'],
            'brand' => $brand['key'],
            'object_quantity' => $object['quantity'],
            'brand_quantity' => $brand['quantity'],
        ];

        // Create the relationship
        $this->createBrandObjectRelationship(
            $object['category_id'],
            $object['object_id'],
            $brand['brand_id'],
            $object['key'],
            $brand['key']
        );
    }

    protected function extractAllTags(Photo $photo): array
    {
        $objects = [];
        $brands = [];
        $categoryKey = null;

        // Extract brands from brands table
        if ($photo->brands_id && $photo->brands) {
            $brandData = $photo->brands;
            foreach (Brand::types() as $brandColumn) {
                if (!empty($brandData->$brandColumn)) {
                    $quantity = (int) $brandData->$brandColumn;
                    if ($quantity > 0) {
                        // Get brand from brandslist
                        $brandModel = BrandList::where('key', $brandColumn)->first();
                        if ($brandModel) {
                            $brands[] = [
                                'key' => $brandColumn,
                                'brand_id' => $brandModel->id,
                                'quantity' => $quantity,
                            ];
                        }
                    }
                }
            }
        }

        // Find which single category this photo has
        $activeCategory = null;
        $activeCategoryKey = null;

        foreach ($this->categoryColumns as $column) {
            if (!empty($photo->$column)) {
                $relationshipName = str_replace('_id', '', $column);
                $activeCategoryKey = $relationshipName;
                $activeCategory = $photo->$relationshipName;
                $categoryKey = $relationshipName;
                break;
            }
        }

        if (!$activeCategory || !$activeCategoryKey) {
            return ['objects' => [], 'brands' => $brands, 'category_key' => null];
        }

        // Get category model
        $category = Category::where('key', $activeCategoryKey)->first();
        if (!$category) {
            return ['objects' => [], 'brands' => $brands, 'category_key' => $activeCategoryKey];
        }

        // Get the types() method for this category
        if (!method_exists($activeCategory, 'types')) {
            return ['objects' => [], 'brands' => $brands, 'category_key' => $activeCategoryKey];
        }

        // Extract objects from this single category
        foreach ($activeCategory->types() as $tagKey) {
            if (!empty($activeCategory->$tagKey)) {
                $quantity = (int) $activeCategory->$tagKey;
                if ($quantity <= 0) {
                    continue;
                }

                // Normalize deprecated tags
                $normalizedKey = $this->normalizeTagKey($tagKey);

                // Check if this is actually a brand that was misplaced
                $brandModel = BrandList::where('key', $normalizedKey)->first();
                if ($brandModel) {
                    $brands[] = [
                        'key' => $normalizedKey,
                        'brand_id' => $brandModel->id,
                        'quantity' => $quantity,
                    ];
                    continue;
                }

                // It's an object
                $objectModel = LitterObject::where('key', $normalizedKey)->first();
                if (!$objectModel) {
                    // Create it if it doesn't exist
                    $objectModel = LitterObject::firstOrCreate(
                        ['key' => $normalizedKey],
                        ['crowdsourced' => true]
                    );
                }

                $objects[] = [
                    'key' => $normalizedKey,
                    'original_key' => $tagKey,
                    'category_id' => $category->id,
                    'object_id' => $objectModel->id,
                    'quantity' => $quantity,
                ];
            }
        }

        // Process custom tags for brand= patterns
        if ($photo->customTags) {
            foreach ($photo->customTags as $customTag) {
                if (preg_match('/^brand[=:](.+)$/i', $customTag->tag, $matches)) {
                    $brandKey = strtolower(trim($matches[1]));

                    // Find in brandslist
                    $brandModel = BrandList::where('key', $brandKey)->first();
                    if ($brandModel) {
                        $brands[] = [
                            'key' => $brandKey,
                            'brand_id' => $brandModel->id,
                            'quantity' => 1,
                        ];
                    }
                }
            }
        }

        return [
            'objects' => $objects,
            'brands' => $brands,
            'category_key' => $categoryKey,
        ];
    }

    protected function normalizeTagKey(string $tagKey): string
    {
        $mapping = ClassifyTagsService::normalizeDeprecatedTag($tagKey);

        if ($mapping !== null && isset($mapping['object'])) {
            return $mapping['object'];
        }

        return $tagKey;
    }

    protected function createBrandObjectRelationship(
        int $categoryId,
        int $objectId,
        int $brandId,
        string $objectKey,
        string $brandKey
    ): void {
        // Track this pair
        $pairKey = "{$brandKey}:{$objectKey}";
        if (!isset($this->stats['brand_object_pairs'][$pairKey])) {
            $this->stats['brand_object_pairs'][$pairKey] = 0;
        }
        $this->stats['brand_object_pairs'][$pairKey]++;

        // Get or create CategoryObject pivot
        $categoryObject = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $objectId,
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
    }

    protected function exportRelationships(): void
    {
        if (empty($this->relationshipsToExport)) {
            $this->info('No relationships to export.');
            return;
        }

        $filename = storage_path('app/brand-object-relationships-' . date('Y-m-d-His') . '.csv');
        $handle = fopen($filename, 'w');

        fputcsv($handle, ['Photo ID', 'Category', 'Object', 'Brand', 'Object Qty', 'Brand Qty']);

        foreach ($this->relationshipsToExport as $relationship) {
            fputcsv($handle, $relationship);
        }

        fclose($handle);
        $this->info("Exported " . count($this->relationshipsToExport) . " relationships to: {$filename}");
    }

    protected function displayFinalStats(): void
    {
        $this->newLine();
        $this->info('=' . str_repeat('=', 78));
        $this->info('BRAND-OBJECT RELATIONSHIP DISCOVERY COMPLETE');
        $this->info('=' . str_repeat('=', 78));

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total photos processed', number_format($this->stats['total_photos'])],
                ['Photos with exactly 1 object & 1 brand', number_format($this->stats['photos_with_1_object_1_brand'])],
                ['New relationships created', number_format($this->stats['relationships_created'])],
                ['Relationships already existed', number_format($this->stats['relationships_already_existed'])],
                ['', ''],
                ['--- Skipped Reasons ---', ''],
                ['No objects found', number_format($this->stats['skipped_no_objects'])],
                ['No brands found', number_format($this->stats['skipped_no_brands'])],
                ['Multiple objects', number_format($this->stats['skipped_multiple_objects'])],
                ['Multiple brands', number_format($this->stats['skipped_multiple_brands'])],
            ]
        );

        // Show breakdown by category
        if (!empty($this->stats['by_category'])) {
            $this->newLine();
            $this->info('Relationships by Category:');

            $categoryData = [];
            arsort($this->stats['by_category']);
            foreach ($this->stats['by_category'] as $category => $count) {
                $categoryData[] = [$category, number_format($count)];
            }

            $this->table(['Category', 'Count'], $categoryData);
        }

        if (!empty($this->stats['brand_object_pairs'])) {
            $this->newLine();
            $this->info('Top Brand-Object Pairs Discovered:');

            arsort($this->stats['brand_object_pairs']);
            $topPairs = array_slice($this->stats['brand_object_pairs'], 0, 20, true);

            $tableData = [];
            foreach ($topPairs as $pair => $count) {
                [$brand, $object] = explode(':', $pair);
                $tableData[] = [$brand, $object, number_format($count)];
            }

            $this->table(['Brand', 'Object', 'Occurrences'], $tableData);

            $this->info('Total unique pairs: ' . count($this->stats['brand_object_pairs']));
        }

        if ($this->stats['total_photos'] > 0) {
            $percentage = round(
                ($this->stats['photos_with_1_object_1_brand'] / $this->stats['total_photos']) * 100,
                2
            );
            $this->newLine();
            $this->info("Success rate: {$percentage}% of eligible photos had exactly 1 object & 1 brand");
        }
    }
}
