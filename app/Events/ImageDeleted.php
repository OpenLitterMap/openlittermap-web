<?php

namespace App\Events;

use App\Models\User\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ImageDeleted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    /** @var User */
    public $user;

    public $countryId;

    public $stateId;

    public $cityId;

    public $isUserVerified;

    public $teamId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct (User $user, int $countryId, int $stateId, int $cityId, ?int $teamId)
    {
        $this->user = $user;
        $this->countryId = $countryId;
        $this->stateId = $stateId;
        $this->cityId = $cityId;
        $this->isUserVerified = $user->is_trusted;
        $this->teamId = $teamId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn (): Channel
    {
        return new Channel('main');
    }
}
