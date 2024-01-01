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
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    // For Websockets
    public $city;
    // For Websockets
    public $state;
    // For Websockets
    public $country;
    // For Websockets
    public $countryCode;
    // For Websockets
    public $teamName;
    // For Websockets
    public $isUserVerified;
    // For Websockets
    public $isPickedUp;
    // For Websockets
    public $photoSource;

    // For CheckContributors
    public $photoId;
    // For CheckContributors
    public $userId;
    // For CheckContributors
    public $user;
    // For CheckContributors
    public $countryId;
    // For CheckContributors
    public $stateId;
    // For CheckContributors
    public $cityId;
    // For CheckContributors
    public $latitude;
    // For CheckContributors
    public $longitude;
    // For CheckContributors
    public $teamId;

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
        $this->user = [
            'name' => $user->show_name_maps ? $user->name : '',
            'username' => $user->show_username_maps ? $user->username : '',
        ];
        $this->city = $city->city;
        $this->state = $state->state;
        $this->country = $country->country;
        $this->countryCode = $country->shortcode;
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
        $this->isPickedUp = $photo->picked_up;
        $this->photoSource = $photo->platform;
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
