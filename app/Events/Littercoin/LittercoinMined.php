<?php

namespace App\Events\Littercoin;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LittercoinMined implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    public $userId;
    public $reason;
    public $now;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct ($userId, $reason)
    {
        $this->userId = $userId;
        $this->reason = $reason;
        $this->now = now();
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
