<?php


namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Achievements\AchievementEngine;
use App\Services\Redis\RedisMetricsCollector;
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
    private int $CACHE_TIME = 3600; // 1 hour
    private int $BATCH_SIZE = 500;

    public function __construct(
        private UpdateTagsService $updateTagsService,
        private TimeSeriesService $timeseriesService,
        private AchievementEngine $achievementEngine
    ) {
        parent::__construct();
    }

    /**
     * Note: Remember to delete all existing redis keys before running this command.
     */
    public function handle(): void
    {
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('The photos.migrated_at column does not exist. Please run the migration first.');
            return;
        }

        $this->setupEnvironment();

        $totalPhotos = Photo::whereNull('migrated_at')->count();
        if ($totalPhotos === 0) {
            $this->info('🎉 All photos are already migrated.');
            return;
        }

        $this->info("Found {$totalPhotos} photos to migrate.\n");

        $progressBar = $this->output->createProgressBar($totalPhotos);
        $progressBar->start();

        Photo::whereNull('migrated_at')
            ->with(['customTags', 'user'])
            ->orderBy('user_id')
            ->chunkById($this->BATCH_SIZE, function ($photos) use ($progressBar) {
                $photosByUser = $photos->groupBy('user_id');
                foreach ($photosByUser as $userId => $userPhotos) {
                    $this->processUserPhotos($userId, $userPhotos);
                    $progressBar->advance($userPhotos->count());
                }

                unset($photos);
                gc_collect_cycles();
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

    // used by a test
    public function processUserPhotos(int $userId, $userPhotos): void
    {
        Photo::unsetEventDispatcher();

        try {
            // Process all photos for this user
            $successfulPhotos = collect();

            foreach ($userPhotos as $photo) {
                try {
                    // 1. Convert tags to new format
                    $this->updateTagsService->updateTags($photo);

                    // 2. Update time series
                    $this->timeseriesService->updateTimeSeries($photo);

                    $successfulPhotos->push($photo);
                    $this->processed++;
                } catch (\Throwable $e) {
                    $this->failed++;
                    Log::error("Migration failed for photo {$photo->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($successfulPhotos->isNotEmpty()) {
                // 3. Batch update Redis for all photos at once
                RedisMetricsCollector::queueBatch($userId, $successfulPhotos);

                // 4. Check achievements once with final state
                $this->achievementEngine->evaluate($userId);

                // 5. Bulk update migrated_at
                Photo::whereIn('id', $successfulPhotos->pluck('id'))
                    ->update(['migrated_at' => now()]);
            }
        } finally {
            Photo::setEventDispatcher(app('events'));
        }
    }

    private function displaySummary(): void
    {
        $this->info("Migration Summary:");
        $this->info("- Total photos processed: {$this->processed}");

        if ($this->failed > 0) {
            $this->warn("- Failed photos: {$this->failed}");
        }

        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->info("- Peak memory usage: {$peakMemory} MB");
    }
}
