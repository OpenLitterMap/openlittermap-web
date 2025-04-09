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
        $photos = Photo::query()
            ->select('id', 'datetime', 'user_id', 'country_id', 'state_id', 'city_id', 'remaining')
            ->orderBy('id', 'desc');

        $bar = $this->output->createProgressBar(Photo::count());
        $bar->start();

        foreach ($photos->cursor() as $photo)
        {
            // Convert all Tags to the new format
            $this->updateTagsService->updateTags($photo);

            // Update Redis with new tags
            $this->updateRedisService->updateRedis($photo);

            // New
            // $this->updateUserAchievements($photo);

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✅ Migration complete.");
    }

    protected function updateUserAchievements (Photo $photo) {

        // uploaded x days in a row
        // track days uploaded in a row
    }
}
