<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Tags\UpdateTagsService;
use App\Services\Achievements\Tags\TagKeyCache;
use Database\Seeders\{AchievementsSeeder, Tags\GenerateBrandsSeeder, Tags\GenerateTagsSeeder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Log};

class MigrationScript extends Command
{
    protected $signature = 'olm:v5
        {--user= : Specific user ID to migrate}
        {--batch=500 : Number of photos per batch}
        {--skip-pivots : Skip brand pivot discovery phase}';

    protected $description = 'Smart migration that discovers brand-object relationships first';

    private int $processed = 0;
    private int $failed = 0;
    private int $brandsAttached = 0;
    private int $brandsSkipped = 0;
    private array $unmatchedBrands = [];

    public function __construct(
        private readonly UpdateTagsService $updateTagsService,
        private readonly MetricsService $metricsService,
        private readonly AchievementEngine $achievementEngine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Check for required columns
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('Column photos.migrated_at missing. Run migrations first.');
            return self::FAILURE;
        }

        $this->ensureProcessingColumns();
        $this->seedReferenceTables();
        TagKeyCache::preloadAll();
        DB::disableQueryLog();

        // Step 1: Discover and create brand-object pivots (unless skipped)
        if (!$this->option('skip-pivots')) {
            $this->discoverAndCreatePivots();
        } else {
            $this->info("Skipping pivot discovery (--skip-pivots flag used)");
        }

        // Step 2: Run migration with improved brand handling
        $this->runMigration();

        // Step 3: Report unmatched brands
        $this->reportUnmatchedBrands();

        return self::SUCCESS;
    }

    /**
     * Run pivot discovery before migration
     */
    private function discoverAndCreatePivots(): void
    {
        $this->info("═══════════════════════════════════════");
        $this->info("Phase 1: Discovering Brand Relationships");
        $this->info("═══════════════════════════════════════");
        $this->newLine();

        // Count photos with brands
        $photosWithBrands = DB::table('photos')->whereNotNull('brands_id')->count();
        $this->info("Found {$photosWithBrands} photos with brands to analyze");

        // Run the comprehensive brand pivot discovery
        $exitCode = Artisan::call('olm:v5:process-all-brands', [
            '--batch' => 5000  // Process in batches for memory efficiency
        ]);

        // Get the output
        $output = Artisan::output();
        if (!empty($output)) {
            $this->line($output);
        }

        if ($exitCode !== 0) {
            $this->warn("Pivot discovery encountered issues but continuing...");
        }

        // Report final pivot count
        $pivotCount = DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->count();

        $this->info("✓ Created {$pivotCount} brand-object pivot relationships");
        $this->newLine(2);
    }

    /**
     * Run the actual migration
     */
    private function runMigration(): void
    {
        $this->info("═══════════════════════════════");
        $this->info("Phase 2: Running Migration");
        $this->info("═══════════════════════════════");
        $this->newLine();

        $memoryLimit = ini_get('memory_limit');
        $this->info("Memory limit: {$memoryLimit}");

        $specificUserId = $this->option('user');

        if ($specificUserId) {
            $userIds = collect([(int)$specificUserId]);
            $photoCount = DB::table('photos')
                ->where('user_id', $specificUserId)
                ->whereNull('migrated_at')
                ->count();

            if ($photoCount === 0) {
                $this->info("User #{$specificUserId} has no photos to migrate.");
                return;
            }

            $this->info("Processing single user #{$specificUserId} with {$photoCount} photos");
        } else {
            $userIds = DB::table('photos')
                ->whereNull('migrated_at')
                ->distinct()
                ->pluck('user_id')
                ->sort()
                ->values();

            if ($userIds->isEmpty()) {
                $this->info('Nothing to migrate.');
                return;
            }
        }

        $totalUsers = $userIds->count();
        $this->info("Found {$totalUsers} user(s) to migrate");
        $this->info("Processing batch size: {$this->option('batch')} photos");
        $this->newLine();

        $globalStartTime = microtime(true);

        foreach ($userIds as $index => $userId) {
            if (!$specificUserId && $index > 0) {
                $this->newLine();
                $elapsed = microtime(true) - $globalStartTime;
                $avgTimePerUser = $elapsed / $index;
                $remainingUsers = $totalUsers - $index;
                $eta = round($avgTimePerUser * $remainingUsers);
                $etaFormatted = $this->formatDuration($eta);

                $this->info("[User " . ($index + 1) . "/{$totalUsers}] Processing user #{$userId} (ETA: {$etaFormatted})");
            } else {
                $userLabel = $specificUserId ? "Processing user #{$userId}" : "[User " . ($index + 1) . "/{$totalUsers}] Processing user #{$userId}";
                $this->info($userLabel);
            }

            $this->migrateSingleUser($userId);
            gc_collect_cycles();
        }

        $this->newLine(2);
        $this->displaySummary($globalStartTime);
    }

    private function migrateSingleUser(int $userId): void
    {
        $user = User::find($userId);
        $name = $user?->name ?? "User {$userId}";

        $photoCount = Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->count();

        if ($photoCount === 0) {
            $this->info("  → No photos to migrate");
            return;
        }

        $this->info("  → {$name}: {$photoCount} photos to migrate");

        $processedForUser = 0;
        $failedForUser = 0;
        $brandsAttachedForUser = 0;
        $brandsSkippedForUser = 0;
        $batchNumber = 0;

        Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->orderBy('id')
            ->chunkById($this->option('batch'), function ($photos) use (
                $userId,
                $photoCount,
                &$processedForUser,
                &$failedForUser,
                &$brandsAttachedForUser,
                &$brandsSkippedForUser,
                &$batchNumber
            ) {
                $batchNumber++;
                $batchStartTime = microtime(true);
                $memoryBefore = memory_get_usage(true);
                $batchSize = $photos->count();
                $successfulPhotos = [];
                $batchFailed = 0;
                $batchBrandsAttached = 0;
                $batchBrandsSkipped = 0;

                foreach ($photos as $photo) {
                    try {
                        // Track brand attachment stats
                        $statsBeforeUpdate = $this->getBrandStats($photo);

                        // Update tags
                        $this->updateTagsService->updateTags($photo);

                        // Check brand attachment after update
                        $statsAfterUpdate = $this->getBrandStats($photo);

                        if ($statsBeforeUpdate['expected'] > 0) {
                            if ($statsAfterUpdate['actual'] > 0) {
                                $batchBrandsAttached += $statsAfterUpdate['actual'];
                                $brandsAttachedForUser += $statsAfterUpdate['actual'];
                                $this->brandsAttached += $statsAfterUpdate['actual'];
                            } else {
                                $batchBrandsSkipped += $statsBeforeUpdate['expected'];
                                $brandsSkippedForUser += $statsBeforeUpdate['expected'];
                                $this->brandsSkipped += $statsBeforeUpdate['expected'];

                                // Track unmatched brands
                                $this->trackUnmatchedBrands($photo, $statsBeforeUpdate);
                            }
                        }

                        // Reload and process metrics
                        $photo->refresh();
                        $this->metricsService->processPhoto($photo);

                        $successfulPhotos[] = $photo->id;
                        $processedForUser++;
                        $this->processed++;

                    } catch (\Throwable $e) {
                        $failedForUser++;
                        $this->failed++;
                        $batchFailed++;
                        Log::error("Migration failed for photo {$photo->id}", [
                            'user_id' => $userId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Mark photos as migrated
                if (!empty($successfulPhotos)) {
                    Photo::whereIn('id', $successfulPhotos)->update(['migrated_at' => now()]);
                }

                // Display batch stats
                $batchDuration = round(microtime(true) - $batchStartTime, 2);
                $memoryAfter = memory_get_usage(true);
                $memoryDelta = round(($memoryAfter - $memoryBefore) / 1024 / 1024, 1);
                $currentMemory = round($memoryAfter / 1024 / 1024, 1);
                $photosPerSecond = $batchDuration > 0 ? round($batchSize / $batchDuration, 1) : 0;
                $percent = round(($processedForUser / $photoCount) * 100);

                $status = sprintf(
                    "    Batch %d: %d/%d photos (%d%%) | Time: %ss | Speed: %s/s | Memory: %sMB %s",
                    $batchNumber,
                    $processedForUser,
                    $photoCount,
                    $percent,
                    $batchDuration,
                    $photosPerSecond,
                    $currentMemory,
                    $memoryDelta >= 0 ? "(+{$memoryDelta}MB)" : "({$memoryDelta}MB)"
                );

                if ($batchFailed > 0) {
                    $status .= " | Failed: {$batchFailed}";
                }

                if ($batchBrandsAttached > 0 || $batchBrandsSkipped > 0) {
                    $status .= sprintf(" | Brands: %d✓/%d✗", $batchBrandsAttached, $batchBrandsSkipped);
                }

                $this->info($status);
            });

        // Evaluate achievements
        $this->evaluateUserAchievements($userId);

        // Display user summary
        $this->displayUserSummary($userId, $processedForUser, $failedForUser, $brandsAttachedForUser, $brandsSkippedForUser);
    }

    /**
     * Get brand statistics for a photo
     */
    private function getBrandStats(Photo $photo): array
    {
        $tags = $photo->tags();
        $expected = 0;
        $brandKeys = [];

        if (isset($tags['brands'])) {
            foreach ($tags['brands'] as $brandKey => $qty) {
                $expected += (int) $qty;
                $brandKeys[] = $brandKey;
            }
        }

        $actual = 0;
        if ($photo->photoTags) {
            $actual = DB::table('photo_tag_extra_tags')
                ->whereIn('photo_tag_id', $photo->photoTags->pluck('id'))
                ->where('tag_type', 'brand')
                ->sum('quantity');
        }

        return [
            'expected' => $expected,
            'actual' => $actual,
            'brand_keys' => $brandKeys
        ];
    }

    /**
     * Track unmatched brands for reporting
     */
    private function trackUnmatchedBrands(Photo $photo, array $stats): void
    {
        foreach ($stats['brand_keys'] as $brandKey) {
            if (!isset($this->unmatchedBrands[$brandKey])) {
                $this->unmatchedBrands[$brandKey] = [
                    'count' => 0,
                    'photo_ids' => []
                ];
            }
            $this->unmatchedBrands[$brandKey]['count']++;
            $this->unmatchedBrands[$brandKey]['photo_ids'][] = $photo->id;
        }
    }

    private function evaluateUserAchievements(int $userId): void
    {
        try {
            $startTime = microtime(true);
            $unlocked = $this->achievementEngine->evaluate($userId);
            $duration = round(microtime(true) - $startTime, 3);

            if ($unlocked->isNotEmpty()) {
                $this->info("    🏆 Unlocked {$unlocked->count()} achievements in {$duration}s");
            }
        } catch (\Throwable $e) {
            Log::error("Achievement evaluation failed for user {$userId}", [
                'error' => $e->getMessage()
            ]);
            $this->warn("    ⚠️  Achievement evaluation failed");
        }
    }

    private function displayUserSummary(int $userId, int $processed, int $failed, int $brandsAttached, int $brandsSkipped): void
    {
        $this->newLine();
        $this->info("    Summary for User #{$userId}:");
        $this->info("    ────────────────────────");

        $this->info("    ✅ Photos migrated: " . number_format($processed));
        if ($failed > 0) {
            $this->error("    ❌ Photos failed: " . number_format($failed));
        }

        if ($brandsAttached > 0 || $brandsSkipped > 0) {
            $this->info("    🏷️  Brands attached: " . number_format($brandsAttached));
            if ($brandsSkipped > 0) {
                $this->warn("    ⚠️  Brands skipped: " . number_format($brandsSkipped));
            }
        }

        // Get metrics from Redis
        try {
            $metrics = RedisMetricsCollector::getUserMetrics($userId);
            $this->info("    📊 Total uploads: " . number_format($metrics['uploads']));
            $this->info("    ⚡ Total XP: " . number_format($metrics['xp']));
            $this->info("    📦 Total litter items: " . number_format($metrics['litter']));
        } catch (\Throwable $e) {
            Log::warning("Could not fetch Redis stats for user {$userId}");
        }

        $this->newLine();
    }

    private function displaySummary(float $globalStartTime): void
    {
        $totalElapsed = round(microtime(true) - $globalStartTime, 2);
        $totalElapsedFormatted = $this->formatDuration((int)$totalElapsed);

        $this->info('Migration Summary');
        $this->info('═════════════════');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Photos processed', number_format($this->processed)],
                ['Failed photos', number_format($this->failed) . ($this->failed > 0 ? ' ❌' : ' ✅')],
                ['Brands attached', number_format($this->brandsAttached) . ' ✓'],
                ['Brands skipped', number_format($this->brandsSkipped) . ($this->brandsSkipped > 0 ? ' ⚠️' : '')],
                ['Total time', $totalElapsedFormatted],
                ['Average speed', $totalElapsed > 0 ? round($this->processed / $totalElapsed, 1) . ' photos/s' : 'N/A'],
                ['Peak memory', round(memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MB'],
            ]
        );
    }

    private function reportUnmatchedBrands(): void
    {
        if (empty($this->unmatchedBrands)) {
            return;
        }

        $this->newLine();
        $this->warn("═════════════════════════════════════");
        $this->warn("Unmatched Brands Requiring Attention");
        $this->warn("═════════════════════════════════════");
        $this->newLine();

        // Sort by occurrence count
        uasort($this->unmatchedBrands, fn($a, $b) => $b['count'] <=> $a['count']);

        // Show top 10 unmatched brands
        $topBrands = array_slice($this->unmatchedBrands, 0, 10, true);

        $this->table(
            ['Brand', 'Occurrences', 'Sample Photo IDs'],
            array_map(fn($key, $data) => [
                $key,
                $data['count'],
                implode(', ', array_slice($data['photo_ids'], 0, 3)) . (count($data['photo_ids']) > 3 ? '...' : '')
            ], array_keys($topBrands), $topBrands)
        );

        if (count($this->unmatchedBrands) > 10) {
            $this->info("... and " . (count($this->unmatchedBrands) - 10) . " more brands");
        }

        // Save detailed report
        $logFile = storage_path('logs/unmatched_brands_' . date('Y-m-d_His') . '.json');
        file_put_contents($logFile, json_encode($this->unmatchedBrands, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Detailed unmatched brands report saved to:");
        $this->line($logFile);

        $this->newLine();
        $this->comment("To fix unmatched brands:");
        $this->comment("1. Review the log file for patterns");
        $this->comment("2. Create pivot relationships for logical brand-object pairs");
        $this->comment("3. Re-run migration for affected photos");
    }

    private function ensureProcessingColumns(): void
    {
        $requiredColumns = [
            'processed_at' => 'TIMESTAMP NULL',
            'processed_fp' => 'CHAR(16) NULL',
            'processed_tags' => 'TEXT NULL',
            'processed_xp' => 'INT NULL'
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!DB::getSchemaBuilder()->hasColumn('photos', $column)) {
                DB::statement("ALTER TABLE photos ADD COLUMN {$column} {$definition}");
                $this->info("Added column: {$column}");
            }
        }
    }

    private function seedReferenceTables(): void
    {
        $this->info('Seeding reference tables...');
        $this->callSilent('db:seed', ['--class' => GenerateTagsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => AchievementsSeeder::class]);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs = $seconds % 60;
            return "{$minutes}m {$secs}s";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
}
