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
    public $total_litter_all_categories, $total_brands;

    // total per category, or total per brand
    public $total_litter_per_category = []; // smoking => 5, alcohol => 1
    public $total_litter_per_brand = []; // mcd => 1, starbucks => 2

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

        $categories = Photo::categories();
        $brands = Photo::getBrands();

        // Count the total category values on this photo
        // We will use this data to update the total category values...
        // for each Country, State and City the photo was uploaded from
        foreach ($categories as $category)
        {
            if ($photo->$category)
            {
                if ($category === "brands")
                {
                    $this->total_brands = $photo->brands->total();

                    foreach ($brands as $brand)
                    {
                        if ($photo->brands->$brand)
                        {
                            // This parent class will hold each brand total
                            // and use it to update each listener
                            $this->total_litter_per_brand[$brand] = $photo->brands->$brand;
                        }
                    }
                }
                // Don't include brands in total_litter. We keep total_brands separate.
                else
                {
                    $categoryTotal = $photo->$category->total();

                    // This parent class will hold each category total
                    // and use it to update each listener
                    $this->total_litter_per_category[$category] = $categoryTotal;

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
