<?php


namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\UpdateRedisService;
use App\Services\Tags\UpdateTagsService;
use App\Services\Timeseries\TimeSeriesService;
use Database\Seeders\AchievementsSeeder;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrationScript extends Command
{
    protected $signature = 'olm:v5';

    protected $description = 'Upgrade OpenLitterMap data to v5';

    private int $processed = 0;
    private int $failed = 0;
    private int $totalPhotos = 0;
    private array $achievementStats = [
        'total' => 0,
        'by_user' => [],
    ];
    private int $CACHE_TIME = 3600; // 1 hour

    public function __construct(
        private UpdateTagsService $updateTagsService,
        private UpdateRedisService $updateRedisService,
        private TimeSeriesService $timeseriesService,
        private AchievementEngine $achievementEngine
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('The photos.migrated_at column does not exist. Please run the migration first.');
            return;
        }

        $this->setupEnvironment();

        $this->totalPhotos = Photo::whereNull('migrated_at')->count();
        if ($this->totalPhotos === 0) {
            $this->info('🎉 All photos are already migrated.');
            return;
        }

        $this->info("Found {$this->totalPhotos} photos to migrate.\n");

        $progressBar = $this->output->createProgressBar($this->totalPhotos);
        $progressBar->start();

        Photo::whereNull('migrated_at')
            ->with(['customTags', 'user'])
            ->chunkById((int) $this->option('batch'), function ($photos) use ($progressBar) {
                $this->processBatch($photos);
                $progressBar->advance($photos->count());
            });

        $progressBar->finish();
        $this->newLine(2);
        $this->displaySummary();
    }

    private function setupEnvironment(): void
    {
        // Seed data if needed
        if (DB::table('litter_objects')->count() === 0) {
            $this->info('Seeding LitterObject definitions…');
            $this->call('db:seed', ['--class' => GenerateTagsSeeder::class]);
        }

        if (DB::table('brandslist')->count() === 0) {
            $this->info('Seeding BrandList definitions…');
            $this->call('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        }

        if (DB::table('achievements')->count() === 0) {
            $this->info('Seeding achievement definitions...');
            $this->call('db:seed', ['--class' => AchievementsSeeder::class]);
        }

        // Pre-cache tag mappings
        $this->info("Caching tag mappings...");

        DB::table('litter_objects')->pluck('id', 'key')
            ->each(fn($id, $key) => Cache::put("tag:litter_objects:{$key}", $id, $this->CACHE_TIME));

        DB::table('categories')->pluck('id', 'key')
            ->each(fn($id, $key) => Cache::put("tag:categories:{$key}", $id, $this->CACHE_TIME));

        DB::table('materials')->pluck('id', 'key')
            ->each(fn($id, $key) => Cache::put("tag:materials:{$key}", $id, $this->CACHE_TIME));

        DB::table('brandslist')->pluck('id', 'key')
            ->each(fn($id, $key) => Cache::put("tag:brandslist:{$key}", $id, $this->CACHE_TIME));
    }

    private function processBatch($photos): void
    {
        Photo::unsetEventDispatcher();

        foreach ($photos as $photo) {
            try {
                $this->processPhoto($photo);
                $this->processed++;
            } catch (\Throwable $e) {
                $this->failed++;
                Log::error("Migration failed for photo {$photo->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Photo::setEventDispatcher(app('events'));

        // Clear memory periodically
        if ($this->processed % 5000 === 0) {
            gc_collect_cycles();
        }
    }

    private function processPhoto(Photo $photo): void
    {
        // 1. Convert tags to new format
        $this->updateTagsService->updateTags($photo);

        // 2. Update Redis metrics
        $this->updateRedisService->updateRedis($photo);

        // 3. Update time series
        $this->timeseriesService->updateTimeSeries($photo);

        // 4. Process achievements (synchronously)
        if (!$this->option('skip-achievements')) {
            $unlocked = $this->achievementEngine->evaluate($photo);

            if ($unlocked->isNotEmpty()) {
                $this->achievementStats['total'] += $unlocked->count();
                $this->achievementStats['by_user'][$photo->user_id] =
                    ($this->achievementStats['by_user'][$photo->user_id] ?? 0) + $unlocked->count();
            }
        }

        // 5. Mark as migrated
        Photo::withoutEvents(fn() =>
            $photo->forceFill(['migrated_at' => now()])->save()
        );
    }

    private function displaySummary(): void
    {
        $this->info("Migration Summary:");
        $this->info("- Total photos processed: {$this->processed}");

        if ($this->failed > 0) {
            $this->warn("- Failed photos: {$this->failed}");
        }

        if (!$this->option('skip-achievements')) {
            $userCount = count($this->achievementStats['by_user']);
            $this->info("- Achievements unlocked: {$this->achievementStats['total']}");
            $this->info("- Users who unlocked achievements: {$userCount}");

            if ($userCount > 0) {
                $avgPerUser = round($this->achievementStats['total'] / $userCount, 2);
                $this->info("- Average achievements per user: {$avgPerUser}");
            }
        }

        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->info("- Peak memory usage: {$peakMemory} MB");
    }
}
