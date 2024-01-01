<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Redis;

class UpdateUserCategories implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        if ($event->total_litter_all_categories > 0)
        {
            foreach ($event->total_litter_per_category as $category => $total)
            {
                Redis::hincrby("user:$event->user_id", $category, $total);
            }

            Redis::hincrby("user:$event->user_id", "total_litter", $event->total_litter_all_categories);
        }

        if ($event->total_brands > 0)
        {
            foreach ($event->total_litter_per_brand as $brand => $total)
            {
                Redis::hincrby("user:$event->user_id", $brand, $total);
            }

            Redis::hincrby("user:$event->user_id", "total_brands", $event->total_brands);
        }

        Redis::hincrby("user:$event->user_id", "total_photos", 1);
    }
}
