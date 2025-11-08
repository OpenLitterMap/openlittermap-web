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
        {--dry-run : Show what would run without API calls}
        {--min-lift=2.0 : Minimum lift for validation}
        {--min-photos=5 : Minimum photo support for validation}';

    protected $description = 'Validate brand-object relationships using AI with lift-based filtering';

    protected float $startTime;
    protected int $apiCalls = 0;
    protected float $estimatedCost = 0;

    public function handle(BrandValidator $validator)
    {
        $this->startTime = microtime(true);

        // Find most recent CSV file
        $csvFiles = glob(storage_path('app/ALL-brands-*.csv'));
        if (empty($csvFiles)) {
            $this->error("No CSV files found. Run: php artisan olm:log-brand-relationships --all");
            return 1;
        }

        // Use the most recent file
        rsort($csvFiles);
        $csvPath = $csvFiles[0];
        $this->info("Using CSV: " . basename($csvPath));

        // Load brands
        $allBrands = $validator->loadFromCSV($csvPath);
        $this->info("Loaded " . count($allBrands) . " brands from CSV");

        // Apply filters
        $minLift = (float) $this->option('min-lift');
        $minPhotos = (int) $this->option('min-photos');

        // Filter by brand/letter/all
        if ($brandKey = $this->option('brand')) {
            // Normalize the brand key to match CSV format
            $brandKey = strtolower(trim($brandKey));
            $brands = [$brandKey => $allBrands[$brandKey] ?? null];

            if (!$brands[$brandKey]) {
                $this->error("Brand '{$brandKey}' not found");
                $this->line("Did you mean one of these?");
                $similar = array_filter(array_keys($allBrands), fn($k) =>
                    stripos($k, substr($brandKey, 0, 3)) === 0
                );
                foreach (array_slice($similar, 0, 10) as $key) {
                    $this->line("  - {$key}");
                }
                return 1;
            }
        } elseif ($letter = $this->option('letter')) {
            $letter = strtolower($letter);
            $brands = array_filter($allBrands, fn($b) =>
            str_starts_with(strtolower($b['brand_key']), $letter)
            );
            $this->info("Filtered to " . count($brands) . " brands for letter '{$letter}'");
        } elseif ($this->option('all')) {
            $brands = $allBrands;
        } else {
            $this->error('Specify --brand="brand name", --letter=X, or --all');
            $this->info('Examples:');
            $this->line('  php artisan olm:validate-brands --brand=coke');
            $this->line('  php artisan olm:validate-brands --letter=a');
            $this->line('  php artisan olm:validate-brands --all --dry-run');
            return 1;
        }

        // Filter relationships by lift and photo support
        $totalRelationships = 0;
        $filteredRelationships = 0;

        foreach ($brands as &$brandData) {
            $totalRelationships += count($brandData['relationships']);

            $brandData['relationships'] = array_filter(
                $brandData['relationships'],
                fn($rel) => $rel['lift'] >= $minLift && $rel['photo_count'] >= $minPhotos
            );

            $filteredRelationships += count($brandData['relationships']);
        }

        // Remove brands with no remaining relationships
        $brands = array_filter($brands, fn($b) => !empty($b['relationships']));

        $this->info(sprintf(
            "Filtered relationships: %d/%d (%.1f%%) meet criteria (lift≥%.1f, photos≥%d)",
            $filteredRelationships,
            $totalRelationships,
            $totalRelationships > 0 ? ($filteredRelationships / $totalRelationships * 100) : 0,
            $minLift,
            $minPhotos
        ));

        // Dry-run mode
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No API calls will be made');
            $this->newLine();

            $this->info('Would process these brands:');
            $preview = array_slice(array_keys($brands), 0, 20);
            foreach ($preview as $i => $key) {
                $brand = $brands[$key];
                $this->line(sprintf(
                    '  %2d. %-20s (%d relationships to validate)',
                    $i + 1,
                    $key,
                    count($brand['relationships'])
                ));
            }

            if (count($brands) > 20) {
                $this->line("  ... and " . (count($brands) - 20) . " more");
            }

            $this->newLine();
            $this->table(['Statistics', 'Value'], [
                ['Total brands', count($brands)],
                ['Total relationships', $filteredRelationships],
                ['Avg relationships/brand', round($filteredRelationships / max(1, count($brands)), 1)],
                ['Estimated API cost', '$' . number_format(count($brands) * 0.008, 2)],
            ]);

            return 0;
        }

        // Confirm
        $cost = count($brands) * 0.008; // ~$0.008 per brand with GPT-4o
        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Brands to validate', count($brands)],
            ['Relationships to validate', $filteredRelationships],
            ['Lift filter', '≥ ' . $minLift],
            ['Photo count filter', '≥ ' . $minPhotos],
            ['Estimated cost', '$' . number_format($cost, 2)],
            ['Estimated time', gmdate('i:s', count($brands) * 1)], // ~1s per brand
        ]);

        if (!$this->confirm('Proceed with validation?')) {
            return 0;
        }

        // Process
        $bar = $this->output->createProgressBar(count($brands));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

        $results = [];
        $errors = [];
        $lowConfidenceBrands = [];

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

                // Track low confidence brands
                if (($result['brand_identity']['confidence'] ?? '') === 'low') {
                    $lowConfidenceBrands[] = $brandKey;
                }

            } catch (\Exception $e) {
                $errors[$brandKey] = $e->getMessage();
                $this->newLine();
                $this->error("  ❌ {$brandKey}: " . substr($e->getMessage(), 0, 100));
            }

            $bar->advance();
            usleep(800000); // 0.8s rate limit for OpenAI
        }

        $bar->finish();
        $this->newLine(2);

        // Show low confidence brands immediately
        if (!empty($lowConfidenceBrands)) {
            $this->warn('⚠️  Low confidence brands requiring manual review:');
            foreach ($lowConfidenceBrands as $brand) {
                $this->line("   - {$brand}");
            }
            $this->newLine();
        }

        // Calculate actual cost
        $this->estimatedCost = $this->apiCalls * 0.008;

        // Summary
        $this->displaySummary($results, $errors, $filteredRelationships);

        // Save summary JSON
        Storage::makeDirectory('brands');
        Storage::put('brands/summary.json', json_encode([
            'generated_at' => now()->toIso8601String(),
            'csv_file' => basename($csvPath),
            'filters' => [
                'min_lift' => $minLift,
                'min_photos' => $minPhotos,
            ],
            'total_brands' => count($results),
            'failed_brands' => count($errors),
            'low_confidence_brands' => $lowConfidenceBrands,
            'api_calls' => $this->apiCalls,
            'estimated_cost' => $this->estimatedCost,
            'results' => $results,
            'errors' => $errors,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("📋 Summary saved to: storage/app/brands/summary.json");

        // Export config if requested
        if ($this->option('export')) {
            $outputPath = storage_path('app/BrandsConfig_generated.php');
            $validator->generateConfig($results, $outputPath);

            $this->newLine();
            $this->info("✅ Generated config file:");
            $this->line("   {$outputPath}");
            $this->newLine();
            $this->warn("⚠️  Review before merging into app/Tags/BrandsConfig.php");
            $this->line("   Especially review low-confidence brands marked with // ⚠️ LOW CONFIDENCE");
        }

        $this->newLine();
        $this->info("📁 Individual brand validations: storage/app/brands/*.json");

        return 0;
    }

    protected function displaySummary(array $results, array $errors, int $totalRelationships): void
    {
        $stats = [
            'total' => count($results),
            'failed' => count($errors),
            'unknown' => 0,
            'very_high' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'relationships_validated' => $totalRelationships,
            'relationships_approved' => 0,
            'relationships_rejected' => 0,
        ];

        foreach ($results as $result) {
            if ($result['unknown_brand'] ?? false) {
                $stats['unknown']++;
                continue;
            }

            // Map confidence levels
            $conf = $result['brand_identity']['confidence'] ?? 'unknown';
            if ($conf === 'very_high') $stats['very_high']++;
            elseif ($conf === 'high') $stats['high']++;
            elseif ($conf === 'medium') $stats['medium']++;
            elseif ($conf === 'low') $stats['low']++;

            $stats['relationships_approved'] += count($result['valid_relationships'] ?? []);
            $stats['relationships_rejected'] += count($result['excluded_with_reason'] ?? []);
        }

        // Add timing and cost
        $elapsed = microtime(true) - $this->startTime;
        $avgTime = count($results) > 0 ? $elapsed / count($results) : 0;

        $approvalRate = $stats['relationships_validated'] > 0
            ? ($stats['relationships_approved'] / $stats['relationships_validated'] * 100)
            : 0;

        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║                  VALIDATION SUMMARY                    ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->table(['Metric', 'Count'], [
            ['Brands processed', $stats['total']],
            ['Failed validations', $stats['failed']],
            ['Unknown brands', $stats['unknown']],
            ['---', '---'],
            ['Very high confidence', $stats['very_high']],
            ['High confidence', $stats['high']],
            ['Medium confidence', $stats['medium']],
            ['⚠️  Low confidence', $stats['low']],
            ['---', '---'],
            ['Relationships validated', $stats['relationships_validated']],
            ['Relationships approved', sprintf('%d (%.1f%%)', $stats['relationships_approved'], $approvalRate)],
            ['Relationships rejected', $stats['relationships_rejected']],
            ['---', '---'],
            ['API calls made', $this->apiCalls],
            ['Actual cost', '$' . number_format($this->estimatedCost, 2)],
            ['Total time', gmdate('H:i:s', (int) $elapsed)],
            ['Avg per brand', number_format($avgTime, 2) . 's'],
        ]);

        if ($stats['low'] > 0) {
            $this->newLine();
            $this->warn("⚠️  {$stats['low']} brands have LOW confidence and need manual review");
            $this->line("   Check storage/app/brands/*.json for details");
        }

        if ($stats['failed'] > 0) {
            $this->newLine();
            $this->error("❌ {$stats['failed']} brands failed validation");
            $this->line("   These will need to be retried or manually configured");
        }
    }
}
