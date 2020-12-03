<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagsVerifiedByAdmin
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $photo_id, $city_id, $state_id, $country_id, $user_id;
    public $total_count;
    public $total_alcohol, $total_art,
        $total_brands,
        $total_coastal, $total_coffee,
        $total_dumping,
        $total_food,
        $total_industrial,
        $total_other,
        $total_sanitary, $total_softdrinks, $total_smoking;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($photo_id)
    {
        $photo = Photo::find($photo_id);
        $this->photo_id = $photo_id;

        $this->city_id = $photo->city_id;
        $this->state_id = $photo->state_id;
        $this->country_id = $photo->country_id;
        $this->user_id = $photo->user_id;

        $total_count = 0;

        foreach ($photo->categories() as $category)
        {
            if ($photo->$category)
            {
                $total = $photo->$category->total();

                $total_string = "total_" . $category; // total_smoking, total_food...

                $this->$total_string += $total;

                $total_count += $total; // total counts of all categories
            }
        }

        $this->total_count = $total_count;
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
