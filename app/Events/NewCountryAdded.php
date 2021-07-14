<?php

namespace App\Events;

use App\Models\Location\Country;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewCountryAdded implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $country, $countryCode, $now, $userId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($country, $countryCode, $now, $userId)
    {
        $this->country = $country;
        $this->countryCode = $countryCode;
        $this->now = $now;
        $this->userId = $userId;
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
