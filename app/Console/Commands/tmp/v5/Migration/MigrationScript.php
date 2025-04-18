<?php

namespace App\Console\Commands\tmp\v5\Migration;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Services\Redis\UpdateRedisService;
use App\Services\Tags\ClassifyTagsService;
use App\Services\Tags\PhotoTagService;
use App\Services\Tags\UpdateTagsService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MigrationScript extends Command
{
    protected $signature = 'olm:v5';
    protected $description = 'Upgrade OpenLitterMap data to v5';

    protected UpdateTagsService $updateTagsService;
    protected UpdateRedisService $updateRedisService;

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
        Photo::whereNull('migrated_at')
            ->with('customTags')
            ->chunkById(500, function ($photos)
            {
                DB::transaction(function () use ($photos)
                {
                    foreach ($photos as $photo)
                    {
                        // Convert all Tags to the new format
                        $this->updateTagsService->updateTags($photo);

                        // Update Redis with new tags
                        $this->updateRedisService->updateRedis($photo);

                        // update achievements - new

                        $photo->update(['migrated_at' => now()]);
                    }

                    $this->info("Migrated photo IDs " . $photos->first()->id . "–" . $photos->last()->id);
                }, 3);
            });

        $this->info("\n✅ Migration complete.");
    }
}
