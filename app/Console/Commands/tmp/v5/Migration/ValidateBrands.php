<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Services\Migration\BrandValidator;
use Illuminate\Console\Command;

class ValidateBrands extends Command
{
    protected $signature = 'olm:validate-brands
        {--brand= : Validate specific brand}
        {--letter= : Validate all brands starting with letter}
        {--all : Validate all brands}
        {--min-photos=1 : Process all brands with at least N photos (can be used alone)}
        {--min-count=2 : Minimum co-occurrence count to consider}
        {--max-objects=50 : Maximum objects per brand to validate}
        {--dry-run : Show what would be processed without API calls}';

    protected $description = 'Validate brand-object relationships using AI (use --min-photos=N as standalone filter)';

    protected float $startTime;
    protected int $apiCalls = 0;
    protected float $estimatedCost = 0;
    protected BrandValidator $validator;

    public function handle(BrandValidator $validator)
    {
        $this->startTime = microtime(true);
        $this->validator = $validator;

        // Find most recent CSV
        $csvFiles = glob(storage_path('app/brand-relationships-*.csv'));
        if (empty($csvFiles)) {
            $this->error("No CSV files found. Run: php artisan olm:extract-brands");
            return 1;
        }

        rsort($csvFiles);
        $csvPath = $csvFiles[0];
        $this->info("Using CSV: " . basename($csvPath));

        // Load brands from CSV
        $allBrands = $this->loadBrandsFromCSV($csvPath);
        $this->info("Loaded " . count($allBrands) . " brands");

        // Filter brands based on options
        $brands = $this->filterBrands($allBrands);

        if (empty($brands)) {
            $this->error("No brands to process based on your filters");
            return 1;
        }

        // Apply min-photos filter
        $minPhotos = (int) $this->option('min-photos');
        if ($minPhotos > 0) {
            $brands = array_filter($brands, fn($b) => ($b['photo_count'] ?? 0) >= $minPhotos);

            if (empty($brands)) {
                $this->error("No brands found with at least {$minPhotos} photos");
                return 1;
            }

            $this->info("Filtered to " . count($brands) . " brands with at least {$minPhotos} photos");
        }

        // Apply min-count filter and max-objects limit
        $minCount = (int) $this->option('min-count');
        $maxObjects = (int) $this->option('max-objects');

        foreach ($brands as &$brandData) {
            // Filter out relationships below minimum count
            $filteredObjects = [];
            foreach ($brandData['objects'] as $objectKey => $objectInfo) {
                // Handle both formats
                $count = is_array($objectInfo) ? $objectInfo['count'] : $objectInfo;
                if ($count >= $minCount) {
                    $filteredObjects[$objectKey] = $objectInfo;
                }
            }

            // Sort by count and limit to max objects
            if (is_array(reset($filteredObjects))) {
                // New format - sort by count
                uasort($filteredObjects, function($a, $b) {
                    return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
                });
            } else {
                // Simple format
                arsort($filteredObjects);
            }

            $brandData['objects'] = array_slice($filteredObjects, 0, $maxObjects, true);
        }

        // Remove brands with no remaining objects
        $brands = array_filter($brands, fn($b) => !empty($b['objects']));

        if ($this->option('dry-run')) {
            return $this->handleDryRun($brands);
        }

        // Process brands
        $this->processBrands($brands);

        return 0;
    }

    protected function loadBrandsFromCSV(string $path): array
    {
        $brands = [];
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle); // Skip header

        while ($row = fgetcsv($handle)) {
            // Preserve the original brand key exactly as it appears
            // This should match what's in the brandslist table
            $brand = $row[0];
            // Only trim quotes if they were added by CSV export
            if (substr($brand, 0, 1) === '"' && substr($brand, -1) === '"') {
                $brand = substr($brand, 1, -1);
            }

            $category = $row[1];
            $object = $row[2];
            $count = (int) $row[3];
            $brandPhotoCount = (int) $row[4];
            $percentage = (float) $row[5];

            if (!isset($brands[$brand])) {
                $brands[$brand] = [
                    'photo_count' => $brandPhotoCount,
                    'objects' => [],
                    'categories' => []
                ];
            }

            // Store as category.object
            $objectKey = "{$category}.{$object}";
            $brands[$brand]['objects'][$objectKey] = [
                'count' => $count,
                'percentage' => $percentage,
                'category' => $category
            ];

            // Track unique categories for this brand
            $brands[$brand]['categories'][$category] = true;
        }

        fclose($handle);

        // Convert categories to array
        foreach ($brands as &$brand) {
            $brand['categories'] = array_keys($brand['categories']);
        }

        return $brands;
    }

    protected function filterBrands(array $allBrands): array
    {
        if ($brandKey = $this->option('brand')) {
            // Preserve the exact brand key as provided
            // The user should provide it as it exists in brandslist
            $brandKey = trim($brandKey);

            if (!isset($allBrands[$brandKey])) {
                $this->error("Brand '{$brandKey}' not found");
                $this->line("Note: Brand keys are case-sensitive and must match brandslist table exactly");

                // Help user find similar brands (case-insensitive search)
                $this->line("Did you mean one of these?");
                $similar = array_filter(array_keys($allBrands), fn($k) =>
                    stripos($k, substr($brandKey, 0, min(3, strlen($brandKey)))) !== false
                );
                foreach (array_slice($similar, 0, 10) as $key) {
                    $this->line("  - {$key}");
                }
                return [];
            }
            return [$brandKey => $allBrands[$brandKey]];

        } elseif ($letter = $this->option('letter')) {
            // For letter filtering, check first character case-insensitively
            $letter = strtolower($letter);
            return array_filter($allBrands, fn($b, $key) =>
                strtolower(substr($key, 0, 1)) === $letter,
                ARRAY_FILTER_USE_BOTH
            );

        } elseif ($this->option('all')) {
            return $allBrands;

        } elseif ((int) $this->option('min-photos') > 0) {
            // If min-photos is specified without other filters, process all brands meeting that threshold
            $minPhotos = (int) $this->option('min-photos');
            $this->info("Processing all brands with at least {$minPhotos} photos...");
            return $allBrands; // Return all brands, min-photos filter will be applied next
        }

        $this->error('Specify --brand="name", --letter=X, --all, or --min-photos=N');
        return [];
    }

    protected function handleDryRun(array $brands): int
    {
        $this->warn('DRY RUN MODE - No API calls will be made');
        $this->newLine();

        $totalObjects = array_sum(array_map(fn($b) => count($b['objects']), $brands));

        // Calculate photo count distribution
        $photoDistribution = [
            '1000+' => 0,
            '100-999' => 0,
            '10-99' => 0,
            '2-9' => 0,
            '1' => 0
        ];

        foreach ($brands as $brand) {
            $photoCount = $brand['photo_count'] ?? 0;
            if ($photoCount >= 1000) $photoDistribution['1000+']++;
            elseif ($photoCount >= 100) $photoDistribution['100-999']++;
            elseif ($photoCount >= 10) $photoDistribution['10-99']++;
            elseif ($photoCount >= 2) $photoDistribution['2-9']++;
            else $photoDistribution['1']++;
        }

        $this->table(['Metric', 'Value'], [
            ['Brands to process', count($brands)],
            ['Total relationships', $totalObjects],
            ['Avg objects/brand', round($totalObjects / max(1, count($brands)), 1)],
            ['Estimated API cost', '$' . number_format(count($brands) * 0.005, 2)],
            ['Estimated time', count($brands) . ' seconds'],
        ]);

        $this->newLine();
        $this->info('Photo count distribution:');
        foreach ($photoDistribution as $range => $count) {
            if ($count > 0) {
                $this->line(sprintf('  %s photos: %d brands', $range, $count));
            }
        }

        $this->newLine();
        $this->info('Sample brands to process:');
        $sample = array_slice($brands, 0, 10, true);
        foreach ($sample as $brand => $data) {
            $this->line(sprintf('  %-20s (%d photos, %d objects)',
                $brand,
                $data['photo_count'] ?? 0,
                count($data['objects'])
            ));
        }

        return 0;
    }

    protected function processBrands(array $brands): void
    {
        $total = count($brands);
        $this->info("Processing {$total} brands...");
        $this->newLine();

        if (!$this->confirm("Estimated cost: $" . number_format($total * 0.005, 2) . ". Continue?")) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

        $results = [];
        $errors = [];
        $stats = [
            'approved_total' => 0,
            'rejected_total' => 0,
        ];

        foreach ($brands as $brandKey => $brandData) {
            $bar->setMessage($brandKey);

            try {
                // Pass complete brand data including photo count and categories
                $result = $this->validator->validateBrand($brandKey, $brandData);
                $results[$brandKey] = $result;
                $this->apiCalls++;

                // Update stats
                $stats['approved_total'] += count($result['valid'] ?? []);
                $stats['rejected_total'] += count($result['invalid'] ?? []);

            } catch (\Exception $e) {
                $errors[$brandKey] = $e->getMessage();
                $this->newLine();
                $this->error("  ❌ {$brandKey}: " . substr($e->getMessage(), 0, 100));
            }

            $bar->advance();
            usleep(500000); // 0.5s rate limit
        }

        $bar->finish();
        $this->newLine(2);

        // Store results
        $this->validator->saveResults($results);

        // Display summary
        $this->displaySummary($results, $errors, $stats);
    }

    protected function displaySummary(array $results, array $errors, array $stats): void
    {
        $elapsed = microtime(true) - $this->startTime;
        $this->estimatedCost = $this->apiCalls * 0.005;

        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║                  VALIDATION COMPLETE                   ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        $approvalRate = ($stats['approved_total'] + $stats['rejected_total']) > 0
            ? ($stats['approved_total'] / ($stats['approved_total'] + $stats['rejected_total']) * 100)
            : 0;

        $this->table(['Metric', 'Value'], [
            ['Brands processed', count($results)],
            ['Failed validations', count($errors)],
            ['---', '---'],
            ['Relationships approved', $stats['approved_total']],
            ['Relationships rejected', $stats['rejected_total']],
            ['Approval rate', number_format($approvalRate, 1) . '%'],
            ['---', '---'],
            ['API calls', $this->apiCalls],
            ['Total cost', '$' . number_format($this->estimatedCost, 2)],
            ['Total time', gmdate('i:s', (int) $elapsed)],
        ]);

        if (count($errors) > 0) {
            $this->newLine();
            $this->warn("⚠️  " . count($errors) . " brands failed validation");
        }

        $this->newLine();
        $this->info("✅ Results saved to: storage/app/brand-validations/");
        $this->line("📁 To generate config, use: php artisan olm:generate-brands-config");
    }
}
