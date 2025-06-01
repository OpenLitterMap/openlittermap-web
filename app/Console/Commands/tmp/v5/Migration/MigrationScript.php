<?php
/**
 *  OpenLitterMap - V5 data-migration
 *
 *  This console command walks through every **un-migrated** photo, converts its
 *  tag payload to the new schema, updates time-series counters, writes all
 *  counters to Redis in *one* call per user, recalculates that user’s
 *  achievements once, and finally marks the processed photos with
 *  `photos.migrated_at`.
 *
 *  ──────────────────────────────────────────────────────────────────────────────
 *  How it works (TL;DR)
 *  ──────────────────────────────────────────────────────────────────────────────
 *  1.  Cursor over *users*   that still own ≥ 1 un-migrated photo.
 *  2.  For each user stream their photos in mini-batches (default 500 rows).
 *  3.  For every mini-batch:
 *      • updateTags()       – converts the JSON tag blob
 *      • updateTimeSeries() – rolls time-series aggregates
 *      • queueBatch()       – ONE Lua / pipeline call to Redis
 *      • UPDATE photos SET migrated_at = NOW()
 *  4.  When all batches of that user are flushed → evaluateUser() once.
 *
 *  Guarantees:
 *      • constant memory      – never loads more than <batch> photos at a time
 *      • idempotent / resumable
 *      • safe to run multiple workers on disjoint user-id ranges
 */

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
use Illuminate\Support\Facades\{DB, Log};

class MigrationScript extends Command
{
    /* ------------------------------------------------------------------------ */
    /*  Artisan command metadata                                               */
    /* ------------------------------------------------------------------------ */

    protected $signature = <<<SIG
        olm:v5
        {--batch=500        : Number of photos to stream per mini-batch}
        {--minUser=1        : First user_id (inclusive) – use to shard workers}
    SIG;

    protected $description = 'Upgrade OpenLitterMap data to v5';

    /* ------------------------------------------------------------------------ */
    /*  Dependencies injected by Laravel                                       */
    /* ------------------------------------------------------------------------ */

    public function __construct(
        private readonly UpdateTagsService   $updateTagsService,
        private readonly TimeSeriesService   $timeSeriesService,
        private readonly AchievementEngine   $achievementEngine
    ) {
        parent::__construct();
    }

    /* ------------------------------------------------------------------------ */
    /*  Internal counters (for summary)                                        */
    /* ------------------------------------------------------------------------ */

    private int $processed = 0;
    private int $failed    = 0;

    /* ------------------------------------------------------------------------ */
    /*  Entry point                                                             */
    /* ------------------------------------------------------------------------ */

    public function handle(): int
    {
        /* ── 0. sanity checks ──────────────────────────────────────────────── */

        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('🛑  Column photos.migrated_at missing – run the DB migration first.');
            return self::FAILURE;
        }

        $this->seedReferenceTables();   // seed reference tables if missing
        TagKeyCache::warmCache();       // warm in-memory tag-id cache once


        /* 1. Cursor of users that still need work */
        $userCursor = DB::table('photos')
            ->whereNull('migrated_at')
            ->where('user_id', '>=', $this->option('minUser'))
            ->selectRaw('DISTINCT user_id')
            ->orderBy('user_id')
            ->lazy();

        $userCount = $userCursor->count();
        if ($userCount === 0) {
            $this->info('Nothing to migrate.');
            return self::SUCCESS;
        }

        $globalBar = $this->output->createProgressBar($userCount);
        $globalBar->setFormat('%current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  ETA:%estimated:-6s%');
        $globalBar->start();

        /* ── 2. iterate user-by-user ──────────────────────────────────────── */

        foreach ($userCursor as $row) {
            $this->migrateSingleUser((int) $row->user_id);
            $globalBar->advance();
        }

        $globalBar->advance();
        $this->newLine(2);
        $this->displaySummary();

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------------ */
    /*  Per-user migration – the workhorse                                     */
    /* ------------------------------------------------------------------------ */

    private function migrateSingleUser(int $userId): void
    {
        $this->info("Migrating user {$userId}...");
        $user        = User::find($userId);
        $name        = $user?->name ?? "User {$userId}";
        $totalPhotos = Photo::where('user_id', $userId)->count();
        $remaining   = Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->count();

        $this->info("➡  {$name}  –  {$remaining}/{$totalPhotos} photos to migrate");
        $userBar = $this->output->createProgressBar($remaining);
        $userBar->setFormat("   %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  ETA:%estimated:-6s%");
        $userBar->start();

        /** stream this user’s unmigrated photos in mini-batches */
        Photo::where('user_id', $userId)
            ->whereNull('migrated_at')
            ->with(['customTags'])
            ->lazyById($this->option('batch'))              // stream rows
            ->chunk($this->option('batch'), function ($photos) use ($userId, $userBar) {
                /* 2.1 transform each photos data */
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

                // 2.2 Update Redis & photo.migrated_at
                try {
                    // single Redis round-trip
                    RedisMetricsCollector::queueBatch($userId, $photos);

                    // mark photos as migrated
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

        // ── 2.3 Evaluate achievements once per user ───────────────────────────
        $this->achievementEngine->evaluate($userId);

        $userBar->finish();
        $this->newLine();
    }

    /* ------------------------------------------------------------------------ */
    /*  Helpers                                                                 */
    /* ------------------------------------------------------------------------ */

    /** make sure reference tables exist */
    private function seedReferenceTables(): void
    {
        $this->callSilent('db:seed', ['--class' => GenerateTagsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        $this->callSilent('db:seed', ['--class' => AchievementsSeeder::class]);
    }

    /** Final stats */
    private function displaySummary(): void
    {
        $this->info('Migration summary');
        $this->info('─────────────────');
        $this->info('✅  Photos processed : '.$this->processed);
        $this->info(($this->failed ? '❌' : '✅').'  Failed           : '.$this->failed);

        $mem = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $this->info('📈  Peak memory      : '.$mem.' MB');
    }
}
