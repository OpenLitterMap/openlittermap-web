<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ImageUploaded implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // For Websockets
    public $city, $state, $country, $countryCode, $imageName, $teamName;

    // For CheckContributors
    public $userId, $countryId, $stateId, $cityId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($city, $state, $country, $countryCode, $imageName, $teamName, $userId, $countryId, $stateId, $cityId)
    {
        $this->city = $city;
        $this->state = $state;
        $this->country = $country;
        $this->countryCode = $countryCode;
        $this->imageName = $imageName;
        $this->teamName = $teamName;
        $this->userId = $userId;
        $this->countryId = $countryId;
        $this->stateId = $stateId;
        $this->cityId = $cityId;
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
