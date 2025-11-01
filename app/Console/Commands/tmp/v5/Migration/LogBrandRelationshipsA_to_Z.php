<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Services\Tags\ClassifyTagsService;
use App\Tags\BrandsConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LogBrandRelationshipsA_to_Z extends Command
{
    protected $signature = 'olm:log-brand-relationships
        {--letter= : Filter brands by starting letter (A-Z)}
        {--analyze : Analyze undefined/unconfigured brands}
        {--export : Export detailed CSV for review}
        {--all : Export ALL brand relationships with 0/1 column for manual review}';

    protected $description = 'Log brand-object co-occurrences for analysis and BrandsConfig building';

    protected ClassifyTagsService $classifyService;

    protected int $totalPhotosToProcess = 0;

    // Category columns we care about (excluding brands_id)
    protected array $categoryColumns = [
        'smoking_id', 'food_id', 'coffee_id', 'alcohol_id',
        'softdrinks_id', 'sanitary_id', 'other_id', 'coastal_id',
        'dumping_id', 'industrial_id'
    ];

    // Track all co-occurrences
    protected array $coOccurrences = [];
    protected array $brandTotals = [];
    protected array $objectTotals = [];

    // Stats tracking
    protected array $stats = [
        'total_photos' => 0,
        'photos_with_brands' => 0,
        'photos_with_objects' => 0,
        'photos_processed' => 0,
        'brands_found' => [],
        'objects_found' => [],
    ];

    public function __construct(ClassifyTagsService $classifyService)
    {
        parent::__construct();
        $this->classifyService = $classifyService;
    }

    public function handle()
    {
        // Check if analyzing undefined brands
        if ($this->option('analyze')) {
            return $this->analyzeUndefinedBrands();
        }

        // Check if exporting all brands
        if ($this->option('all')) {
            return $this->exportAllBrands();
        }

        $letter = $this->option('letter');

        if (!$letter) {
            $this->error('Please specify a letter to analyze using --letter=X');
            $this->info('Example: php artisan olm:log-brand-relationships --letter=A --export');
            $this->info('Or use --all to export ALL brand relationships for manual review');
            $this->info('Or use --analyze to see unconfigured brands');
            return 1;
        }

        $letter = strtoupper($letter);

        if (!preg_match('/^[A-Z0-9]$/', $letter)) {
            $this->error('Letter must be a single character from A-Z or 0-9');
            return 1;
        }

        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║    BRAND-OBJECT RELATIONSHIP LOGGING                   ║');
        $this->info('║    Letter: ' . str_pad($letter, 44) . '║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Build query for photos with brands
        $query = $this->buildQuery();

        $this->totalPhotosToProcess = $query->count();
        $this->info("Total photos to analyze: " . number_format($this->totalPhotosToProcess));
        $this->info("Filtering for brands starting with: {$letter}");
        $this->newLine();

        if ($this->totalPhotosToProcess === 0) {
            $this->warn('No photos found with brands and objects.');
            return 0;
        }

        // Process photos in batches
        $progressBar = $this->output->createProgressBar($this->totalPhotosToProcess);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        $query->with([
            'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
            'other', 'customTags'
        ])
            ->chunkById(500, function ($photos) use ($progressBar, $letter) {
                foreach ($photos as $photo) {
                    $this->analyzePhotoCoOccurrences($photo, strtolower($letter));
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        // Display and export results
        $this->displayResults($letter);

        if ($this->option('export')) {
            $this->exportToCSV($letter);
        }

        return 0;
    }

    /**
     * Export ALL brand relationships with simple 0/1 column
     */
    protected function exportAllBrands()
    {
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║    EXPORTING ALL BRAND-OBJECT RELATIONSHIPS            ║');
        $this->info('║              (With 0/1 for manual review)              ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Build query for photos with brands
        $query = $this->buildQuery();

        $this->totalPhotosToProcess = $query->count();
        $this->info("Total photos to analyze: " . number_format($this->totalPhotosToProcess));
        $this->info("Processing ALL brands (A-Z, 0-9)...");
        $this->newLine();

        if ($this->totalPhotosToProcess === 0) {
            $this->warn('No photos found with brands and objects.');
            return 0;
        }

        // Reset tracking arrays
        $this->coOccurrences = [];
        $this->brandTotals = [];
        $this->stats = [
            'total_photos' => 0,
            'photos_with_brands' => 0,
            'photos_with_objects' => 0,
            'photos_processed' => 0,
            'brands_found' => [],
            'objects_found' => [],
        ];

        // Process photos in batches
        $progressBar = $this->output->createProgressBar($this->totalPhotosToProcess);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        $query->with([
            'smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
            'sanitary', 'coastal', 'dumping', 'industrial', 'brands',
            'other', 'customTags'
        ])
            ->chunkById(500, function ($photos) use ($progressBar) {
                foreach ($photos as $photo) {
                    // Process ALL brands - no letter filter
                    $this->analyzePhotoCoOccurrences($photo, null);
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        // Export to CSV
        $this->exportAllToCSV();

        return 0;
    }

    /**
     * Build the query for photos with brands
     */
    protected function buildQuery()
    {
        $query = Photo::whereNotNull('brands_id')
            ->where('brands_id', '>', 0);

        // Also need at least one category
        $query->where(function ($q) {
            foreach ($this->categoryColumns as $column) {
                $q->orWhere(function($q2) use ($column) {
                    $q2->whereNotNull($column)->where($column, '>', 0);
                });
            }
        });

        return $query;
    }

    /**
     * Export ALL relationships to CSV with simple 0/1 column
     */
    protected function exportAllToCSV(): void
    {
        $timestamp = date('Y-m-d-His');
        $filename = storage_path("app/ALL-brands-{$timestamp}.csv");

        $handle = fopen($filename, 'w');

        // Simple, clear header
        fputcsv($handle, [
            'Include',           // 0 or 1
            'Brand',
            'Letter',            // First letter for sorting
            'Category',
            'Object',
            'Photo Count',
            'Total Occurrences',
            'Brand Total',
            'Percentage',
            'Already in Config',
            'Example Photo IDs'
        ]);

        // Sort by brand alphabetically, then by percentage
        uasort($this->coOccurrences, function($a, $b) {
            $brandCompare = strcmp($a['brand'], $b['brand']);
            if ($brandCompare === 0) {
                $aPercent = $this->brandTotals[$a['brand']] > 0
                    ? ($a['total_occurrences'] / $this->brandTotals[$a['brand']] * 100)
                    : 0;
                $bPercent = $this->brandTotals[$b['brand']] > 0
                    ? ($b['total_occurrences'] / $this->brandTotals[$b['brand']] * 100)
                    : 0;
                return $bPercent <=> $aPercent;
            }
            return $brandCompare;
        });

        $totalExported = 0;
        $defaultYes = 0;
        $defaultNo = 0;

        foreach ($this->coOccurrences as $co) {
            $brandTotal = $this->brandTotals[$co['brand']] ?? 0;
            $percentage = $brandTotal > 0
                ? round(($co['total_occurrences'] / $brandTotal) * 100, 2)
                : 0;

            // Get first letter for sorting
            $firstLetter = strtoupper(substr($co['brand'], 0, 1));
            if (is_numeric($firstLetter)) {
                $firstLetter = '#';
            }

            // Simple rule: 1 if percentage >= 10% AND photos >= 3, else 0
            $include = ($percentage >= 10 && $co['photo_count'] >= 3) ? 1 : 0;

            if ($include === 1) $defaultYes++;
            else $defaultNo++;

            // Check if already configured
            $inConfig = 'No';
            if (BrandsConfig::brandExists($co['brand'])) {
                if (BrandsConfig::canBrandAttachToObject($co['brand'], $co['category'], $co['object'])) {
                    $inConfig = 'Yes-Allowed';
                } else {
                    $inConfig = 'Yes-NotAllowed';
                }
            }

            fputcsv($handle, [
                $include,
                $co['brand'],
                $firstLetter,
                $co['category'],
                $co['object'],
                $co['photo_count'],
                $co['total_occurrences'],
                $brandTotal,
                $percentage . '%',
                $inConfig,
                implode(',', array_slice($co['photo_ids'], 0, 5))
            ]);

            $totalExported++;
        }

        fclose($handle);

        // Display summary
        $uniqueBrands = count($this->brandTotals);

        $this->newLine();
        $this->info('════════════════════════════════════════════════════════');
        $this->info('                    EXPORT COMPLETE');
        $this->info('════════════════════════════════════════════════════════');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total photos analyzed', number_format($this->stats['total_photos'])],
                ['Unique brands found', number_format($uniqueBrands)],
                ['Total relationships', number_format($totalExported)],
                ['Default 1 (include)', number_format($defaultYes)],
                ['Default 0 (exclude)', number_format($defaultNo)],
            ]
        );

        $this->newLine();
        $this->info("📁 CSV file saved to:");
        $this->line("   {$filename}");
        $this->newLine();
        $this->info("📝 Instructions:");
        $this->line("   1. Open in Excel/Google Sheets");
        $this->line("   2. Sort by Letter, Brand, then Percentage");
        $this->line("   3. Review the Include column (0=exclude, 1=include)");
        $this->line("   4. Change 0 to 1 for valid relationships");
        $this->line("   5. Change 1 to 0 for invalid relationships");
    }

    /**
     * Analyze co-occurrences in a single photo
     */
    protected function analyzePhotoCoOccurrences(Photo $photo, ?string $letterFilter = null): void
    {
        $this->stats['total_photos']++;

        // Extract all tags from the photo
        $tags = $this->extractAllTags($photo);

        if (empty($tags['brands']) || empty($tags['objects'])) {
            return;
        }

        // Filter brands by letter if specified
        $brandsToAnalyze = $tags['brands'];
        if ($letterFilter !== null) {
            $brandsToAnalyze = array_filter($tags['brands'], function($brand) use ($letterFilter) {
                return stripos($brand['key'], $letterFilter) === 0;
            });
        }

        if (empty($brandsToAnalyze)) {
            return;
        }

        $this->stats['photos_with_brands']++;
        $this->stats['photos_with_objects']++;
        $this->stats['photos_processed']++;

        // Track each brand-object co-occurrence
        foreach ($brandsToAnalyze as $brand) {
            $brandKey = $brand['key'];

            // Track total brand occurrences
            if (!isset($this->brandTotals[$brandKey])) {
                $this->brandTotals[$brandKey] = 0;
            }
            $this->brandTotals[$brandKey] += $brand['quantity'];

            // Track unique brands found
            $this->stats['brands_found'][$brandKey] = true;

            foreach ($tags['objects'] as $object) {
                $objectKey = $object['key'];
                $categoryKey = $object['category_key'] ?? 'unknown';

                // Track unique objects found
                $objectCatKey = "{$categoryKey}/{$objectKey}";
                $this->stats['objects_found'][$objectCatKey] = true;

                // Create co-occurrence key
                $coKey = "{$brandKey}|{$categoryKey}|{$objectKey}";

                if (!isset($this->coOccurrences[$coKey])) {
                    $this->coOccurrences[$coKey] = [
                        'brand' => $brandKey,
                        'category' => $categoryKey,
                        'object' => $objectKey,
                        'photo_count' => 0,
                        'total_occurrences' => 0,
                        'photo_ids' => [],
                    ];
                }

                $this->coOccurrences[$coKey]['photo_count']++;
                $this->coOccurrences[$coKey]['total_occurrences'] += min($brand['quantity'], $object['quantity']);

                // Track first 10 photo IDs for examples
                if (count($this->coOccurrences[$coKey]['photo_ids']) < 10) {
                    $this->coOccurrences[$coKey]['photo_ids'][] = $photo->id;
                }
            }
        }
    }

    /**
     * Display analysis results (for letter-specific analysis)
     */
    protected function displayResults(string $letter): void
    {
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║                  ANALYSIS COMPLETE                     ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        $brandsFound = count($this->stats['brands_found']);
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total photos analyzed', number_format($this->stats['total_photos'])],
                ['Photos with Letter ' . $letter . ' brands', number_format($this->stats['photos_processed'])],
                ['Unique brands found (Letter ' . $letter . ')', number_format($brandsFound)],
                ['Unique objects found', number_format(count($this->stats['objects_found']))],
                ['Unique co-occurrences', number_format(count($this->coOccurrences))],
            ]
        );

        if ($brandsFound === 0) {
            $this->warn("No brands starting with '{$letter}' were found in the data.");
            return;
        }

        // Group by brand for display
        $byBrand = [];
        foreach ($this->coOccurrences as $co) {
            $brand = $co['brand'];
            if (!isset($byBrand[$brand])) {
                $byBrand[$brand] = [];
            }
            $byBrand[$brand][] = $co;
        }

        // Sort brands alphabetically
        ksort($byBrand);

        $this->newLine();
        $this->info('BRAND-OBJECT CO-OCCURRENCES FOR LETTER: ' . $letter);
        $this->newLine();

        foreach ($byBrand as $brandKey => $coOccurrences) {
            $brandTotal = $this->brandTotals[$brandKey] ?? 0;
            $isConfigured = BrandsConfig::brandExists($brandKey);

            $this->line('╔════════════════════════════════════════════════════════╗');
            $this->line('║ BRAND: ' . str_pad($brandKey, 47) . '║');
            $this->line('║ Total occurrences: ' . str_pad(number_format($brandTotal), 35) . '║');
            $this->line('║ Status: ' . str_pad($isConfigured ? '✅ Configured in BrandsConfig' : '❌ NOT in BrandsConfig', 46) . '║');
            $this->line('╠════════════════════════════════════════════════════════╣');

            // Sort by percentage descending
            usort($coOccurrences, function($a, $b) use ($brandTotal) {
                $aPercent = $brandTotal > 0 ? ($a['total_occurrences'] / $brandTotal * 100) : 0;
                $bPercent = $brandTotal > 0 ? ($b['total_occurrences'] / $brandTotal * 100) : 0;
                return $bPercent <=> $aPercent;
            });

            foreach ($coOccurrences as $co) {
                $percentage = $brandTotal > 0
                    ? round(($co['total_occurrences'] / $brandTotal) * 100, 1)
                    : 0;

                $indicator = '';
                if ($co['photo_count'] == 1) {
                    $indicator = '•';  // Single occurrence
                } elseif ($percentage >= 20) {
                    $indicator = '★';  // High percentage
                }

                $this->line(sprintf(
                    '║ %-12s %-20s %5d photos (%5.1f%%) %s║',
                    $co['category'],
                    $co['object'],
                    $co['photo_count'],
                    $percentage,
                    str_pad($indicator, 1)
                ));
            }

            $this->line('╚════════════════════════════════════════════════════════╝');
            $this->newLine();
        }
    }

    /**
     * Export analysis to CSV (for letter-specific export)
     */
    protected function exportToCSV(string $letter): void
    {
        $timestamp = date('Y-m-d-His');
        $filename = storage_path("app/brands-letter-{$letter}-{$timestamp}.csv");

        $handle = fopen($filename, 'w');

        // Header
        fputcsv($handle, [
            'Brand',
            'Category',
            'Object',
            'Photo Count',
            'Total Occurrences',
            'Brand Total',
            'Percentage',
            'Example Photo IDs'
        ]);

        // Sort by brand, then by percentage
        uasort($this->coOccurrences, function($a, $b) {
            $brandCompare = strcmp($a['brand'], $b['brand']);
            if ($brandCompare === 0) {
                return $b['photo_count'] <=> $a['photo_count'];
            }
            return $brandCompare;
        });

        foreach ($this->coOccurrences as $co) {
            $brandTotal = $this->brandTotals[$co['brand']] ?? 0;
            $percentage = $brandTotal > 0
                ? round(($co['total_occurrences'] / $brandTotal) * 100, 1)
                : 0;

            fputcsv($handle, [
                $co['brand'],
                $co['category'],
                $co['object'],
                $co['photo_count'],
                $co['total_occurrences'],
                $brandTotal,
                $percentage . '%',
                implode(',', array_slice($co['photo_ids'], 0, 5))
            ]);
        }

        fclose($handle);

        $this->newLine();
        $this->info("📁 Exported analysis for Letter {$letter} to:");
        $this->line("   {$filename}");
        $this->info("   Total relationships exported: " . count($this->coOccurrences));
    }

    /**
     * Extract all tags from a photo
     */
    protected function extractAllTags(Photo $photo): array
    {
        $objects = [];
        $brands = [];

        // Extract brands from brands table
        if ($photo->brands_id && $photo->brands) {
            $brandData = $photo->brands;
            foreach (Brand::types() as $brandColumn) {
                if (!empty($brandData->$brandColumn)) {
                    $quantity = (int) $brandData->$brandColumn;
                    if ($quantity > 0) {
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

        // Process each category
        foreach ($this->categoryColumns as $column) {
            if (!empty($photo->$column)) {
                $relationshipName = str_replace('_id', '', $column);
                $categoryData = $photo->$relationshipName;

                if (!$categoryData) {
                    continue;
                }

                $category = Category::where('key', $relationshipName)->first();
                if (!$category) {
                    continue;
                }

                if (!method_exists($categoryData, 'types')) {
                    continue;
                }

                // Extract objects from this category
                foreach ($categoryData->types() as $tagKey) {
                    if (!empty($categoryData->$tagKey)) {
                        $quantity = (int) $categoryData->$tagKey;
                        if ($quantity <= 0) {
                            continue;
                        }

                        $normalizedKey = $this->normalizeTagKey($tagKey);

                        // Check if this is actually a brand
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
                            $objectModel = LitterObject::firstOrCreate(
                                ['key' => $normalizedKey],
                                ['crowdsourced' => true]
                            );
                        }

                        $objects[] = [
                            'key' => $normalizedKey,
                            'original_key' => $tagKey,
                            'category_id' => $category->id,
                            'category_key' => $relationshipName,
                            'object_id' => $objectModel->id,
                            'quantity' => $quantity,
                        ];
                    }
                }
            }
        }

        // Process custom tags for brand patterns
        if ($photo->customTags) {
            foreach ($photo->customTags as $customTag) {
                if (preg_match('/^brand[=:](.+)$/i', $customTag->tag, $matches)) {
                    $brandKey = strtolower(trim($matches[1]));
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

    /**
     * Analyze brands that are not yet configured in BrandsConfig
     */
    protected function analyzeUndefinedBrands()
    {
        $this->info('Analyzing undefined brand-object relationships...');
        $this->newLine();

        $allBrands = BrandList::pluck('key', 'id')->toArray();
        $this->info("Total brands in system: " . count($allBrands));

        $configuredBrands = BrandsConfig::getAllBrands();
        $this->info("Brands configured in BrandsConfig: " . count($configuredBrands));

        $unconfiguredBrands = array_diff($allBrands, $configuredBrands);
        $this->info("Brands NOT in BrandsConfig: " . count($unconfiguredBrands));
        $this->newLine();

        // Group by first character
        $byFirstChar = [];
        foreach ($unconfiguredBrands as $brand) {
            $firstChar = strtoupper(substr($brand, 0, 1));
            if (is_numeric($firstChar)) {
                $firstChar = '#';
            }
            if (!isset($byFirstChar[$firstChar])) {
                $byFirstChar[$firstChar] = [];
            }
            $byFirstChar[$firstChar][] = $brand;
        }

        // Sort with numbers first, then letters
        uksort($byFirstChar, function($a, $b) {
            if ($a === '#' && $b !== '#') return -1;
            if ($a !== '#' && $b === '#') return 1;
            return strcmp($a, $b);
        });

        $this->info("Unconfigured brands by letter:");
        foreach ($byFirstChar as $char => $brands) {
            $displayChar = $char === '#' ? 'Numbers' : $char;
            $this->line("  {$displayChar}: " . count($brands) . " brands");

            if (count($brands) <= 10) {
                sort($brands);
                foreach ($brands as $brand) {
                    $this->line("     - {$brand}");
                }
            }
        }

        // Export list
        $filename = storage_path('app/unconfigured-brands-' . date('Y-m-d-His') . '.csv');
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['Letter', 'Brand']);

        foreach ($byFirstChar as $char => $brands) {
            sort($brands);
            foreach ($brands as $brand) {
                fputcsv($handle, [$char, $brand]);
            }
        }

        fclose($handle);
        $this->newLine();
        $this->info("📁 Exported unconfigured brands to:");
        $this->line("   {$filename}");

        return 0;
    }
}
