<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewCityAdded implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $city, $state, $country, $now;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($city, $state, $country, $now)
    {
        $this->city    = $city;
        $this->state   = $state;
        $this->country = $country;
        $this->now     = $now;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('main');
    }
}
