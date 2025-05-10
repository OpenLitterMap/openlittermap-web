<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Services\Achievements\UpdateAchievementsService;
use App\Services\Redis\UpdateRedisService;
use App\Services\Tags\UpdateTagsService;
use App\Services\Timeseries\TimeSeriesService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Console\Command;
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
    protected UpdateAchievementsService $achievementsService;

    protected int $processed = 0;
    protected int $totalPhotos = 0;

    public function __construct(
        UpdateTagsService $updateTagsService,
        UpdateRedisService $updateRedisService,
        TimeseriesService $timeseriesService,
        UpdateAchievementsService $achievementsService
    ) {
        parent::__construct();
        $this->updateTagsService = $updateTagsService;
        $this->updateRedisService = $updateRedisService;
        $this->timeseriesService = $timeseriesService;
        $this->achievementsService = $achievementsService;
    }

    public function handle(): void
    {
        if (!DB::getSchemaBuilder()->hasColumn('photos', 'migrated_at')) {
            $this->error('The photos.migrated_at column does not exist. Please run the migration first.');
            return;
        }

        if (LitterObject::count() === 0) {
            $this->info('Seeding LitterObject definitions…');
            $this->call('db:seed', ['--class' => GenerateTagsSeeder::class]);
        }
        if (BrandList::count() === 0) {
            $this->info('Seeding BrandList definitions…');
            $this->call('db:seed', ['--class' => GenerateBrandsSeeder::class]);
        }

        $this->totalPhotos = Photo::whereNull('migrated_at')->count();

        if ($this->totalPhotos === 0) {
            $this->info('🎉 All photos are already migrated.');
            return;
        }

        $this->info("Starting migration of {$this->totalPhotos} photos...\n");

        Photo::whereNull('migrated_at')
            ->with('customTags')
            ->chunkById(500, function ($photos)
            {
                foreach ($photos as $photo)
                {
                    try
                    {
                        $this->updateTagsService->updateTags($photo);
                        $this->updateRedisService->updateRedis($photo);
                        $this->timeseriesService->updateTimeSeries($photo);
                        $this->achievementsService->generateAchievements($photo);
                    }
                    catch (Throwable $e)
                    {
                        Log::channel('migration')->error("Failed on photo {$photo->id}", [
                            'exception' => $e,
                        ]);

                        continue;
                    }

                    Photo::withoutEvents(fn() =>
                        $photo->forceFill(['migrated_at' => now()])->save()
                    );

                    $this->processed++;
                    if ($this->processed % 100 === 0) {
                        $pct = number_format(($this->processed / $this->totalPhotos) * 100, 2);
                        $this->line("Progress: {$this->processed}/{$this->totalPhotos} ({$pct}%)");
                    }
                }
            });

        $this->info("\n✅ Migration complete. Total migrated: {$this->processed}");
    }
}
