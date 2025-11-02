<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Services\Migration\BrandValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ValidateBrands extends Command
{
    protected $signature = 'olm:validate-brands
        {--brand= : Validate specific brand}
        {--letter= : Validate all brands starting with letter}
        {--all : Validate all brands}
        {--export : Generate BrandsConfig_generated.php}
        {--dry-run : Show what would run without API calls}';

    protected $description = 'Validate brand-object relationships using AI';

    protected float $startTime;
    protected int $apiCalls = 0;
    protected float $estimatedCost = 0;

    public function handle(BrandValidator $validator)
    {
        $this->startTime = microtime(true);

        // Get CSV path
        $csvPath = storage_path('app/ALL-brands-2025-11-02-180656.csv');
        if (!file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return 1;
        }

        // Load brands
        $allBrands = $validator->loadFromCSV($csvPath);
        $this->info("Loaded " . count($allBrands) . " brands");

        // Filter (keeping exact brand keys)
        if ($brandKey = $this->option('brand')) {
            $brands = [$brandKey => $allBrands[$brandKey] ?? null];
            if (!$brands[$brandKey]) {
                $this->error("Brand '{$brandKey}' not found");
                $this->line("Available brands starting with same letter:");
                $firstChar = strtoupper(substr($brandKey, 0, 1));
                foreach ($allBrands as $key => $data) {
                    if (stripos($key, $firstChar) === 0) {
                        $this->line("  - {$key}");
                    }
                }
                return 1;
            }
        } elseif ($letter = $this->option('letter')) {
            $letter = strtoupper($letter);
            $brands = array_filter($allBrands, fn($b) => $b['letter'] === $letter);
            $this->info("Filtered to " . count($brands) . " brands for letter '{$letter}'");
        } elseif ($this->option('all')) {
            $brands = $allBrands;
        } else {
            $this->error('Specify --brand="brand name", --letter=X, or --all');
            return 1;
        }

        // Dry-run mode
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No API calls will be made');
            $this->newLine();
            $this->info('Would process these brands:');
            foreach (array_slice(array_keys($brands), 0, 10) as $key) {
                $this->line("  - {$key}");
            }
            if (count($brands) > 10) {
                $this->line("  ... and " . (count($brands) - 10) . " more");
            }
            return 0;
        }

        // Confirm
        $cost = count($brands) * 0.008; // ~$0.008 per brand with GPT-4o
        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Brands to validate', count($brands)],
            ['Estimated cost', '$' . number_format($cost, 2)],
            ['Estimated time', gmdate('i:s', count($brands) * 1)], // ~1s per brand
        ]);

        if (!$this->confirm('Proceed?')) {
            return 0;
        }

        // Process
        $bar = $this->output->createProgressBar(count($brands));
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% - %message%');

        $results = [];
        $errors = [];

        foreach ($brands as $brandKey => $brandData) {
            $bar->setMessage($brandKey);

            try {
                $result = $validator->validateBrand(
                    $brandKey,
                    $brandData['relationships'],
                    $brandData['total']
                );
                $results[$brandKey] = $result;
                $this->apiCalls++;

                // Warn on low confidence
                if (($result['brand_identity']['confidence'] ?? '') === 'low') {
                    $this->newLine();
                    $this->warn("  ⚠️  {$brandKey}: Low confidence - review manually");
                }

            } catch (\Exception $e) {
                $errors[$brandKey] = $e->getMessage();
                $this->newLine();
                $this->error("  ❌ {$brandKey}: " . $e->getMessage());
            }

            $bar->advance();
            usleep(800000); // 0.8s rate limit
        }

        $bar->finish();
        $this->newLine(2);

        // Calculate actual cost
        $this->estimatedCost = $this->apiCalls * 0.008;

        // Summary
        $this->displaySummary($results, $errors);

        // Save summary JSON
        Storage::put('brands/summary.json', json_encode([
            'generated_at' => now()->toIso8601String(),
            'total_brands' => count($results),
            'failed_brands' => count($errors),
            'api_calls' => $this->apiCalls,
            'estimated_cost' => $this->estimatedCost,
            'results' => $results,
            'errors' => $errors,
        ], JSON_PRETTY_PRINT));

        $this->info("📋 Summary saved to: storage/app/brands/summary.json");

        // Export config if requested
        if ($this->option('export')) {
            $outputPath = storage_path('app/BrandsConfig_generated.php');
            $validator->generateConfig($results, $outputPath);

            $this->newLine();
            $this->info("✅ Generated config:");
            $this->line("   {$outputPath}");
            $this->newLine();
            $this->warn("⚠️  Review before merging into app/Tags/BrandsConfig.php");
        }

        $this->newLine();
        $this->info("📁 Individual results: storage/app/brands/*.json");

        return 0;
    }

    protected function displaySummary(array $results, array $errors): void
    {
        $stats = [
            'total' => count($results),
            'failed' => count($errors),
            'unknown' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'relationships' => 0,
        ];

        foreach ($results as $result) {
            if ($result['unknown_brand'] ?? false) {
                $stats['unknown']++;
                continue;
            }

            $conf = $result['brand_identity']['confidence'] ?? 'unknown';
            if (isset($stats[$conf])) $stats[$conf]++;

            $stats['relationships'] += count($result['valid_relationships'] ?? []);
        }

        // Add timing and cost
        $elapsed = microtime(true) - $this->startTime;
        $avgTime = count($results) > 0 ? $elapsed / count($results) : 0;

        $this->table(['Metric', 'Count'], [
            ['Processed', $stats['total']],
            ['Failed', $stats['failed']],
            ['Unknown brands', $stats['unknown']],
            ['High confidence', $stats['high']],
            ['Medium confidence', $stats['medium']],
            ['⚠️  Low confidence', $stats['low']],
            ['Valid relationships', $stats['relationships']],
            ['---', '---'],
            ['API calls made', $this->apiCalls],
            ['Actual cost', '$' . number_format($this->estimatedCost, 2)],
            ['Total time', gmdate('i:s', (int) $elapsed)],
            ['Avg per brand', number_format($avgTime, 2) . 's'],
        ]);

        if ($stats['low'] > 0) {
            $this->newLine();
            $this->warn("⚠️  {$stats['low']} brands need manual review (check storage/app/brands/*.json)");
        }
    }
}
