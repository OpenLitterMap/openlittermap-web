<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagsVerifiedByAdmin implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $photo_id;
    public int $user_id;
    public int $country_id;
    public ?int $state_id;
    public ?int $city_id;
    public ?int $team_id;

    /**
     * Tags on a photo have been verified.
     *
     * This event is intentionally slim. MetricsService loads the photo
     * and extracts all tag/XP data from the summary JSON directly.
     * Listeners that need more data should load it themselves.
     */
    public function __construct(
        int $photo_id,
        int $user_id,
        int $country_id,
        ?int $state_id,
        ?int $city_id = null,
        ?int $team_id = null
    ) {
        $this->photo_id = $photo_id;
        $this->user_id = $user_id;
        $this->country_id = $country_id;
        $this->state_id = $state_id;
        $this->city_id = $city_id;
        $this->team_id = $team_id;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('main');
    }
}
