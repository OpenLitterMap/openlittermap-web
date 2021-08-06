<?php

namespace App\Events;

use App\Models\User\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ImageDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var User */
    public $user;
    public $countryId;
    public $stateId;
    public $cityId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct (User $user, int $countryId, int $stateId, int $cityId)
    {
        $this->user = $user;
        $this->countryId = $countryId;
        $this->stateId = $stateId;
        $this->cityId = $cityId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn (): Channel
    {
        return new Channel('main');
    }
}
