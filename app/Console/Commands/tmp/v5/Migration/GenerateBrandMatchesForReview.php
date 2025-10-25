<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateBrandMatchesForReview extends Command
{
    protected $signature = 'olm:v5:generate-matches
                            {--top=200 : Number of top brands to analyze}
                            {--sample=50 : Sample photos per brand}
                            {--import= : Import reviewed CSV file}';

    protected $description = 'Generate potential brand matches for manual review';

    public function handle()
    {
        if ($importFile = $this->option('import')) {
            $this->importReviewedMatches($importFile);
            return;
        }

        $this->info('Generating Brand Match Options for Review');
        $this->info('=========================================');

        // Get brands without relationships
        $brands = $this->getBrandsWithoutRelationships($this->option('top'));
        $this->info("Analyzing " . count($brands) . " brands without relationships\n");

        $matches = [];
        $bar = $this->output->createProgressBar(count($brands));

        foreach ($brands as $brand => $photoCount) {
            $analysis = $this->analyzeBrand($brand, $this->option('sample'));
            if (!empty($analysis['options'])) {
                $matches[$brand] = $analysis;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n");

        // Generate CSV for review
        $this->generateReviewFile($matches);
    }

    private function getBrandsWithoutRelationships(int $limit): array
    {
        // Get top brands
        $topBrands = DB::table('custom_tags')
            ->selectRaw("LOWER(REPLACE(SUBSTRING(tag, 7), ' ', '_')) as brand, COUNT(*) as count")
            ->where('tag', 'like', 'brand:%')
            ->groupBy('brand')
            ->orderByDesc('count')
            ->limit($limit)
            ->pluck('count', 'brand')
            ->toArray();

        // Filter out brands with existing relationships
        $existingBrands = DB::table('taggables')
            ->join('brandslist', 'taggables.taggable_id', '=', 'brandslist.id')
            ->where('taggables.taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->pluck('brandslist.key')
            ->toArray();

        $brandsWithoutRelationships = [];
        foreach ($topBrands as $brand => $count) {
            if (!in_array($brand, $existingBrands)) {
                $brandsWithoutRelationships[$brand] = $count;
            }
        }

        return $brandsWithoutRelationships;
    }

    private function analyzeBrand(string $brandKey, int $sampleSize): array
    {
        $photoIds = DB::table('custom_tags')
            ->whereRaw("LOWER(tag) = ?", ['brand:' . strtolower($brandKey)])
            ->limit($sampleSize)
            ->pluck('photo_id');

        if ($photoIds->isEmpty()) {
            return ['options' => []];
        }

        // Analyze what objects appear with this brand
        $objectStats = [];
        $singleBrandStats = [];
        $totalPhotos = 0;
        $singleBrandPhotos = 0;

        foreach ($photoIds as $photoId) {
            $photo = Photo::find($photoId);
            if (!$photo) continue;

            $totalPhotos++;

            // Count brands in this photo
            $brandCount = DB::table('custom_tags')
                ->where('photo_id', $photoId)
                ->where('tag', 'like', 'brand:%')
                ->count();

            $tags = $photo->tags();
            unset($tags['brands']);

            foreach ($tags as $category => $objects) {
                if (in_array($category, ['dogshit', 'pathways', 'art', 'brands'])) continue;

                foreach ($objects as $object => $qty) {
                    $key = "{$category}|{$object}";

                    // Overall stats
                    if (!isset($objectStats[$key])) {
                        $objectStats[$key] = [
                            'count' => 0,
                            'total_quantity' => 0,
                            'category' => $category,
                            'object' => $object,
                        ];
                    }
                    $objectStats[$key]['count']++;
                    $objectStats[$key]['total_quantity'] += $qty;

                    // Single-brand photo stats
                    if ($brandCount == 1) {
                        if (!isset($singleBrandStats[$key])) {
                            $singleBrandStats[$key] = ['count' => 0, 'quantity' => 0];
                        }
                        $singleBrandStats[$key]['count']++;
                        $singleBrandStats[$key]['quantity'] += $qty;
                        if ($brandCount == 1 && !in_array($photoId, [0])) { // Avoid counting same photo multiple times
                            $singleBrandPhotos = max($singleBrandPhotos, $singleBrandStats[$key]['count']);
                        }
                    }
                }
            }
        }

        // Calculate percentages and sort
        $options = [];
        $hasSpecificCategories = false;

        // First pass: check if we have specific categories (not just material/other)
        foreach ($objectStats as $key => $stats) {
            if (!in_array($stats['category'], ['material', 'other'])) {
                $hasSpecificCategories = true;
                break;
            }
        }

        foreach ($objectStats as $key => $stats) {
            // Filter out generic material tags if we have more specific options
            if ($hasSpecificCategories && $stats['category'] === 'material') {
                // Skip generic material tags when we have specific categories
                if (in_array($stats['object'], ['plastic', 'paper', 'metal', 'glass'])) {
                    continue;
                }
            }

            $percentage = round(($stats['count'] / $totalPhotos) * 100, 1);
            $singleBrandPercentage = isset($singleBrandStats[$key])
                ? round(($singleBrandStats[$key]['count'] / max(1, $singleBrandPhotos)) * 100, 1)
                : 0;

            $options[] = [
                'category' => $stats['category'],
                'object' => $stats['object'],
                'appears_in' => $stats['count'],
                'total_photos' => $totalPhotos,
                'percentage' => $percentage,
                'single_brand_percentage' => $singleBrandPercentage,
                'avg_quantity' => round($stats['total_quantity'] / $stats['count'], 1),
            ];
        }

        // Sort by percentage
        usort($options, function($a, $b) {
            // Prioritize single-brand percentage, then overall percentage
            if ($a['single_brand_percentage'] != $b['single_brand_percentage']) {
                return $b['single_brand_percentage'] <=> $a['single_brand_percentage'];
            }
            return $b['percentage'] <=> $a['percentage'];
        });

        // Mark high-confidence options
        $finalOptions = [];
        foreach ($options as $opt) {
            // Flag obvious matches
            if ($opt['single_brand_percentage'] >= 80) {
                $opt['confidence'] = 'HIGH';
            } elseif ($opt['single_brand_percentage'] >= 50) {
                $opt['confidence'] = 'MEDIUM';
            } else {
                $opt['confidence'] = 'LOW';
            }
            $finalOptions[] = $opt;
        }

        return [
            'options' => array_slice($finalOptions, 0, 5), // Top 5 options
            'total_photos' => $totalPhotos,
            'single_brand_photos' => $singleBrandPhotos,
        ];
    }

    private function generateReviewFile(array $matches): void
    {
        $csvContent = "brand,photos,single_brand_photos,option_1,opt1_pct,opt1_single,confidence,option_2,opt2_pct,opt2_single,option_3,opt3_pct,opt3_single,CHOSEN\n";

        // Count how many high-confidence matches we have
        $highConfidence = 0;
        $mediumConfidence = 0;
        $needsReview = 0;

        foreach ($matches as $brand => $data) {
            $row = [
                $brand,
                $data['total_photos'],
                $data['single_brand_photos'],
            ];

            // Check confidence of first option
            $firstOptionConfidence = '';
            if (isset($data['options'][0])) {
                $firstOptionConfidence = $data['options'][0]['confidence'] ?? '';
                if ($firstOptionConfidence === 'HIGH') {
                    $highConfidence++;
                } elseif ($firstOptionConfidence === 'MEDIUM') {
                    $mediumConfidence++;
                } else {
                    $needsReview++;
                }
            }

            // Add top 3 options
            for ($i = 0; $i < 3; $i++) {
                if (isset($data['options'][$i])) {
                    $opt = $data['options'][$i];
                    $row[] = "{$opt['category']}.{$opt['object']}";
                    $row[] = "{$opt['percentage']}%";
                    $row[] = "{$opt['single_brand_percentage']}%";
                    if ($i === 0) {
                        $row[] = $firstOptionConfidence;
                    }
                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                    if ($i === 0) {
                        $row[] = '';
                    }
                }
            }

            // Pre-fill CHOSEN for high confidence matches
            if ($firstOptionConfidence === 'HIGH') {
                $row[] = '1';  // Auto-select option 1 for high confidence
            } else {
                $row[] = '';   // Leave blank for review
            }

            $csvContent .= implode(',', $row) . "\n";
        }

        $filename = storage_path('app/brand_matches_for_review.csv');
        File::put($filename, $csvContent);

        // Also generate a more detailed JSON
        $jsonFilename = storage_path('app/brand_matches_detailed.json');
        File::put($jsonFilename, json_encode($matches, JSON_PRETTY_PRINT));

        $this->info("✅ Generated files for review:");
        $this->info("   CSV (for editing): {$filename}");
        $this->info("   JSON (detailed): {$jsonFilename}");

        $this->info("\n📊 Summary:");
        $this->info("   HIGH confidence (≥80% single-brand): {$highConfidence} brands - PRE-SELECTED!");
        $this->info("   MEDIUM confidence (50-79%): {$mediumConfidence} brands - please review");
        $this->info("   LOW confidence (<50%): {$needsReview} brands - needs careful review");

        $this->info("\nTo review:");
        $this->info("1. Open the CSV in Excel/Numbers/Google Sheets");
        $this->info("2. HIGH confidence matches are pre-selected with '1'");
        $this->info("3. For others, in the CHOSEN column, enter:");
        $this->info("   - '1' for option_1");
        $this->info("   - '2' for option_2");
        $this->info("   - '3' for option_3");
        $this->info("   - '1,2' for BOTH option_1 and option_2 (multiple relationships)");
        $this->info("   - 'SKIP' to skip this brand");
        $this->info("   - Or type a custom match like 'smoking.cigaretteBox'");
        $this->info("\n4. Save and import: php artisan olm:v5:generate-matches --import={$filename}");

        // Show preview focusing on ones that need review
        $this->info("\n📋 Preview (focusing on items needing review):");
        $count = 0;
        foreach ($matches as $brand => $data) {
            if ($count >= 10) break;

            // Show high confidence ones briefly
            if (isset($data['options'][0]) && $data['options'][0]['confidence'] === 'HIGH') {
                $opt = $data['options'][0];
                $this->info("✅ {$brand} → {$opt['category']}.{$opt['object']} (AUTO-SELECTED: {$opt['single_brand_percentage']}% confidence)");
            } else {
                // Show ones needing review in detail
                $count++;
                $this->line("\n❓ {$brand} ({$data['total_photos']} photos, {$data['single_brand_photos']} single-brand):");
                foreach ($data['options'] as $i => $opt) {
                    $confidence = $opt['confidence'] ?? 'LOW';
                    $marker = $confidence === 'HIGH' ? '✅' : ($confidence === 'MEDIUM' ? '⚠️' : '❌');
                    $this->line(sprintf(
                        "  %s %d. %s.%s - %d%% overall, %d%% single-brand",
                        $marker,
                        $i + 1,
                        $opt['category'],
                        $opt['object'],
                        $opt['percentage'],
                        $opt['single_brand_percentage']
                    ));
                }
            }
        }
    }

    private function importReviewedMatches(string $filename): void
    {
        if (!File::exists($filename)) {
            $this->error("File not found: {$filename}");
            return;
        }

        $content = File::get($filename);
        $lines = explode("\n", $content);
        $header = str_getcsv(array_shift($lines)); // Remove header

        $created = 0;
        $skipped = 0;
        $autoAccepted = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line);
            if (count($data) < 13) continue;  // Updated for new column structure

            $brand = $data[0];
            $chosen = trim($data[13] ?? '');  // CHOSEN column is now at index 13

            if (empty($chosen) || strtoupper($chosen) === 'SKIP') {
                $skipped++;
                continue;
            }

            // Handle multiple selections (e.g., "1,2" for both option 1 and 2)
            $selections = explode(',', $chosen);

            foreach ($selections as $selection) {
                $selection = trim($selection);

                // Determine what was chosen
                $category = null;
                $object = null;

                if ($selection === '1' && !empty($data[3])) {
                    [$category, $object] = explode('.', $data[3]);
                    if ($data[6] === 'HIGH') $autoAccepted++;  // Track auto-accepted high confidence
                } elseif ($selection === '2' && !empty($data[7])) {
                    [$category, $object] = explode('.', $data[7]);
                } elseif ($selection === '3' && !empty($data[10])) {
                    [$category, $object] = explode('.', $data[10]);
                } elseif (str_contains($selection, '.')) {
                    // Custom entry
                    [$category, $object] = explode('.', $selection);
                }

                if ($category && $object) {
                    if ($this->createRelationship($brand, $category, $object)) {
                        $created++;
                        $this->line("✓ {$brand} → {$category}.{$object}");
                    }
                }
            }
        }

        $this->info("\n✅ Import complete:");
        $this->info("   Created: {$created} relationships");
        $this->info("   Auto-accepted (high confidence): {$autoAccepted}");
        $this->info("   Skipped: {$skipped}");

        $this->info("\n📊 Checking new coverage:");
        $this->call('olm:v5:check-coverage');
    }

    private function createRelationship(string $brandKey, string $categoryKey, string $objectKey): bool
    {
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
            $this->warn("Category or object not found: {$categoryKey}.{$objectKey}");
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

        // Create relationship
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
}
