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

    /**
     * Update totals
     *
     * global:totals:photos
     * global:totals:tags
     * global:totals:categories:category
     * global:totals:objects:object
     * global:totals:brands:brand
     * global:totals:materials:material
     */
    protected function updateTotals(Photo $photo): void
    {
        $locations = [
            'global'  => 'global',
            'country' => "country:{$photo->country->id}",
            'state'   => "state:{$photo->state->id}",
            'city'    => "city:{$photo->city->id}",
        ];

        $totals = $photo->summary['totals'] ?? [];

        $tagsCount       = $totals['total_tags']     ?? 0;
        $customTagsCount = $totals['custom_tags']    ?? 0;
        $byCategory      = $totals['by_category']    ?? [];
        $byObject        = $totals['by_object']      ?? [];
        $byMaterial      = $totals['by_material']    ?? [];
        $byBrand         = $totals['by_brand']       ?? [];

        Redis::pipeline(function ($pipe) use (
            $locations,
            $tagsCount,
            $customTagsCount,
            $byCategory,
            $byObject,
            $byMaterial,
            $byBrand
        ) {
            foreach ($locations as $scopeKey)
            {
                // Overall totals
                $pipe->hincrby("{$scopeKey}:totals", 'photos', 1);
                $pipe->hincrby("{$scopeKey}:totals", 'tags', $tagsCount);
                $pipe->hincrby("{$scopeKey}:totals", 'custom_tags', $customTagsCount);

                // Categories
                foreach ($byCategory as $categoryId => $qty) {
                    $pipe->hincrby("{$scopeKey}:totals:categories", $categoryId, $qty);
                }

                // Objects
                foreach ($byObject as $objectId => $qty) {
                    $pipe->hincrby("{$scopeKey}:totals:objects", $objectId, $qty);
                }

                // Materials
                foreach ($byMaterial as $materialId => $qty) {
                    $pipe->hincrby("{$scopeKey}:totals:materials", $materialId, $qty);
                }

                // Brands
                foreach ($byBrand as $brandId => $qty) {
                    $pipe->hincrby("{$scopeKey}:totals:brands", $brandId, $qty);
                }

                // Custom Tags
            }
        });
    }

    /**
     * Time-series
     *
     * global:timeseries:ppd:yyyy:mm:dd
     * country:id:totals:timeseries:photos:yyyy:mm:dd
     * state:id:totals:timeseries:photos:yyyy:mm:dd
     * city:id:totals:timeseries:photos:yyyy:mm:dd
     * user:id:totals:timeseries:photos:yyyy:mm:dd
     *
     * country:id:photos_per_day:yyyy:mm:dd
     * state:id:photos_per_day:yyyy:mm:dd
     * city:id:photos_per_day:yyyy:mm:dd
     * user:id:photos_per_day:yyyy:mm:dd
     *
     * country:id:photos_per_week:yyyy:ww
     * state:id:photos_per_week:yyyy:ww
     * city:id:photos_per_week:yyyy:ww
     * user:id:photos_per_week:yyyy:ww
     *
     * country:id:photos_per_month:yyyy:mm
     * state:id:photos_per_month:yyyy:mm
     * city:id:photos_per_month:yyyy:mm
     * user:id:photos_per_month:yyyy:mm
     *
     * country:id:photos_per_year:yyyy
     * state:id:photos_per_year:yyyy
     * city:id:photos_per_year:yyyy
     * user:id:photos_per_year:yyyy
     */
    protected function updateTimeSeries(Photo $photo): void
    {
        $ts    = $photo->created_at;
        $date  = $ts->format('Y-m-d');
        $week  = $ts->format('o-W');  // ISO year-week
        $month = $ts->format('Y-m');
        $year  = $ts->format('Y');

        $scopes = [
            'global'  => 'global',
            'country' => "country:{$photo->country->id}",
            'state'   => "state:{$photo->state->id}",
            'city'    => "city:{$photo->city->id}",
            'user'    => "user:{$photo->user->id}",
        ];

        Redis::pipeline(function ($pipe) use ($scopes, $date, $week, $month, $year) {
            foreach ($scopes as $key) {
                // Daily
                $pipe->incr("{$key}:ts:daily:photos:{$date}");
                // Weekly
                $pipe->incr("{$key}:ts:weekly:photos:{$week}");
                // Monthly
                $pipe->incr("{$key}:ts:monthly:photos:{$month}");
                // Yearly
                $pipe->incr("{$key}:ts:yearly:photos:{$year}");
            }
        });
    }

    protected function updateLeaderboards(Photo $photo) {

        // get xp for photo

        // leaderboard:users:yyyy:mm:dd
        // leaderboard:locationType:locationId:yyyy:mm:dd
    }
}
