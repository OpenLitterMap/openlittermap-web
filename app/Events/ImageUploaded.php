<?php

namespace App\Events;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
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
    public $photoId, $userId, $countryId, $stateId, $cityId, $latitude, $longitude, $teamId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct (
        User $user,
        Photo $photo,
        Country $country,
        State $state,
        City $city
    )
    {
        $this->city = $city->city;
        $this->state = $state->state;
        $this->country = $country->country;
        $this->countryCode = $country->shortcode;
        $this->imageName = $photo->filename;
        $this->teamName = $user->team->name ?? null;
        $this->userId = $user->id;
        $this->photoId = $photo->id;
        $this->countryId = $country->id;
        $this->stateId = $state->id;
        $this->cityId = $city->id;
        $this->latitude = $photo->lat;
        $this->longitude = $photo->lon;
        $this->isUserVerified = $user->is_trusted;
        $this->teamId = $user->active_team;
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
