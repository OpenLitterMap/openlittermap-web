<?php

namespace App\Console\Commands\tmp\v5\Migration\Brands;

use App\Models\Litter\Tags\BrandList;
use App\Models\Photo;
use Illuminate\Console\Command;

class LogBrandRelationships extends Command
{
    protected $signature = 'olm:extract-brands
        {--limit=0 : Limit number of photos to process (0 = all)}
        {--min-count=1 : Minimum co-occurrence count to export}';

    protected $description = 'Extract comprehensive brand-object relationships from all photos';

    // Track all brand-object relationships
    protected array $brandObjects = [];
    protected array $brandPhotoCounts = [];
    protected array $objectCategories = [];
    protected array $uniqueCategories = [];
    protected int $photosProcessed = 0;

    // Global object tracking for comprehensive context
    protected array $allPossibleObjects = [];  // All unique category.object combinations
    protected array $objectGlobalCounts = [];  // How many photos contain each object globally

    // Track brand sources
    protected array $brandsFromOfficial = [];  // Brands from brands_id
    protected array $brandsFromCustom = [];    // Brands from custom tags

    // Track exact brand keys for debugging
    protected array $brandKeyVariations = [];  // Track different case variations

    public function handle()
    {
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║     EXTRACTING BRAND-OBJECT RELATIONSHIPS              ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Get photos with EITHER brands_id OR custom brand tags
        $query = Photo::where(function($q) {
            // Official brands via brands_id
            $q->whereNotNull('brands_id')->where('brands_id', '>', 0)
                // OR custom brand tags
                ->orWhereHas('customTags', function($q) {
                    $q->where('tag', 'like', '%brand%');
                });
        });

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $totalPhotos = $query->count();
        $this->info("Processing {$totalPhotos} photos with brands (official + custom tags)...");

        if ($totalPhotos === 0) {
            $this->warn('No photos with brands found.');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($totalPhotos);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');

        // Process photos in chunks - include customTags relationship
        $query->with('customTags')->chunkById(500, function ($photos) use ($progressBar) {
            foreach ($photos as $photo) {
                $this->processPhoto($photo);
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Export comprehensive CSV
        $this->exportComprehensiveCSV();

        // Show detailed summary
        $this->displayDetailedSummary();

        return 0;
    }

    protected function processPhoto(Photo $photo): void
    {
        $this->photosProcessed++;

        $tags = $photo->tags();
        $brandsInPhoto = [];
        $objectsInPhoto = [];

        // 1. Extract brands from the official brands table (via tags() method)
        if (!empty($tags)) {
            foreach ($tags as $categoryKey => $categoryTags) {
                if ($categoryKey === 'brands') {
                    // Handle official brands category
                    foreach ($categoryTags as $brandKey => $quantity) {
                        if ($quantity > 0) {
                            // Preserve exact brand key
                            $brandKey = $this->normalizeBrandKey($brandKey);
                            $brandsInPhoto[$brandKey] = true;
                            $this->brandsFromOfficial[$brandKey] = true;
                            $this->trackBrandVariation($brandKey);
                        }
                    }
                    continue;
                }

                // Process other categories for objects
                foreach ($categoryTags as $objectKey => $quantity) {
                    if ($quantity > 0) {
                        $normalized = $this->normalizeTagKey($objectKey);

                        // Check if it's actually a brand in another category
                        if ($this->isBrandInBrandslist($normalized)) {
                            $brandKey = $this->normalizeBrandKey($normalized);
                            $brandsInPhoto[$brandKey] = true;
                            $this->trackBrandVariation($brandKey);
                        } else {
                            // It's an object - track it globally
                            $categoryNorm = strtolower(trim($categoryKey));
                            $objectNorm = strtolower(trim($normalized));
                            $objectFullKey = "{$categoryNorm}.{$objectNorm}";

                            // Track this object globally
                            $this->allPossibleObjects[$objectFullKey] = true;
                            $this->objectGlobalCounts[$objectFullKey] =
                                ($this->objectGlobalCounts[$objectFullKey] ?? 0) + 1;

                            // Add to photo's objects for brand association
                            $objectsInPhoto[] = [
                                'category' => $categoryNorm,
                                'object' => $objectNorm,
                            ];

                            // Track unique categories
                            $this->uniqueCategories[$categoryNorm] = true;
                        }
                    }
                }
            }
        }

        // 2. Extract brands from custom tags (brand:xxx or brand=xxx)
        if ($photo->customTags) {
            foreach ($photo->customTags as $customTag) {
                if (stripos($customTag->tag, 'brand') !== false) {
                    // Parse brand from tag like "brand:coke" or "brand=pepsi" or "brand:coke=3"
                    $brandKey = $this->extractBrandFromCustomTag($customTag->tag);
                    if ($brandKey) {
                        // Preserve exact brand key
                        $brandKey = $this->normalizeBrandKey($brandKey);
                        $brandsInPhoto[$brandKey] = true;
                        $this->brandsFromCustom[$brandKey] = true;
                        $this->trackBrandVariation($brandKey);
                    }
                }
            }
        }

        // Now record brand-object combinations if brands exist in photo
        if (!empty($brandsInPhoto)) {
            foreach (array_keys($brandsInPhoto) as $brand) {
                // Increment photo count for this brand
                $this->brandPhotoCounts[$brand] = ($this->brandPhotoCounts[$brand] ?? 0) + 1;

                // Initialize brand if not exists
                if (!isset($this->brandObjects[$brand])) {
                    $this->brandObjects[$brand] = [];
                }

                // Record each object found with this brand
                foreach ($objectsInPhoto as $obj) {
                    $objectKey = "{$obj['category']}.{$obj['object']}";

                    // Track co-occurrence count
                    $this->brandObjects[$brand][$objectKey] =
                        ($this->brandObjects[$brand][$objectKey] ?? 0) + 1;

                    // Track which categories this object belongs to
                    $this->objectCategories[$objectKey] = $obj['category'];
                }
            }
        }
    }

    /**
     * Track brand key variations for debugging
     */
    protected function trackBrandVariation(string $brandKey): void
    {
        $lower = strtolower($brandKey);
        if (!isset($this->brandKeyVariations[$lower])) {
            $this->brandKeyVariations[$lower] = [];
        }
        $this->brandKeyVariations[$lower][$brandKey] = true;
    }

    /**
     * Extract brand name from custom tag formats
     * Handles: "brand:coke", "brand=pepsi", "brand:coke=3", etc.
     */
    protected function extractBrandFromCustomTag(string $tag): ?string
    {
        // Remove "brand:" or "brand=" prefix
        if (preg_match('/^brand[:=](.+)$/i', $tag, $matches)) {
            $brandPart = trim($matches[1]);

            // Handle cases like "coke=3" where =3 is quantity
            if (strpos($brandPart, '=') !== false) {
                [$brandName, $quantity] = explode('=', $brandPart, 2);
                return trim($brandName);
            }

            return $brandPart;
        }

        return null;
    }

    protected function exportComprehensiveCSV(): void
    {
        $timestamp = date('Y-m-d_His');
        $filename = storage_path("app/brand-relationships-{$timestamp}.csv");

        $handle = fopen($filename, 'w');

        // Comprehensive header
        fputcsv($handle, [
            'Brand',
            'Category',
            'Object',
            'Count',
            'Brand_Photo_Count',
            'Percentage',
            'Rank_In_Brand'
        ]);

        $minCount = (int) $this->option('min-count');

        // Sort brands preserving case-sensitive order
        $sortedBrands = array_keys($this->brandObjects);
        usort($sortedBrands, function($a, $b) {
            // Check if starts with number
            $aIsNum = preg_match('/^[0-9]/', $a);
            $bIsNum = preg_match('/^[0-9]/', $b);

            if ($aIsNum && !$bIsNum) return -1;  // Numbers come first
            if (!$aIsNum && $bIsNum) return 1;

            // Case-sensitive comparison to maintain distinct brands
            return strcmp($a, $b);
        });

        $totalExported = 0;

        // Export relationships for each brand
        foreach ($sortedBrands as $brand) {
            $objects = $this->brandObjects[$brand];
            $brandPhotoCount = $this->brandPhotoCounts[$brand] ?? 1;

            // Sort objects by count (descending) for this brand
            arsort($objects);

            $rank = 1;
            foreach ($objects as $objectKey => $count) {
                // Apply minimum count filter
                if ($count < $minCount) {
                    continue;
                }

                // Split category.object
                if (strpos($objectKey, '.') !== false) {
                    [$category, $object] = explode('.', $objectKey, 2);
                } else {
                    $category = 'unknown';
                    $object = $objectKey;
                }

                $percentage = round(($count / $brandPhotoCount) * 100, 1);

                fputcsv($handle, [
                    $brand,
                    $category,
                    $object,
                    $count,
                    $brandPhotoCount,
                    $percentage,
                    $rank
                ]);

                $rank++;
                $totalExported++;
            }
        }

        fclose($handle);

        $this->info("📁 CSV saved to: {$filename}");
        $this->line("   Total relationships exported: " . number_format($totalExported));

        // Also export a summary JSON for quick reference
        $this->exportSummaryJson($timestamp);
    }

    protected function exportSummaryJson(string $timestamp): void
    {
        $filename = storage_path("app/brand-summary-{$timestamp}.json");

        // Sort all possible objects by global count
        arsort($this->objectGlobalCounts);

        $summary = [
            'generated_at' => now()->toIso8601String(),
            'photos_processed' => $this->photosProcessed,
            'total_brands' => count($this->brandObjects),
            'total_unique_categories' => count($this->uniqueCategories),
            'total_unique_objects' => count($this->allPossibleObjects),
            'brands_by_photo_count' => [],
            'top_100_brands' => [],
            'categories' => array_keys($this->uniqueCategories),
            'all_possible_objects' => [],
            'case_variations' => [],
        ];

        // Add case variation information
        foreach ($this->brandKeyVariations as $lower => $variations) {
            if (count($variations) > 1) {
                $summary['case_variations'][$lower] = array_keys($variations);
            }
        }

        // Add ALL possible objects with their global frequencies
        foreach ($this->objectGlobalCounts as $objectKey => $count) {
            $summary['all_possible_objects'][] = [
                'object' => $objectKey,
                'global_count' => $count,
                'global_percentage' => round(($count / $this->photosProcessed) * 100, 2)
            ];
        }

        // Sort brands by photo count
        arsort($this->brandPhotoCounts);

        // Add ALL brands (not just top 100)
        foreach ($this->brandPhotoCounts as $brand => $count) {
            $summary['brands_by_photo_count'][] = [
                'brand' => $brand,
                'photo_count' => $count,
                'unique_objects' => count($this->brandObjects[$brand] ?? [])
            ];
        }

        // Also keep top 100 for backward compatibility
        $topBrands = array_slice($this->brandPhotoCounts, 0, 100, true);
        foreach ($topBrands as $brand => $count) {
            $summary['top_100_brands'][] = [
                'brand' => $brand,
                'photo_count' => $count,
                'unique_objects' => count($this->brandObjects[$brand] ?? [])
            ];
        }

        file_put_contents($filename, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("   Summary JSON saved to: {$filename}");

        // Also export a comprehensive objects CSV for reference
        $this->exportObjectsCatalog($timestamp);
    }

    /**
     * Export a catalog of ALL possible objects in the system
     */
    protected function exportObjectsCatalog(string $timestamp): void
    {
        $filename = storage_path("app/objects-catalog-{$timestamp}.csv");

        $handle = fopen($filename, 'w');
        fputcsv($handle, ['Category', 'Object', 'Global_Count', 'Global_Percentage']);

        // Sort by count descending
        arsort($this->objectGlobalCounts);

        foreach ($this->objectGlobalCounts as $objectKey => $count) {
            if (strpos($objectKey, '.') !== false) {
                [$category, $object] = explode('.', $objectKey, 2);
            } else {
                $category = 'unknown';
                $object = $objectKey;
            }

            $percentage = round(($count / $this->photosProcessed) * 100, 2);

            fputcsv($handle, [
                $category,
                $object,
                $count,
                $percentage
            ]);
        }

        fclose($handle);
        $this->line("   Objects catalog saved to: {$filename}");
    }

    protected function displayDetailedSummary(): void
    {
        $totalBrands = count($this->brandObjects);
        $totalRelationships = array_sum(array_map('count', $this->brandObjects));
        $totalCategories = count($this->uniqueCategories);
        $totalUniqueObjects = count($this->allPossibleObjects);

        // Calculate brand source statistics
        $officialBrandsCount = count($this->brandsFromOfficial);
        $customBrandsCount = count($this->brandsFromCustom);
        $bothSourcesCount = count(array_intersect_key($this->brandsFromOfficial, $this->brandsFromCustom));

        // Check for case variations
        $caseCollisions = 0;
        foreach ($this->brandKeyVariations as $variations) {
            if (count($variations) > 1) {
                $caseCollisions++;
            }
        }

        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║                    EXTRACTION SUMMARY                   ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->table(['Metric', 'Value'], [
            ['Photos processed', number_format($this->photosProcessed)],
            ['---', '---'],
            ['Total unique brands found', number_format($totalBrands)],
            ['Brands from official brands table', number_format($officialBrandsCount)],
            ['Brands from custom tags', number_format($customBrandsCount)],
            ['Brands in both sources', number_format($bothSourcesCount)],
            ['---', '---'],
            ['Brands with case variations', number_format($caseCollisions)],
            ['Total brand-object relationships', number_format($totalRelationships)],
            ['Total unique objects in system', number_format($totalUniqueObjects)],
            ['Unique categories', number_format($totalCategories)],
            ['Avg objects per brand', round($totalRelationships / max(1, $totalBrands), 1)],
        ]);

        // Show case variations if any
        if ($caseCollisions > 0) {
            $this->newLine();
            $this->warn("⚠️  Found {$caseCollisions} brands with case variations:");
            $shown = 0;
            foreach ($this->brandKeyVariations as $lower => $variations) {
                if (count($variations) > 1 && $shown++ < 10) {
                    $this->line("   " . implode(' / ', array_keys($variations)));
                }
            }
            if ($shown < $caseCollisions) {
                $this->line("   ... and " . ($caseCollisions - $shown) . " more");
            }
        }

        // Show top global objects
        $this->newLine();
        $this->info('Top 15 Most Common Objects (globally):');

        arsort($this->objectGlobalCounts);
        $topGlobalObjects = array_slice($this->objectGlobalCounts, 0, 15, true);

        $i = 1;
        foreach ($topGlobalObjects as $object => $count) {
            $percentage = round(($count / $this->photosProcessed) * 100, 1);
            $this->line(sprintf(
                '  %2d. %-35s %6s photos (%4.1f%%)',
                $i++,
                $object,
                number_format($count),
                $percentage
            ));
        }

        // Show brands grouped by frequency
        $this->newLine();
        $this->info('Brand Distribution:');

        $distribution = [
            '1000+ photos' => 0,
            '100-999 photos' => 0,
            '10-99 photos' => 0,
            '2-9 photos' => 0,
            '1 photo' => 0,
        ];

        foreach ($this->brandPhotoCounts as $count) {
            if ($count >= 1000) $distribution['1000+ photos']++;
            elseif ($count >= 100) $distribution['100-999 photos']++;
            elseif ($count >= 10) $distribution['10-99 photos']++;
            elseif ($count >= 2) $distribution['2-9 photos']++;
            else $distribution['1 photo']++;
        }

        foreach ($distribution as $range => $count) {
            $this->line(sprintf('   %-15s: %5d brands', $range, $count));
        }

        // Show top brands
        $this->newLine();
        $this->info('Top 20 Brands by Photo Count:');

        arsort($this->brandPhotoCounts);
        $topBrands = array_slice($this->brandPhotoCounts, 0, 20, true);

        $i = 1;
        foreach ($topBrands as $brand => $photoCount) {
            $objectCount = count($this->brandObjects[$brand] ?? []);
            // Show source indicator
            $source = '';
            if (isset($this->brandsFromOfficial[$brand]) && isset($this->brandsFromCustom[$brand])) {
                $source = '[both]';
            } elseif (isset($this->brandsFromOfficial[$brand])) {
                $source = '[official]';
            } elseif (isset($this->brandsFromCustom[$brand])) {
                $source = '[custom]';
            }

            $this->line(sprintf(
                '  %2d. %-20s %6s photos, %3d objects %s',
                $i++,
                $brand,
                number_format($photoCount),
                $objectCount,
                $source
            ));
        }

        // Show categories
        $this->newLine();
        $this->info('Categories found: ' . implode(', ', array_keys($this->uniqueCategories)));

        $this->newLine();
        $this->comment('Legend: [official] = from brands table, [custom] = from custom tags, [both] = found in both');
    }

    protected function isBrandInBrandslist(string $key): bool
    {
        static $brandCache = null;
        if ($brandCache === null) {
            // Load ALL brands preserving case
            $allBrands = BrandList::pluck('key')->flip()->all();
            $brandCache = $allBrands;
        }

        // Check for exact match (case-sensitive)
        return isset($brandCache[$key]);
    }

    protected function normalizeTagKey(string $tagKey): string
    {
        // Handle brand formats like "brand:xxx" or "brand=xxx"
        if (preg_match('/^brand[:=](.+)$/i', $tagKey, $matches)) {
            return trim($matches[1]);
        }

        // Additional normalization can go here
        return $tagKey;
    }

    protected function normalizeBrandKey(string $brandKey): string
    {
        // DO NOT normalize to lowercase - preserve exact brand keys
        // This prevents collisions between brands that differ only in case
        // e.g., "Apple" vs "apple", "MAX" vs "max"
        return trim($brandKey);
    }
}
