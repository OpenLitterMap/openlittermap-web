<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ImageUploaded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $city;
    public $state;
    public $country;
    public $imageName;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($city, $state, $country, $name)
    {
        $this->city = $city;
        $this->state = $state;
        $this->country = $country;
        $this->imageName = $name;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn ()
    {
        return new Channel('main');
    }
}
