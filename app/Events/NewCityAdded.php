<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewCityAdded implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $city, $state, $country, $now, $cityId, $lat, $lon, $photoId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($city, $state, $country, $now, $cityId = null, $lat = null, $lon = null, $photoId = null)
    {
        $this->city    = $city;
        $this->state   = $state;
        $this->country = $country;
        $this->now     = $now;
        $this->cityId  = $cityId;
        $this->lat     = $lat;
        $this->lon     = $lon;
        $this->photoId = $photoId;
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
