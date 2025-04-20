<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Photo;
use App\Services\Redis\UpdateRedisService;
use App\Services\Tags\UpdateTagsService;
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

    protected int $processed = 0;
    protected int $totalPhotos = 0;

    public function __construct(
        UpdateTagsService $updateTagsService,
        UpdateRedisService $updateRedisService
    ) {
        parent::__construct();
        $this->updateTagsService = $updateTagsService;
        $this->updateRedisService = $updateRedisService;
    }

    public function handle(): void
    {
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
                try
                {
                    DB::transaction(function () use ($photos)
                    {
                        foreach ($photos as $photo)
                        {
                            $this->updateTagsService->updateTags($photo);

                            $this->updateRedisService->updateRedis($photo);

                            // Update Achievements

                            $photo->update(['migrated_at' => now()]);
                            $this->processed++;

                            // Output percentage every 100 photos
                            if ($this->processed % 100 === 0 || $this->processed === $this->totalPhotos) {
                                $percent = number_format(($this->processed / $this->totalPhotos) * 100, 2);
                                $this->line("Progress: {$this->processed}/{$this->totalPhotos} ({$percent}%)");
                            }
                        }

                        $this->info("✅ Migrated photos {$photos->first()->id} – {$photos->last()->id}");
                    }, 3);
                }
                catch (Throwable $e)
                {
                    $firstId = $photos->first()->id ?? 'unknown';
                    $lastId = $photos->last()->id ?? 'unknown';

                    $errorMessage = "❌ Error migrating photos $firstId – $lastId: " . $e->getMessage();
                    $this->error($errorMessage);

                    // Write to log file
                    Log::channel('migration')->error($errorMessage, [
                        'trace' => $e->getTraceAsString(),
                        'photo_ids' => [$firstId, $lastId],
                        'processed_count' => $this->processed,
                    ]);

                    // Optional: rethrow to stop execution
                    throw $e;
                }
            });

        $this->info("\n✅ Migration complete. Total migrated: {$this->processed}");
    }
}
