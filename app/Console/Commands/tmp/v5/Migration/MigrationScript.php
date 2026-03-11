<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Achievements\Achievement;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Models\Users\User;
use App\Tags\TagsConfig;
use App\Services\Achievements\AchievementEngine;
use App\Services\Metrics\MetricsService;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Tags\UpdateTagsService;
use App\Services\Achievements\Tags\TagKeyCache;
use Database\Seeders\{AchievementsSeeder, Tags\GenerateBrandsSeeder, Tags\GenerateTagsSeeder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Log, Redis, Schema};

class MigrationScript extends Command
{
    protected $signature = 'olm:v5
        {--skip-locations : Skip the locations cleanup step}
        {--skip-redis-flush : Skip the Redis FLUSHDB step (useful when resuming a partial migration)}
        {--user= : Specific user ID to migrate}
        {--batch=500 : Number of photos per batch}';

    protected $description = 'Migrate photos to v5 structure';

    private int $processed = 0;
    private int $failed = 0;

    public function __construct(
        private readonly UpdateTagsService $updateTagsService,
        private readonly MetricsService $metricsService,
        private readonly AchievementEngine $achievementEngine
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('Column photos.migrated_at missing. Run migrations first.');
            return self::FAILURE;
        }

        if (! $this->option('skip-locations')) {
            $this->runLocationsMigrationScript();
        }

        $this->ensureProcessingColumns();
        $this->fixInvalidVerificationStatuses();
        $this->seedReferenceTables();
        TagKeyCache::preloadAll();
        DB::disableQueryLog();

        if (! $this->option('skip-redis-flush')) {
            if (! $this->flushRedis()) {
                return self::FAILURE;
            }
        }

        $this->runMigration();

        return self::SUCCESS;
    }

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
        $batchNumber = 0;

        Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->orderBy('id')
            ->chunkById($this->option('batch'), function ($photos) use (
                $userId,
                $photoCount,
                &$processedForUser,
                &$failedForUser,
                &$batchNumber
            ) {
                $batchNumber++;
                $batchStartTime = microtime(true);
                $memoryBefore = memory_get_usage(true);
                $batchSize = $photos->count();
                $successfulPhotos = [];
                $batchFailed = 0;

                foreach ($photos as $photo) {
                    try {
                        // Update tags
                        $this->updateTagsService->updateTags($photo);

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

                $this->info($status);
            });

        // Achievements parked for post-release — skip evaluation during migration
        // $this->evaluateUserAchievements($userId);

        // Display user summary
        $this->displayUserSummary($userId, $processedForUser, $failedForUser);
    }

    private function runLocationsMigrationScript () {
        $this->info("Running location cleanup...");

        $exitCode = Artisan::call('olm:locations:cleanup', [], $this->output);

        if ($exitCode === 0) {
            $this->info("✓ Location cleanup complete");
        } else {
            $this->warn("⚠ Location cleanup exited with code {$exitCode}");
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

    private function displayUserSummary(int $userId, int $processed, int $failed): void
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

    private function ensureProcessingColumns(): void
    {
        $schema = DB::getSchemaBuilder();

        $columns = [
            'processed_at' => 'TIMESTAMP',
            'processed_fp' => 'VARCHAR(32)',
            'processed_tags' => 'TEXT',
            'processed_xp' => 'INT UNSIGNED'
        ];

        foreach ($columns as $column => $type) {
            if (!$schema->hasColumn('photos', $column)) {
                DB::statement("ALTER TABLE photos ADD COLUMN {$column} {$type} NULL");
                $this->info("Added column: photos.{$column}");
            }
        }
    }

    /**
     * Fix photos with invalid verification status (e.g. 999) before migration.
     * Uses raw DB query to bypass the VerificationStatus enum cast.
     */
    private function fixInvalidVerificationStatuses(): void
    {
        // [photoId => [userId, newVerified]]
        $fixes = [
            11641 => [32, 2],
            11645 => [1210, 2],
            11856 => [1210, 2],
            11903 => [1254, 2],
        ];

        $fixed = 0;
        foreach ($fixes as $photoId => [$expectedUserId, $newStatus]) {
            $photo = DB::table('photos')->where('id', $photoId)->first(['verified', 'user_id']);
            if (!$photo) {
                $this->warn("⚠ Photo {$photoId} not found, skipping");
                continue;
            }
            if ((int) $photo->user_id !== $expectedUserId) {
                $this->warn("⚠ Photo {$photoId} belongs to user {$photo->user_id}, expected {$expectedUserId}, skipping");
                continue;
            }
            if ((int) $photo->verified !== $newStatus) {
                DB::table('photos')->where('id', $photoId)->update(['verified' => $newStatus]);
                $this->info("✓ Photo {$photoId}: verified {$photo->verified} → {$newStatus}");
                $fixed++;
            }
        }

        if ($fixed > 0) {
            $this->info("✓ Fixed {$fixed} invalid verification statuses");
        }
    }

    private function seedReferenceTables(): void
    {
        $this->info("Seeding reference tables...");

        // Seed tags
        if (Category::count() == 0) {
            $seeder = new GenerateTagsSeeder();
            $seeder->run();
            $this->info("✓ Tags seeded");
        } else {
            $this->info("✓ Tags already exist");
        }

        // Always ensure all materials exist (including legacy v4 materials)
        $materialsCreated = 0;
        foreach (TagsConfig::allMaterialKeys() as $key) {
            $material = Materials::firstOrCreate(['key' => $key]);
            if ($material->wasRecentlyCreated) {
                $materialsCreated++;
            }
        }
        if ($materialsCreated > 0) {
            $this->info("✓ Created {$materialsCreated} missing materials");
        }

        if (BrandList::count() == 0) {
            $brandSeeder = new GenerateBrandsSeeder();
            $brandSeeder->run();
            $this->info("✓ Brands seeded");
        } else {
            $this->info("✓ Brands already exist");
        }

        if (Achievement::count() == 0) {
            // Seed achievements
            $achievementSeeder = new AchievementsSeeder();
            $achievementSeeder->run();
            $this->info("✓ Achievements seeded");
        }

        $this->newLine();
    }

    /**
     * Flush all Redis data before migration.
     *
     * The migration rebuilds Redis via MetricsService → RedisMetricsCollector per photo.
     * Old keys combined with incremental ops would produce wrong totals, so we nuke everything first.
     */
    private function flushRedis(): bool
    {
        $this->warn('The migration will flush ALL Redis data before processing.');
        $this->warn('MetricsService will rebuild Redis as each photo is processed.');

        if (! $this->confirm('This will delete ALL Redis data. Continue?')) {
            $this->info('Aborted. Use --skip-redis-flush to skip this step.');
            return false;
        }

        Redis::command('FLUSHDB');

        $this->info('Redis FLUSHDB complete — all keys cleared.');
        Log::info('olm:v5 migration: Redis FLUSHDB executed before migration run.');
        $this->newLine();

        return true;
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
