<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Services\Achievements\AchievementEngine;
use App\Services\Achievements\AchievementProgressTracker;
use App\Services\Achievements\AchievementRepository;
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
use Throwable;

class MigrationScript extends Command
{
    protected $signature = 'olm:v5';
    protected $description = 'Upgrade OpenLitterMap data to v5';

    protected UpdateTagsService $updateTagsService;
    protected UpdateRedisService $updateRedisService;
    protected TimeseriesService $timeseriesService;
    protected AchievementEngine $achievementEngine;

    protected int $processed = 0;
    protected int $totalPhotos = 0;
    protected array $achievementsUnlocked = [];

    public function __construct(
        UpdateTagsService $updateTagsService,
        UpdateRedisService $updateRedisService,
        TimeseriesService $timeseriesService,
        AchievementEngine $achievementEngine
    ) {
        parent::__construct();
        $this->updateTagsService = $updateTagsService;
        $this->updateRedisService = $updateRedisService;
        $this->timeseriesService = $timeseriesService;
        $this->achievementEngine = $achievementEngine;
    }

    public function handle(): void
    {
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('The photos.migrated_at column does not exist. Please run the migration first.');
            return;
        }

        $this->checkSeeders();
        $this->warmCaches();

        $this->totalPhotos = Photo::whereNull('migrated_at')->count();
        if ($this->totalPhotos === 0) {
            $this->info('🎉 All photos are already migrated.');
            return;
        }
        $this->info("Found {$this->totalPhotos} photos to migrate.\n");

        $query = Photo::whereNull('migrated_at')
            ->with(['customTags', 'user']);

        $batchSize = (int) $this->option('batch');
        $query->chunkById($batchSize, function ($photos) {
            $this->processBatch($photos);
        });

        $this->info("\n✅ Migration complete. Total migrated: {$this->processed}");
        $this->displaySummary();
    }

    private function checkSeeders(): void
    {
        if (LitterObject::count() === 0) {
            $this->info('Seeding LitterObject definitions…');
            $this->call('db:seed', ['--class' => GenerateTagsSeeder::class]);
        }
        if (BrandList::count() === 0) {
            $this->info('Seeding BrandList definitions…');
            $this->call('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        }
        if (DB::table('achievements')->count() === 0) {
            $this->info('Seeding achievement definitions...');
            $this->call('db:seed', ['--class' => AchievementsSeeder::class]);
        }
    }

    private function warmCaches(): void
    {
        $this->info("Pre-warming caches...");

        // Pre-cache all tag IDs
        $tagMappings = [
            'object' => 'litter_objects',
            'category' => 'categories',
            'material' => 'materials',
            'brand' => 'brandslist',
            'customTag' => 'custom_tags',
        ];

        foreach ($tagMappings as $dimension => $table) {
            $tags = DB::table($table)->pluck('id', 'key');
            foreach ($tags as $key => $id) {
                Cache::put("tag_id:{$dimension}:{$key}", $id, 86400);
            }
        }

        // Pre-load achievement definitions
        app(AchievementRepository::class)->getAchievementDefinitions();

        $this->info("Caches warmed!");
    }

    private function processBatch($photos): void
    {
        // Disable events temporarily for faster processing
        Photo::unsetEventDispatcher();

        foreach ($photos as $photo) {
            try {
                $this->processPhoto($photo);
            } catch (Throwable $e) {
                $this->error("\nError processing photo {$photo->id}: " . $e->getMessage());
                Log::error("Migration failed for photo {$photo->id}", [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                continue;
            }
        }

        // Re-enable events
        Photo::setEventDispatcher(app('events'));

        // Clear memory
        if ($this->processed % 5000 === 0) {
            $this->clearMemory();
        }
    }

    private function processPhoto(Photo $photo): void
    {
        // Convert the old tags to the new format
        $this->updateTagsService->updateTags($photo);

        // Capture metadata counts on Redis
        $this->updateRedisService->updateRedis($photo);

        // Collect new time-series data
        $this->timeseriesService->updateTimeSeries($photo);

        if (!$this->option('skip-achievements')) {
            $unlocked = $this->achievementEngine->evaluate($photo);

            if ($unlocked->isNotEmpty()) {
                $this->achievementsUnlocked[$photo->user_id] =
                    ($this->achievementsUnlocked[$photo->user_id] ?? 0) + $unlocked->count();
            }
        }

        Photo::withoutEvents(fn() =>
            $photo->forceFill(['migrated_at' => now()])->save()
        );

        $this->processed++;
    }

    /**
     * Clear memory to prevent memory leaks
     */
    private function clearMemory(): void
    {
        // Clear the achievement progress tracker cache
        $tracker = app(AchievementProgressTracker::class);

        foreach (array_keys($this->achievementsUnlocked) as $userId) {
            $tracker->clearUserCache($userId);
        }

        // Run garbage collection
        gc_collect_cycles();
    }

    private function displaySummary(): void
    {
        $this->info("Migration Summary:");
        $this->info("- Total photos migrated: {$this->processed}");

        if (!$this->option('skip-achievements')) {
            $totalAchievements = array_sum($this->achievementsUnlocked);
            $totalUsers = count($this->achievementsUnlocked);
            $this->info("- Achievements unlocked: {$totalAchievements}");
            $this->info("- Users who unlocked achievements: {$totalUsers}");
        }

        $this->info("- Memory peak usage: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB");
    }
}
