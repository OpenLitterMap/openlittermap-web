<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Services\Achievements\UpdateAchievementsService;
use App\Services\Redis\UpdateRedisService;
use App\Services\Tags\UpdateTagsService;
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
    protected UpdateAchievementsService $achievementsService;

    protected int $processed = 0;
    protected int $totalPhotos = 0;

    public function __construct(
        UpdateTagsService $updateTagsService,
        UpdateRedisService $updateRedisService,
        UpdateAchievementsService $achievementsService
    ) {
        parent::__construct();
        $this->updateTagsService = $updateTagsService;
        $this->updateRedisService = $updateRedisService;
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
            ->lazyById(500, function ($photos)
            {
                try
                {
                    foreach ($photos as $photo)
                    {
                        $this->updateTagsService->updateTags($photo);

                        $this->updateRedisService->updateRedis($photo);

                        $this->achievementsService->generateAchievements($photo);

                        Photo::withoutEvents(fn() => $photo->forceFill(['migrated_at' => now()])->save());
                        $this->processed++;

                        if ($this->processed % 100 === 0) {
                            $percent = number_format(($this->processed / $this->totalPhotos) * 100, 2);
                            $this->line("Progress: {$this->processed}/{$this->totalPhotos} ({$percent}%)");
                        }
                    }

                    if ( ! $photos->isEmpty()) {
                        $this->info("✅ Migrated photos {$photos->first()->id} – {$photos->last()->id}");
                    }
                }
                catch (Throwable $e)
                {
                    $firstId = $photos->first()->id ?? 'unknown';
                    $lastId = $photos->last()->id ?? 'unknown';

                    $errorMessage = "❌ Error migrating photos $firstId – $lastId: " . $e->getMessage();
                    $this->error($errorMessage);

                    Log::channel('migration')->error($errorMessage, [
                        'trace' => $e->getTraceAsString(),
                        'photo_ids' => [$firstId, $lastId],
                        'processed_count' => $this->processed,
                    ]);

                    throw $e;
                }
            });

        $this->info("\n✅ Migration complete. Total migrated: {$this->processed}");
    }
}
