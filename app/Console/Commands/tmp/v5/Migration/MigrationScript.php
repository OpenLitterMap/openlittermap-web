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
        {--batch=500 : Number of photos per batch}';

    protected $description = 'Migrate photos to v5 structure using pre-existing brand-object relationships';

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
        $this->reportBrandRelationshipStatus();
        TagKeyCache::preloadAll();
        DB::disableQueryLog();

        // Run migration using existing brand relationships
        $this->runMigration();

        // Report unmatched brands
        $this->reportUnmatchedBrands();

        return self::SUCCESS;
    }

    /**
     * Report status of brand relationships
     */
    private function reportBrandRelationshipStatus(): void
    {
        $this->info("═══════════════════════════════════════");
        $this->info("Brand Relationship Status");
        $this->info("═══════════════════════════════════════");

        // Count total brands
        $totalBrands = DB::table('brandslist')->count();

        // Count brands with relationships
        $brandsWithRelationships = DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->distinct()
            ->count('taggable_id');

        // Count total relationships
        $totalRelationships = DB::table('taggables')
            ->where('taggable_type', 'App\\Models\\Litter\\Tags\\BrandList')
            ->count();

        // Count photos with brands
        $photosWithBrands = DB::table('photos')
            ->whereNotNull('brands_id')
            ->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total brands in system', number_format($totalBrands)],
                ['Brands with relationships', number_format($brandsWithRelationships)],
                ['Brands without relationships', number_format($totalBrands - $brandsWithRelationships)],
                ['Total brand-object relationships', number_format($totalRelationships)],
                ['Photos with brands to migrate', number_format($photosWithBrands)],
            ]
        );

        if ($brandsWithRelationships === 0) {
            $this->warn("");
            $this->warn("⚠️  No brand relationships found!");
            $this->warn("Run the following commands first:");
            $this->warn("  php artisan olm:define-brand-relationships");
            $this->warn("  php artisan olm:auto-create-brand-relationships --apply");
            $this->warn("");

            if (!$this->confirm('Continue without brand relationships?')) {
                return;
            }
        }

        $this->newLine();
    }

    /**
     * Run the migration
     */
    private function runMigration(): void
    {
        $this->info("═══════════════════════════════");
        $this->info("Running Migration");
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
            ->orderBy('created_at')
            ->chunk($this->option('batch'), function ($photos) use (
                $userId,
                &$processedForUser,
                &$failedForUser,
                &$batchNumber,
                &$brandsAttachedForUser,
                &$brandsSkippedForUser
            ) {
                $batchNumber++;
                $startTime = microtime(true);
                $batchSize = $photos->count();

                $this->info("    Batch {$batchNumber} ({$batchSize} photos)...");

                foreach ($photos as $photo) {
                    $hasData = false;

                    // Check for official category tags
                    $categories = ['smoking', 'food', 'coffee', 'alcohol', 'softdrinks',
                        'sanitary', 'other', 'coastal', 'dumping', 'industrial'];
                    foreach ($categories as $category) {
                        $idColumn = "{$category}_id";
                        if ($photo->$idColumn > 0) {
                            $hasData = true;
                            break;
                        }
                    }

                    // Check for brand data
                    if (!$hasData && $photo->brands_id > 0) {
                        $hasData = true;
                    }

                    // Check for custom tags
                    if (!$hasData) {
                        $hasCustomTags = DB::table('custom_tags')
                            ->where('photo_id', $photo->id)
                            ->exists();
                        if ($hasCustomTags) {
                            $hasData = true;
                        }
                    }

                    if (!$hasData) {
                        $this->markAsEmpty($photo);
                        $processedForUser++;
                        $this->processed++;
                        continue;
                    }

                    DB::beginTransaction();
                    try {
                        // Process the photo tags
                        $this->updateTagsService->updateTags($photo);

                        DB::update('UPDATE photos SET migrated_at = NOW() WHERE id = ?', [$photo->id]);
                        DB::commit();

                        $processedForUser++;
                        $this->processed++;
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::error("Migration failed for photo {$photo->id}", [
                            'user_id' => $userId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $failedForUser++;
                        $this->failed++;
                    }
                }

                $elapsed = round(microtime(true) - $startTime, 2);
                $rate = $elapsed > 0 ? round($batchSize / $elapsed, 1) : 0;
                $this->info("      ✓ Processed in {$elapsed}s ({$rate} photos/s)");
            });

        // After all photos for user processed - evaluate achievements
        $this->evaluateUserAchievements($userId);

        // Display user summary
        $this->displayUserSummary($userId, $processedForUser, $failedForUser,
            $brandsAttachedForUser, $brandsSkippedForUser);
    }

    private function markAsEmpty(Photo $photo): void
    {
        DB::update('UPDATE photos SET migrated_at = NOW() WHERE id = ?', [$photo->id]);
    }

    private function trackUnmatchedBrand(string $brandKey, Photo $photo): void
    {
        if (!isset($this->unmatchedBrands[$brandKey])) {
            $this->unmatchedBrands[$brandKey] = [
                'count' => 0,
                'photo_ids' => []
            ];
        }
        $this->unmatchedBrands[$brandKey]['count']++;
        $this->unmatchedBrands[$brandKey]['photo_ids'][] = $photo->id;
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

    private function displayUserSummary(int $userId, int $processed, int $failed, int $brandsAttached = 0, int $brandsSkipped = 0): void
    {
        $this->newLine();
        $this->info("    Summary for User #{$userId}:");
        $this->info("    ────────────────────────");

        $this->info("    ✅ Photos migrated: " . number_format($processed));
        if ($failed > 0) {
            $this->error("    ❌ Photos failed: " . number_format($failed));
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
                implode(', ', array_slice($data['photo_ids'], 0, 5))
            ], array_keys($topBrands), $topBrands)
        );

        $totalUnmatched = count($this->unmatchedBrands);
        if ($totalUnmatched > 10) {
            $this->info("... and " . ($totalUnmatched - 10) . " more brands");
        }

        $this->newLine();
        $this->info("These brands did not have relationships defined in the taggables table.");
        $this->info("They were created as brands-only PhotoTags to preserve the data.");
    }

    private function ensureProcessingColumns(): void
    {
        $schema = DB::getSchemaBuilder();

        $columns = [
            'processed_at' => 'TIMESTAMP',
            'processed_fp' => 'VARCHAR(32)',
            'processed_tags' => 'TEXT',
            'processed_xp' => 'TINYINT(1)'
        ];

        foreach ($columns as $column => $type) {
            if (!$schema->hasColumn('photos', $column)) {
                DB::statement("ALTER TABLE photos ADD COLUMN {$column} {$type} NULL");
                $this->info("Added column: photos.{$column}");
            }
        }
    }

    private function seedReferenceTables(): void
    {
        $this->info("Seeding reference tables...");

        // Seed tags
        $seeder = new GenerateTagsSeeder();
        $seeder->run();
        $this->info("✓ Tags seeded");

        // Seed brands
        $brandSeeder = new GenerateBrandsSeeder();
        $brandSeeder->run();
        $this->info("✓ Brands seeded");

        // Seed achievements
        $achievementSeeder = new AchievementsSeeder();
        $achievementSeeder->run();
        $this->info("✓ Achievements seeded");

        $this->newLine();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%dm %ds', $minutes, $remainingSeconds);
    }
}
