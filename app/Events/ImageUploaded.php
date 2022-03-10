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
    public $city, $state, $country, $countryCode, $imageName, $teamName, $isUserVerified;

    // For CheckContributors
    public $userId, $countryId, $stateId, $cityId, $latitude, $longitude, $teamId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct (
        string $city,
        string $state,
        string $country,
        string $countryCode,
        string $imageName,
        ?string $teamName,
        int $userId,
        int $countryId,
        int $stateId,
        int $cityId,
        float $latitude,
        float $longitude,
        bool $isUserVerified,
        ?int $teamId
    )
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
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->isUserVerified = $isUserVerified;
        $this->teamId = $teamId;
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
