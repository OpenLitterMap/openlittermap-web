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

        $summary = $photo->summary ?? [];
        $totals  = $summary['totals'] ?? [];
        $items   = $summary['items']  ?? [];

        $tagsCount       = $totals['total_tags']      ?? 0;
        $customTagsCount = $totals['custom_tags']     ?? 0;
        $byCategory      = $totals['by_category']     ?? [];

        // collect object, material, brand breakdowns from items
        $objectCounts   = [];
        $materialCounts = [];
        $brandCounts    = [];

        foreach ($items as $item) {
            // base object
            if (!is_null($item['litter_object_id'])) {
                $objectCounts[$item['litter_object_id']] =
                    ($objectCounts[$item['litter_object_id']] ?? 0) + $item['quantity'];
            }
            // extra tags
            foreach ($item['extra_tags'] as $extra) {
                if ($extra['type'] === 'material') {
                    $materialCounts[$extra['id']] =
                        ($materialCounts[$extra['id']] ?? 0) + $extra['quantity'];
                } elseif ($extra['type'] === 'brand') {
                    $brandCounts[$extra['id']] =
                        ($brandCounts[$extra['id']] ?? 0) + $extra['quantity'];
                }
            }
        }

        // for each location context
        foreach ($locations as $location) {
            switch ($location) {
                case 'global':
                    $key = 'global';
                    break;
                case 'country':
                    $key = "country:{$photo->country->id}";
                    break;
                case 'state':
                    $key = "state:{$photo->state->id}";
                    break;
                case 'city':
                    $key = "city:{$photo->city->id}";
                    break;
            }

            // overall counts
            Redis::hincrby("{$key}:totals", 'photos', 1);
            Redis::hincrby("{$key}:totals", 'tags', $tagsCount);
            Redis::hincrby("{$key}:totals", 'custom_tags', $customTagsCount);

            // by category
            foreach ($byCategory as $categoryId => $qty) {
                Redis::hincrby("{$key}:totals:category", $categoryId, $qty);
            }

            // by object
            foreach ($objectCounts as $objectId => $qty) {
                Redis::hincrby("{$key}:totals:objects", $objectId, $qty);
            }

            // by material
            foreach ($materialCounts as $materialId => $qty) {
                Redis::hincrby("{$key}:materials", $materialId, $qty);
            }

            // by brand
            foreach ($brandCounts as $brandId => $qty) {
                Redis::hincrby("{$key}:totals:brands", $brandId, $qty);
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
