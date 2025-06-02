<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\RedisMetricsCollector;
use App\Services\Tags\UpdateTagsService;
use App\Services\Timeseries\TimeSeriesService;
use App\Services\Achievements\Tags\TagKeyCache;
use Database\Seeders\{AchievementsSeeder, Tags\GenerateBrandsSeeder, Tags\GenerateTagsSeeder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Log, Cache};

/**
 * Remember to install redis-stack on production
 */
class MigrationScript extends Command
{
    protected $signature = <<<SIG
        olm:v5
        {--batch=500        : Number of photos to stream per mini-batch}
        {--minUser=1        : First user_id (inclusive) – use to shard workers}
    SIG;

    protected $description = 'Upgrade OpenLitterMap data to v5';

    public function __construct(
        private readonly UpdateTagsService   $updateTagsService,
        private readonly TimeSeriesService   $timeSeriesService,
        private readonly AchievementEngine   $achievementEngine
    ) {
        parent::__construct();
    }

    private int $processed = 0;
    private int $failed    = 0;

    public function handle(): int
    {
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('🛑  Column photos.migrated_at missing – run the DB migration first.');
            return self::FAILURE;
        }

        $this->seedReferenceTables();

        // Pre-load tag cache for performance
        $this->info('Pre-loading tag cache...');
        TagKeyCache::preloadAll();

        // Pre-warm achievement definitions
        $this->info('Pre-warming achievement definitions...');
        Cache::remember('achievements.definitions.v2', 86400, function () {
            return DB::table('achievements')
                ->select('id', 'type', 'tag_id', 'threshold', 'metadata')
                ->orderBy('type')
                ->orderBy('tag_id')
                ->orderBy('threshold')
                ->get();
        });

        // FIX 1: Count users separately from cursor creation
        $userCount = DB::table('photos')
            ->whereNull('migrated_at')
            ->where('user_id', '>=', $this->option('minUser'))
            ->distinct()
            ->count('user_id');

        if ($userCount === 0) {
            $this->info('Nothing to migrate.');
            return self::SUCCESS;
        }

        // Create cursor AFTER counting
        $userCursor = DB::table('photos')
            ->whereNull('migrated_at')
            ->where('user_id', '>=', $this->option('minUser'))
            ->selectRaw('DISTINCT user_id')
            ->orderBy('user_id')
            ->lazy();

        $globalBar = $this->output->createProgressBar($userCount);
        $globalBar->setFormat('%current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  ETA:%estimated:-6s%');
        $globalBar->start();

        foreach ($userCursor as $row) {
            $this->migrateSingleUser((int) $row->user_id);
            $globalBar->advance();
        }

        // FIX 3: Remove extra advance() that causes off-by-one
        // $globalBar->advance(); // REMOVED
        $globalBar->finish();
        $this->newLine(2);
        $this->displaySummary();

        return self::SUCCESS;
    }

    private function migrateSingleUser(int $userId): void
    {
        $this->info("Migrating user {$userId}...");
        $user        = User::find($userId);
        $name        = $user?->name ?? "User {$userId}";
        $totalPhotos = Photo::where('user_id', $userId)->count();
        $remaining   = Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->count();

        if ($remaining === 0) {
            $this->info("➡  {$name}  –  No photos to migrate");
            return;
        }

        $this->info("➡  {$name}  –  {$remaining}/{$totalPhotos} photos to migrate");
        $userBar = $this->output->createProgressBar($remaining);
        $userBar->setFormat("   %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%");
        $userBar->start();

        // Track changed dimensions for this user (for future use)
        $userChangedDimensions = [];

        // FIX 2: Use chunkById instead of lazyById()->chunk()
        Photo::where('user_id', $userId)
            ->lockForUpdate()
            ->skipLocked()
            ->whereNull('migrated_at')
            ->with(['customTags'])
            ->orderBy('id')
            ->chunkById($this->option('batch'), function ($photos) use ($userId, $userBar, &$userChangedDimensions) {
                // Transform each photo's data
                foreach ($photos as $photo) {
                    try {
                        $this->updateTagsService->updateTags($photo);
                        $this->timeSeriesService->updateTimeSeries($photo);
                        $this->processed++;
                    } catch (\Throwable $e) {
                        $this->failed++;
                        Log::error("Migration failed @photo {$photo->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Update Redis & mark as migrated
                try {
                    // Use tracking version to capture changes (for future use)
                    $changes = RedisMetricsCollector::queueBatchWithTracking($userId, $photos);

                    // Accumulate changed dimensions (for future optimization)
                    if (!empty($changes['changed_dimensions'])) {
                        $userChangedDimensions = array_unique(array_merge(
                            $userChangedDimensions,
                            $changes['changed_dimensions']
                        ));
                    }

                    // Mark photos as migrated
                    Photo::whereIn('id', $photos->pluck('id'))->update(['migrated_at' => now()]);
                } catch (\Throwable $e) {
                    $this->error("Write-phase failure for user {$userId}. Check logs for more info.");
                    $this->failed += $photos->count();
                    Log::critical("Write-phase failure for user {$userId}", [
                        'error' => $e->getMessage(),
                    ]);
                }

                $userBar->advance($photos->count());
            });

        $userBar->finish();
        $this->newLine();

        // Evaluate achievements for the user
        try {
            $startTime = microtime(true);
            $unlocked = $this->achievementEngine->evaluate($userId);
            $duration = round(microtime(true) - $startTime, 3);

            if ($unlocked->isNotEmpty()) {
                $this->info("  🏆 Unlocked {$unlocked->count()} achievements in {$duration}s");
            }

            // Log if evaluation was slow
            if ($duration > 1.0) {
                Log::warning("Slow achievement evaluation", [
                    'user_id' => $userId,
                    'duration' => $duration,
                    'changed_dimensions' => $userChangedDimensions
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Achievement evaluation failed for user {$userId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function seedReferenceTables(): void
    {
        $this->callSilent('db:seed', ['--class' => GenerateTagsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => AchievementsSeeder::class]);
    }

    private function displaySummary(): void
    {
        $this->info('Migration summary');
        $this->info('─────────────────');
        $this->info('✅  Photos processed : ' . number_format($this->processed));
        $this->info(($this->failed ? '❌' : '✅') . '  Failed           : ' . number_format($this->failed));

        $mem = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $this->info('📈  Peak memory      : ' . $mem . ' MB');

        // Show achievement status
        if ($this->option('skipAchievements')) {
            $this->info('⏭️   Achievements    : Skipped');
        } else {
            $this->info('🏆  Achievements    : Evaluated');
        }
    }
}
