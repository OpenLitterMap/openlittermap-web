<?php

namespace App\Services\Redis;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use Illuminate\Support\Facades\Redis;

class UpdateRedisService
{
    public function updateRedis(Photo $photo): void
    {
        $this->updateTotals($photo);
        $this->updateTimeSeries($photo);
        $this->updateLeaderboards($photo);
    }

    protected function updateTotals (Photo $photo): void
    {
        $locations = ['global', 'country', 'state', 'city'];
        // global:totals:photos++
        // global:totals:tags++
        // global:totals:categories:category
        // global:totals:objects:object
        // global:totals:brands:brand
        // global:totals:materials:material

        // v2 tags
        $summary = $photo->summary;
        $categories = [];
        $objects = [];
        $brands = [];
        $materials = [];
        $customTags = [];
        $customTagsCount = 0;

        $country = $photo->country;
        $state = $photo->state;
        $city = $photo->city;

        $tagsCount = $photo->total_tags;

        foreach ($locations as $location)
        {
            if ($location === 'global') {
                $key = "global";
            } else {
                if ($location === "country") {
                    $key = "country:$country->id";
                } elseif ($location === "state") {
                    $key = "state:$state->id";
                } elseif ($location === "city") {
                    $key = "city:$city->id";
                }
            }

            Redis::hincrby("$key:totals", 'photos', 1);
            Redis::hincrby("$key:totals", 'tags', $tagsCount);
            Redis::hincrby("$key:totals", 'custom_tags', $customTagsCount);

            foreach ($categories as $category)
            {
                $categoryId = Category::where('key', $category)->first()->id;

                Redis::hincrby("$key:totals:category", $categoryId, 1);
            }

            // g.categories.category.object.material
            foreach ($objects as $object)
            {
                $objectId = LitterObject::where('key', $object)->first()->id;

                Redis::hincrby("$key:totals:objects", $objectId, 1);
            }

            foreach ($materials as $material)
            {
                $materialId = Materials::where('key', $material)->first()->id;

                Redis::hincrby("$key:materials", $materialId, 1);
            }

            foreach ($brands as $brand)
            {
                $brandId = BrandList::where('key', $brand)->first()->id;

                Redis::hincrby("$key:totals:brands", $brandId, 1);
            }
        }
    }

    public function updateTimeSeries(Photo $photo): void {
        $created_at = $photo->created_at;
        $taken_at = $photo->datetime;

        Redis::hincrby("test", 1);

        // global:timeseries:ppd:yyyy:mm:dd
        // country:id:totals.timeseries.photos:yyyy:mm:dd
        // state:id:totals.timeseries.photos:yyyy:mm:dd
        // city:id:totals.timeseries.photos:yyyy:mm:dd
        // user:id:totals.timeseries.photos:yyyy:mm:dd

        // country:id:photos_per_day:yyyy:mm:dd
        // state:id:photos_per_day:yyyy:mm:dd
        // city:id:photos_per_day:yyyy:mm:dd
        // user:id:photos_per_day:yyyy:mm:dd

        // country:id:photos_per_week:yyyy:ww
        // state:id:photos_per_week:yyyy:ww
        // city:id:photos_per_week:yyyy:ww
        // user:id:photos_per_week:yyyy:ww

        // country:id:photos_per_month:yyyy:mm
        // state:id:photos_per_month:yyyy:mm
        // city:id:photos_per_month:yyyy:mm
        // user:id:photos_per_month:yyyy:mm

        // country:id:photos_per_year:yyyy
        // state:id:photos_per_year:yyyy
        // city:id:photos_per_year:yyyy
        // user:id:photos_per_year:yyyy
    }

    protected function updateLeaderboards(Photo $photo) {

        // get xp for photo

        // leaderboard:users:yyyy:mm:dd
        // leaderboard:locationType:locationId:yyyy:mm:dd
    }
}
