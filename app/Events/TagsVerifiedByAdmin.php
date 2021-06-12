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

    public $photo_id, $city_id, $state_id, $country_id, $user_id, $created_at;
    public $total_count;
    public $total_alcohol, $total_art,
        $total_brands,
        $total_coastal, $total_coffee,
        $total_dogshit,
        $total_dumping,
        $total_food,
        $total_industrial,
        $total_other,
        $total_sanitary, $total_softdrinks, $total_smoking;

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
                // Create a key, a string representation of each "total_category"
                // eg "total_smoking", "total_alcohol"
                $total_category_key = "total_" . $category;

                // Create a value
                // This is the sum of all litter types on this category
                $total_category_value = $photo->$category->total();

                // total_smoking = 1
                // total_alcohol = 2
                $this->$total_category_key = $total_category_value;

                // Don't include brands in total_litter. We keep total_brands separate.
                if ($photo->$category !== 'brands')
                {
                    $total_litter_all_categories += $total_category_value; // total counts of all categories
                }
            }
        }

        $this->total_count = $total_litter_all_categories;
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
