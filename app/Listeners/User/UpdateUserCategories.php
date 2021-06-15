<?php

namespace App\Listeners\User;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class UpdateUserCategories implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TagsVerifiedByAdmin  $event
     * @return void
     */
    public function handle (TagsVerifiedByAdmin $event)
    {
        $categories = Photo::categories();

        foreach ($categories as $category)
        {
            if ($event->$category)
            {
                Redis::hincrby("user:$event->user_id", $category, $event->$category);
            }
        }

        Redis::hincrby("user:$event->user_id", "total_photos", 1);
        Redis::hincrby("user:$event->user_id", "total_litter", $event->total_litter_all_categories);
    }
}
