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
     * Update totals using summary:
     *
     *  <scope>:totals
     *    photos         ++
     *    tags           += total_tags
     *    custom_tags    += custom_tags
     *
     *  <scope>:totals:categories   ← categoryKey => count
     *  <scope>:totals:objects      ← objectKey => count
     *  <scope>:totals:materials    ← materialKey => count
     *  <scope>:totals:brands       ← brandKey => count
     *  <scope>:totals:custom_tags_breakdown ← customTagKey => count (optional)
     */
    protected function updateTotals(Photo $photo): void
    {
        $summary = $photo->summary ?? [];
        $totals  = $summary['totals'] ?? [];
        $tags    = $summary['tags']   ?? [];

        $tagsCount       = $totals['total_tags']     ?? 0;
        $customTagsCount = $totals['custom_tags']    ?? 0;
        $byCategory      = $totals['by_category']    ?? [];

        $locations = [
            'global'  => 'global',
            'country' => "country:{$photo->country->id}",
            'state'   => "state:{$photo->state->id}",
            'city'    => "city:{$photo->city->id}",
        ];

        Redis::pipeline(function ($pipe) use (
            $locations,
            $tagsCount,
            $customTagsCount,
            $byCategory,
            $tags
        ) {
            foreach ($locations as $scopeKey) {
                // overall counters
                $pipe->hincrby("{$scopeKey}:totals", 'photos',      1);
                $pipe->hincrby("{$scopeKey}:totals", 'tags',        $tagsCount);
                $pipe->hincrby("{$scopeKey}:totals", 'custom_tags', $customTagsCount);

                // by-category
                foreach ($byCategory as $catKey => $qty) {
                    $pipe->hincrby("{$scopeKey}:totals:categories", $catKey, $qty);
                }

                // dive into each category → object
                foreach ($tags as $catKey => $objects) {
                    foreach ($objects as $objKey => $data) {
                        // objects
                        $pipe->hincrby("{$scopeKey}:totals:objects", $objKey, $data['quantity']);

                        // materials breakdown
                        foreach ($data['materials'] as $matKey => $matQty) {
                            $pipe->hincrby("{$scopeKey}:totals:materials", $matKey, $matQty);
                        }

                        // brands breakdown
                        foreach ($data['brands'] as $brandKey => $brandQty) {
                            $pipe->hincrby("{$scopeKey}:totals:brands", $brandKey, $brandQty);
                        }

                        // custom-tags breakdown (if you want per-tag counts)
                        foreach ($data['custom_tags'] as $ctKey => $ctQty) {
                            $pipe->hincrby("{$scopeKey}:totals:custom_tags_breakdown", $ctKey, $ctQty);
                        }
                    }
                }
            }
        });
    }

    /**
     * Time-series remains largely the same, using date‐buckets:
     *   <scope>:ts:daily:photos:<YYYY-MM-DD>
     *   <scope>:ts:weekly:photos:<YYYY-WW>
     *   <scope>:ts:monthly:photos:<YYYY-MM>
     *   <scope>:ts:yearly:photos:<YYYY>
     */
    protected function updateTimeSeries(Photo $photo): void
    {
        $ts    = $photo->created_at;
        $date  = $ts->format('Y-m-d');
        $week  = $ts->format('o-W');
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
            foreach ($scopes as $scopeKey) {
                $pipe->incr("{$scopeKey}:ts:daily:photos:{$date}");
                $pipe->incr("{$scopeKey}:ts:weekly:photos:{$week}");
                $pipe->incr("{$scopeKey}:ts:monthly:photos:{$month}");
                $pipe->incr("{$scopeKey}:ts:yearly:photos:{$year}");
            }
        });
    }

    protected function updateLeaderboards(Photo $photo) {

        // get xp for photo

        // leaderboard:users:yyyy:mm:dd
        // leaderboard:locationType:locationId:yyyy:mm:dd
    }
}
