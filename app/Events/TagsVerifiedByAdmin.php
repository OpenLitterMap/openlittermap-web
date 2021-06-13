<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TagsVerifiedByAdmin implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // photo relationships
    public $photo_id, $city_id, $state_id, $country_id, $user_id, $created_at;

    // total litter on all categories
    public $total_litter_all_categories;

    /**
     * The tags on a single photo have been verified by an Admin
     *
     * photo.verified => 2
     */
    public function __construct ($photo_id)
    {
        $photo = Photo::find($photo_id);
        $this->photo_id = $photo_id;

        $this->city_id = $photo->city_id;
        $this->state_id = $photo->state_id;
        $this->country_id = $photo->country_id;
        $this->user_id = $photo->user_id;
        $this->created_at = $photo->created_at;

        $total_litter_all_categories = 0;

        // Count the total category values on this photo
        // We will use this data to update the total_category values...
        // for Country, State and City the photo was uploaded from
        foreach ($photo->categories() as $category)
        {
            if ($photo->$category)
            {
                // Don't include brands in total_litter. We keep total_brands separate.
                if ($photo->$category !== 'brands')
                {
                    $categoryTotal = $photo->$category->total();

                    $this->$category = $categoryTotal;

                    $total_litter_all_categories += $categoryTotal;
                }
            }
        }

        $this->total_litter_all_categories = $total_litter_all_categories;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
