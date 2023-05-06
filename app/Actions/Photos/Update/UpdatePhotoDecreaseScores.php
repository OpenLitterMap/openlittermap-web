<?php

namespace App\Actions\Photos\Update;

use App\Models\Photo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UpdatePhotoDecreaseScores
{
    /**
     * When a User wants to update one of the Photos,
     *
     * We need to reset the scores that were already rewarded to:
     * - User
     * - Team
     * - Locations
     */
    public function run (Photo $photo, $userId, $diff)
    {
        // photo.result_string will be re-written

        // Reverse IncrementLocation.php and UpdateUserCategories
        $decrementCounts = $this->decrementLocation($photo);

        // Reverse IncreaseTeamTotalLitter
        $this->decreaseTeamTotalLitter($photo, $decrementCounts);

        // What if Littercoin was mined?

        // $this->decrementUserCategoryScores($photo, $decrementCounts);

        // UpdateUserTimeSeries needs to be refactored before its included here
    }

    /**
     * Reverse of IncrementLocation.php
     */
    private function decrementLocation ($photo)
    {
        $decrementTotalLitter = 0;
        $decrementTotalBrands = 0;

        // Decrease for each Category including Brands
        foreach ($photo->tags as $category => $tags)
        {
            if ($category === 'brands')
            {
                $decrementTotalBrands = ($photo->brands->total() * -1);

                foreach ($photo->tags['brands'] as $brand => $quantity)
                {
                    $decrementBrand = ($quantity * -1);

                    // User
                    Redis::hincrby("user:$photo->user_id", $brand, $decrementBrand);

                    // Locations
                    Redis::hincrby("country:$photo->country_id", $brand, $decrementBrand);
                    Redis::hincrby("state:$photo->state_id", $brand, $decrementBrand);
                    Redis::hincrby("city:$photo->city_id", $brand, $decrementBrand);
                }
            }
            else
            {
                $decrementCategory = ($photo->$category->total() * -1);

                $decrementTotalLitter += $decrementCategory;

                // User
                Redis::hincrby("user:$photo->user_id", $category, $decrementCategory);

                // Locations
                Redis::hincrby("country:$photo->country_id", $category, $decrementCategory);
                Redis::hincrby("state:$photo->state_id", $category, $decrementCategory);
                Redis::hincrby("city:$photo->city_id", $category, $decrementCategory);
            }
        }

        // Decrease total_litter
        if ($decrementTotalLitter !== 0)
        {
            // User
            Redis::hincrby("user:$photo->user_id", "total_litter", $decrementTotalLitter);

            // Locations
            Redis::hincrby("country:$photo->country_id", "total_litter", $decrementTotalLitter);
            Redis::hincrby("state:$photo->state_id", "total_litter", $decrementTotalLitter);
            Redis::hincrby("city:$photo->city_id", "total_litter", $decrementTotalLitter);
        }

        if ($decrementTotalBrands !== 0)
        {
            // User
            Redis::hincrby("user:$photo->user_id", "total_brands", $decrementTotalLitter);

            // Locations
            Redis::hincrby("country:$photo->country_id", "total_brands", $decrementTotalBrands);
            Redis::hincrby("state:$photo->state_id", "total_brands", $decrementTotalBrands);
            Redis::hincrby("city:$photo->city_id", "total_brands", $decrementTotalBrands);
        }

        // Doesn't really make sense to do this again and again
        // We should consider refactoring how this value is added during upload
        // Reverse of UpdateTotalPhotosForLocationAction

        // User
        Redis::hincrby("user:$photo->user_id", "total_photos", -1);

        // Locations
        Redis::hincrby("country:$photo->country_id", "total_photos", -1);
        Redis::hincrby("state:$photo->state_id", "total_photos", -1);
        Redis::hincrby("city:$photo->city_id", "total_photos", -1);

        return compact('decrementTotalLitter', 'decrementTotalBrands');
    }

    /**
     * Reverse of IncreaseTeamTotalLitter
     */
    private function decreaseTeamTotalLitter ($photo, $decrementCounts)
    {
        if (!$photo->team) {
            return;
        }

        $decrementTotal = $decrementCounts['decrementTotalLitter'] + $decrementCounts['decrementTotalBrands'];

        // We need to move these counts to Redis
        $photo->team->total_litter += $decrementTotal;
        $photo->team->save();

        DB::table('team_user')
            ->where([
                'team_id' => $photo->team_id,
                'user_id' => $photo->user_id
            ])
            ->update([
                'total_litter' => DB::raw('ifnull(total_litter, 0) - ' . $decrementTotal),
                'updated_at' => now()
            ]);
    }
}
