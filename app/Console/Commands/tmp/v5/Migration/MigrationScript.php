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
use Illuminate\Support\Facades\{DB, Log};

class MigrationScript extends Command
{
    // Todo - deprecate remaining and introduced picked_up (nullable)

    protected $signature = 'olm:v5
        {--batch=500 : Number of photos to process per chunk}
        {--user= : Process only this user ID}';

    protected $description = 'Upgrade OpenLitterMap data to v5';

    private int $processed = 0;
    private int $failed = 0;
    private int $totalUsers = 0;
    private int $currentUserPhotos = 0;
    private float $globalStartTime = 0;

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

        // Disable query log for performance
        DB::disableQueryLog();

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
                return self::SUCCESS;
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
                return self::SUCCESS;
            }
        }

        $this->totalUsers = $userIds->count();
        $this->info("Found {$this->totalUsers} user(s) to migrate");
        $this->info("Processing batch size: {$this->option('batch')} photos");

        $this->globalStartTime = microtime(true);

        foreach ($userIds as $index => $userId) {
            $this->newLine();

            if (!$specificUserId && $index > 0) {
                $elapsed = microtime(true) - $this->globalStartTime;
                $avgTimePerUser = $elapsed / $index;
                $remainingUsers = $this->totalUsers - $index;
                $eta = round($avgTimePerUser * $remainingUsers);
                $etaFormatted = $this->formatDuration($eta);

                $this->info("[User " . ($index + 1) . "/{$this->totalUsers}] Processing user #{$userId} (ETA: {$etaFormatted})");
            } else {
                $userLabel = $specificUserId ? "Processing user #{$userId}" : "[User " . ($index + 1) . "/{$this->totalUsers}] Processing user #{$userId}";
                $this->info($userLabel);
            }

            $this->migrateSingleUser($userId);
            gc_collect_cycles();
        }

        $this->newLine(2);
        $this->displaySummary();

        return self::SUCCESS;
    }

    private function ensureProcessingColumns(): void
    {
        // Check for all required columns
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

    private function migrateSingleUser(int $userId): void
    {
        $user = User::find($userId);
        $name = $user?->name ?? "User {$userId}";

        $this->currentUserPhotos = Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->count();

        if ($this->currentUserPhotos === 0) {
            $this->info("  → No photos to migrate");
            return;
        }

        $this->info("  → {$name}: {$this->currentUserPhotos} photos to migrate");

        $processedForUser = 0;
        $failedForUser = 0;
        $startTime = microtime(true);
        $totalBatchTime = 0;
        $batchNumber = 0;

        Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->orderBy('id')
            ->chunkById($this->option('batch'), function ($photos) use ($userId, &$processedForUser, &$failedForUser, &$batchNumber, &$totalBatchTime) {
                $batchNumber++;
                $batchStartTime = microtime(true);
                $memoryBefore = memory_get_usage(true);
                $batchSize = $photos->count();
                $successfulPhotos = [];
                $batchFailed = 0;

                foreach ($photos as $photo) {
                    try {
                        // Step 1: Update tags and generate summary (creates photo.summary and photo.xp)
                        $this->updateTagsService->updateTags($photo);

                        // Reload photo to get the updated summary and xp
                        $photo->refresh();

                        // Step 2: Process metrics (MySQL + Redis in one go)
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
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                // Mark photos as migrated
                if (!empty($successfulPhotos)) {
                    Photo::whereIn('id', $successfulPhotos)->update(['migrated_at' => now()]);
                }

                // Calculate batch metrics
                $batchDuration = round(microtime(true) - $batchStartTime, 2);
                $totalBatchTime += $batchDuration;
                $memoryAfter = memory_get_usage(true);
                $memoryDelta = round(($memoryAfter - $memoryBefore) / 1024 / 1024, 1);
                $currentMemory = round($memoryAfter / 1024 / 1024, 1);
                $photosPerSecond = $batchDuration > 0 ? round($batchSize / $batchDuration, 1) : 0;
                $percent = round(($processedForUser / $this->currentUserPhotos) * 100);

                $status = sprintf(
                    "    Batch %d: %d/%d photos (%d%%) | Time: %ss | Speed: %s/s | Memory: %sMB %s",
                    $batchNumber,
                    $processedForUser,
                    $this->currentUserPhotos,
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

                if ($currentMemory > 1024) {
                    $this->warn("⚠️ High memory usage: {$currentMemory}MB");
                }
            });

        $this->evaluateUserAchievements($userId);
        $this->displayUserSummary($userId, $processedForUser, $failedForUser, $batchNumber, $totalBatchTime);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("✓ Migration completed in {$duration}s");
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

    private function displayUserSummary(int $userId, int $processed, int $failed, int $totalBatches, float $totalBatchTime): void
    {
        $this->info("");
        $this->info("    Summary for User #{$userId}:");
        $this->info("    ────────────────────────");

        $this->info("    ✅ Photos migrated: " . number_format($processed));
        if ($failed > 0) {
            $this->error("    ❌ Photos failed: " . number_format($failed));
        }

        $avgBatchTime = $totalBatches > 0 ? round($totalBatchTime / $totalBatches, 2) : 0;
        $avgPhotosPerSecond = $totalBatchTime > 0 ? round($processed / $totalBatchTime, 1) : 0;
        $this->info("    ⚡ Total batches: {$totalBatches}");
        $this->info("    ⏱️  Avg batch time: {$avgBatchTime}s");
        $this->info("    🚀 Avg speed: {$avgPhotosPerSecond} photos/s");

        // Get metrics from Redis
        try {
            $metrics = RedisMetricsCollector::getUserMetrics($userId);

            $this->info("    📊 Total uploads: " . number_format($metrics['uploads']));
            $this->info("    ⚡ Total XP: " . number_format($metrics['xp']));
            $this->info("    🔥 Current streak: " . number_format($metrics['streak']) . " days");
            $this->info("    📦 Total litter items: " . number_format($metrics['litter']));

            // Top categories (if available)
            if (!empty($metrics['categories'])) {
                $topCats = array_slice($metrics['categories'], 0, 3, true);
                $catStrings = [];
                foreach ($topCats as $id => $count) {
                    $catStrings[] = "#{$id} (" . number_format($count) . ")";
                }
                if (!empty($catStrings)) {
                    $this->info("    🏷️  Top categories: " . implode(', ', $catStrings));
                }
            }

        } catch (\Throwable $e) {
            Log::warning("Could not fetch Redis stats for user {$userId}", [
                'error' => $e->getMessage()
            ]);
        }

        $remainingPhotos = Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->count();

        if ($remainingPhotos === 0) {
            $this->info("    ✓ User fully migrated!");
        } else {
            $this->warn("    ⚠️  {$remainingPhotos} photos still pending migration");
        }

        $this->info("");
    }

    private function seedReferenceTables(): void
    {
        $this->info('Seeding reference tables...');
        $this->callSilent('db:seed', ['--class' => GenerateTagsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => AchievementsSeeder::class]);
    }

    private function displaySummary(): void
    {
        $totalElapsed = round(microtime(true) - $this->globalStartTime, 2);
        $totalElapsedFormatted = $this->formatDuration((int)$totalElapsed);

        $this->info('Migration Summary');
        $this->info('═════════════════');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Users processed', number_format($this->totalUsers)],
                ['Photos processed', number_format($this->processed)],
                ['Failed photos', number_format($this->failed) . ($this->failed > 0 ? ' ❌' : ' ✅')],
                ['Total time', $totalElapsedFormatted],
                ['Average speed', $totalElapsed > 0 ? round($this->processed / $totalElapsed, 1) . ' photos/s' : 'N/A'],
                ['Peak memory', round(memory_get_peak_usage(true) / 1024 / 1024, 1) . ' MB'],
            ]
        );

        // Show failed photo IDs if any
        if ($this->failed > 0) {
            $this->warn("\nCheck logs for details on failed photos.");
        }
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
