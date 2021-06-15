<?php

namespace App\Events\Photo;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class IncrementPhotoMonth implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $country_id, $state_id, $city_id, $created_at;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($country_id, $state_id, $city_id, $created_at)
    {
        $this->country_id = $country_id;
        $this->state_id   = $state_id;
        $this->city_id    = $city_id;
        $this->created_at = $created_at;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn ()
    {
        return new PrivateChannel('channel-name');
    }
}
