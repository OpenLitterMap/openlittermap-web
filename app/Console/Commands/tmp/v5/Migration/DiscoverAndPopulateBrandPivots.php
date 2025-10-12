<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Tags\ClassifyTagsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscoverAndPopulateBrandPivots extends Command
{
    protected $signature = 'olm:v5:discover-pivots
        {--limit=1000 : Number of photos to analyze}
        {--threshold=3 : Minimum occurrences to create pivot}
        {--dry-run : Analyze without creating pivots}';

    protected $description = 'Discover brand-object relationships from existing data and create pivots';

    private ClassifyTagsService $classifyTags;
    private array $coOccurrences = [];
    private array $ambiguousCases = [];
    private array $createdPivots = [];

    public function __construct(ClassifyTagsService $classifyTags)
    {
        parent::__construct();
        $this->classifyTags = $classifyTags;
    }

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $threshold = (int) $this->option('threshold');
        $dryRun = $this->option('dry-run');

        $this->info("Analyzing brand-object relationships from existing data...");
        $this->info("Sample size: {$limit} photos");
        $this->info("Co-occurrence threshold: {$threshold}");
        $this->info("Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE (will create pivots)"));
        $this->newLine();

        // Step 1: Analyze photos to find brand-object patterns
        $this->discoverRelationships($limit);

        // Step 2: Process discovered relationships
        $this->processDiscoveredRelationships($threshold, $dryRun);

        // Step 3: Report results
        $this->displayResults($dryRun);

        return self::SUCCESS;
    }

    /**
     * Analyze photos to discover brand-object co-occurrences
     */
    private function discoverRelationships(int $limit): void
    {
        $bar = $this->output->createProgressBar($limit);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Analyzing photos...');
        $bar->start();

        $analyzed = 0;

        Photo::whereNotNull('brands_id')
            ->orderBy('id')
            ->chunk(100, function ($photos) use (&$analyzed, $limit, $bar) {
                foreach ($photos as $photo) {
                    if ($analyzed >= $limit) {
                        return false;
                    }

                    $this->analyzePhoto($photo);
                    $analyzed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * Analyze a single photo for brand-object relationships
     */
    private function analyzePhoto(Photo $photo): void
    {
        $tags = $photo->tags();

        if (empty($tags) || !isset($tags['brands'])) {
            return;
        }

        $brands = [];
        $objectsByCategory = [];

        // Parse brands
        foreach ($tags['brands'] as $brandKey => $qty) {
            if ($qty > 0) {
                $classified = $this->classifyTags->classify($brandKey);
                if ($classified['type'] === 'brand' || $classified['type'] === 'undefined') {
                    $brands[] = [
                        'key' => $brandKey,
                        'normalized' => $classified['key'] ?? $brandKey,
                        'quantity' => (int) $qty
                    ];
                }
            }
        }

        // Parse objects by category
        foreach ($tags as $categoryKey => $items) {
            if ($categoryKey === 'brands') continue;

            $category = $this->classifyTags->getCategory($categoryKey);
            if (!$category) continue;

            foreach ($items as $objectKey => $qty) {
                if ($qty <= 0) continue;

                // Handle deprecated tag mappings
                $mapping = ClassifyTagsService::normalizeDeprecatedTag($objectKey);
                $newObjectKey = $mapping ? ($mapping['object'] ?? $objectKey) : $objectKey;

                $classified = $this->classifyTags->classify($newObjectKey);

                if ($classified['type'] === 'object' || $classified['type'] === 'undefined') {
                    $objectsByCategory[$categoryKey][] = [
                        'key' => $newObjectKey,
                        'original_key' => $objectKey,
                        'category_id' => $category->id,
                        'category_key' => $categoryKey,
                        'quantity' => (int) $qty
                    ];
                }
            }
        }

        // Record co-occurrences
        $this->recordCoOccurrences($photo->id, $brands, $objectsByCategory);
    }

    /**
     * Record brand-object co-occurrences for pattern analysis
     */
    private function recordCoOccurrences(int $photoId, array $brands, array $objectsByCategory): void
    {
        foreach ($brands as $brand) {
            $brandKey = $brand['normalized'];

            // Find best matches based on context
            $matches = $this->findBestMatches($brand, $objectsByCategory);

            if (count($matches) === 1) {
                // Clear single match - record co-occurrence
                $match = $matches[0];
                $relationKey = "{$match['category_key']}.{$match['key']}.{$brandKey}";

                if (!isset($this->coOccurrences[$relationKey])) {
                    $this->coOccurrences[$relationKey] = [
                        'category_key' => $match['category_key'],
                        'category_id' => $match['category_id'],
                        'object_key' => $match['key'],
                        'brand_key' => $brandKey,
                        'count' => 0,
                        'photo_ids' => []
                    ];
                }

                $this->coOccurrences[$relationKey]['count']++;
                $this->coOccurrences[$relationKey]['photo_ids'][] = $photoId;

            } elseif (count($matches) > 1) {
                // Ambiguous - multiple possible matches
                $this->ambiguousCases[] = [
                    'photo_id' => $photoId,
                    'brand' => $brandKey,
                    'possible_objects' => array_map(function($m) {
                        return "{$m['category_key']}.{$m['key']}";
                    }, $matches),
                    'quantity' => $brand['quantity']
                ];
            }
        }
    }

    /**
     * Find best object matches for a brand based on quantity and category logic
     */
    private function findBestMatches(array $brand, array $objectsByCategory): array
    {
        $matches = [];
        $brandQty = $brand['quantity'];
        $brandKey = $brand['normalized'];

        // Category preferences for common brands
        $categoryPreferences = $this->getCategoryPreferences($brandKey);

        // First pass: Look for exact quantity matches in preferred categories
        foreach ($categoryPreferences as $preferredCategory) {
            if (isset($objectsByCategory[$preferredCategory])) {
                foreach ($objectsByCategory[$preferredCategory] as $object) {
                    if ($object['quantity'] === $brandQty) {
                        $matches[] = $object;
                    }
                }
            }
        }

        // If we found matches in preferred categories, use those
        if (!empty($matches)) {
            return $matches;
        }

        // Second pass: Look for any quantity matches across all categories
        foreach ($objectsByCategory as $categoryKey => $objects) {
            foreach ($objects as $object) {
                if ($object['quantity'] === $brandQty) {
                    $matches[] = $object;
                }
            }
        }

        // If still no matches and only one total object, assume they go together
        if (empty($matches)) {
            $totalObjects = array_sum(array_map('count', $objectsByCategory));
            if ($totalObjects === 1) {
                foreach ($objectsByCategory as $objects) {
                    $matches[] = $objects[0];
                }
            }
        }

        return $matches;
    }

    /**
     * Get category preferences for a brand
     */
    private function getCategoryPreferences(string $brandKey): array
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $brandKey));

        // Common sense category preferences
        $patterns = [
            'softdrinks' => ['coke', 'coca', 'pepsi', 'sprite', 'fanta', '7up', 'drpepper'],
            'alcohol' => ['heineken', 'budweiser', 'corona', 'guinness', 'carlsberg', 'stella'],
            'coffee' => ['starbucks', 'costa', 'nero', 'pret', 'dunkin', 'timhortons'],
            'smoking' => ['marlboro', 'camel', 'winston', 'luckystrike', 'parliament'],
            'food' => ['mcdonalds', 'kfc', 'subway', 'burgerking', 'wendys'],
        ];

        foreach ($patterns as $category => $brandPatterns) {
            foreach ($brandPatterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return [$category];
                }
            }
        }

        // No specific preference - check all categories
        return array_keys($patterns);
    }

    /**
     * Process discovered relationships and create pivots
     */
    private function processDiscoveredRelationships(int $threshold, bool $dryRun): void
    {
        $this->info("Processing discovered relationships...");
        $this->newLine();

        // Sort by occurrence count
        uasort($this->coOccurrences, fn($a, $b) => $b['count'] <=> $a['count']);

        foreach ($this->coOccurrences as $key => $data) {
            if ($data['count'] >= $threshold) {
                if (!$dryRun) {
                    $this->createPivot($data);
                }
                $this->createdPivots[] = $data;
            }
        }
    }

    /**
     * Create a pivot relationship in the database
     */
    private function createPivot(array $data): void
    {
        try {
            // Get IDs for object and brand
            $objectId = DB::table('litter_objects')
                ->where('key', $data['object_key'])
                ->value('id');

            $brandId = DB::table('brandslist')
                ->where('key', $data['brand_key'])
                ->value('id');

            if (!$objectId || !$brandId) {
                Log::warning("Could not find IDs for pivot creation", [
                    'object_key' => $data['object_key'],
                    'brand_key' => $data['brand_key']
                ]);
                return;
            }

            // Create or get CategoryObject
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

            // Create taggable relationship if it doesn't exist
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
            }

        } catch (\Exception $e) {
            Log::error("Failed to create pivot", [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display analysis results
     */
    private function displayResults(bool $dryRun): void
    {
        $this->info("═══════════════════════════════════════════");
        $this->info("Brand-Object Relationship Discovery Results");
        $this->info("═══════════════════════════════════════════");
        $this->newLine();

        // Show created/would-create pivots
        if (!empty($this->createdPivots)) {
            $this->info(($dryRun ? "Would create" : "Created") . " " . count($this->createdPivots) . " pivot relationships:");
            $this->newLine();

            $this->table(
                ['Category', 'Object', 'Brand', 'Occurrences'],
                array_map(fn($p) => [
                    $p['category_key'],
                    $p['object_key'],
                    $p['brand_key'],
                    $p['count']
                ], array_slice($this->createdPivots, 0, 20))
            );

            if (count($this->createdPivots) > 20) {
                $this->info("... and " . (count($this->createdPivots) - 20) . " more");
            }
        }

        // Show ambiguous cases summary
        if (!empty($this->ambiguousCases)) {
            $this->newLine();
            $this->warn("Found " . count($this->ambiguousCases) . " ambiguous cases requiring manual review");

            // Group ambiguous cases by brand
            $byBrand = [];
            foreach ($this->ambiguousCases as $case) {
                $byBrand[$case['brand']][] = $case;
            }

            $this->info("Most ambiguous brands:");
            $sorted = array_slice($byBrand, 0, 10, true);
            foreach ($sorted as $brand => $cases) {
                $this->line("  • {$brand}: " . count($cases) . " ambiguous photos");
            }
        }

        // Save detailed log
        $logFile = storage_path('logs/brand_pivot_discovery_' . date('Y-m-d_His') . '.json');
        file_put_contents($logFile, json_encode([
            'created_pivots' => $this->createdPivots,
            'ambiguous_cases' => $this->ambiguousCases,
            'all_cooccurrences' => $this->coOccurrences
        ], JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Detailed log saved to: {$logFile}");
    }
}
